<?php

namespace App\Services;

use App\Enums\EventRegistrationStatus;
use App\Enums\EventStatus;
use App\Enums\FeeStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\ScoutException;
use App\Exceptions\StudentNotAccessible;
use App\Models\Event;
use App\Models\EventItem;
use App\Models\EventRegistration;
use App\Models\EventRegistrationItem;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Events: setup by admins and leaders, registration with pre-ordered
 * items by parents and scouts, paid through the normal payment flow.
 */
class EventService
{
    public function __construct(
        private LeaderScopeService $scope,
        private PaymentBalanceService $balances,
        private AuditLogService $audit,
    ) {}

    /**
     * Admins manage every event; leaders manage the events they created.
     */
    public function canManage(User $user, Event $event): bool
    {
        return $user->isAdmin() || ($user->isActive() && $user->isLeader() && $event->created_by === $user->id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Event
    {
        $event = Event::query()->create($this->attributes($data) + [
            'status' => EventStatus::Draft,
            'created_by' => $actor->id,
        ]);

        $this->audit->record('event.created', $event, ['name' => $event->name], $actor);

        return $event;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Event $event, array $data, User $actor): Event
    {
        $event->update($this->attributes($data));
        $this->audit->record('event.updated', $event, ['name' => $event->name], $actor);

        return $event;
    }

    public function setStatus(Event $event, EventStatus $status, User $actor): void
    {
        $event->update(['status' => $status]);
        $this->audit->record('event.status_changed', $event, ['status' => $status], $actor);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveItem(Event $event, array $data, User $actor, ?EventItem $item = null): EventItem
    {
        $attributes = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price' => Money::normalize($data['price'] ?? 0),
            'sizes' => $this->sizes($data['sizes'] ?? null),
            'stock' => filled($data['stock'] ?? null) ? (int) $data['stock'] : null,
            'max_per_registration' => max(1, (int) ($data['max_per_registration'] ?? 5)),
            'active' => (bool) ($data['active'] ?? true),
        ];

        if ($item) {
            $item->update($attributes);
        } else {
            $item = $event->items()->create($attributes);
        }

        $this->audit->record('event.item_saved', $event, ['item' => $item->name, 'price' => $item->price], $actor);

        return $item;
    }

    public function deleteItem(EventItem $item, User $actor): void
    {
        if ($item->orderLines()->exists()) {
            $item->update(['active' => false]);
            $this->audit->record('event.item_deactivated', $item->event, ['item' => $item->name], $actor);

            return;
        }

        $this->audit->record('event.item_deleted', $item->event, ['item' => $item->name], $actor);
        $item->delete();
    }

    /**
     * Register a scout with optional pre-ordered items.
     *
     * @param  list<array{item: string, quantity: int|string, size?: ?string}>  $lines
     */
    public function register(Event $event, Student $student, array $lines, PaymentMethod $paymentOption, User $actor, ?string $notes = null): EventRegistration
    {
        if (! $this->scope->canAccessStudent($actor, $student)) {
            throw new StudentNotAccessible('You cannot register this scout.');
        }

        return DB::transaction(function () use ($event, $student, $lines, $paymentOption, $actor, $notes): EventRegistration {
            $event = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();

            if (! $event->acceptsRegistrations()) {
                throw new ScoutException('Registration for this event is not open.');
            }

            if ($student->section === null || ! $event->isOpenForSection($student->section)) {
                throw new ScoutException("{$event->name} is only for {$event->sectionsLabel()}.");
            }

            $existing = EventRegistration::query()->where('event_id', $event->id)->where('student_id', $student->id)->lockForUpdate()->first();

            if ($existing?->isActive()) {
                throw new ScoutException("{$student->name} is already registered for {$event->name}.");
            }

            if ($event->capacity !== null && $event->registeredCount() >= $event->capacity) {
                throw new ScoutException('This event is full.');
            }

            $orderLines = $this->orderLines($event, $lines, $existing?->id);
            $itemsTotal = Money::add('0', ...array_column($orderLines, 'total_amount'));
            $total = Money::add($event->fee, $itemsTotal);

            $attributes = [
                'registered_by' => $actor->id,
                'status' => EventRegistrationStatus::Registered,
                'payment_option' => $paymentOption,
                'fee_amount' => Money::normalize($event->fee),
                'items_amount' => $itemsTotal,
                'total_amount' => $total,
                'paid_amount' => '0.00',
                'outstanding_amount' => $total,
                'payment_status' => FeeStatus::Pending,
                'notes' => $notes,
            ];

            if ($existing) {
                $existing->items()->delete();
                $existing->update($attributes);
                $registration = $existing;
            } else {
                $registration = EventRegistration::query()->create($attributes + ['event_id' => $event->id, 'student_id' => $student->id]);
            }

            foreach ($orderLines as $line) {
                $registration->items()->create($line);
            }

            $this->balances->calculateEventRegistrationBalance($registration);
            $this->audit->record('event.registered', $registration, [
                'event' => $event->name,
                'student' => $student->national_id,
                'total' => $total,
            ], $actor);

            return $registration;
        });
    }

    /**
     * The buyer side or event staff may cancel until money is received.
     */
    public function cancel(EventRegistration $registration, User $actor): void
    {
        DB::transaction(function () use ($registration, $actor): void {
            $locked = EventRegistration::query()->whereKey($registration->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isActive()) {
                return;
            }

            $hasMoney = Money::isPositive($locked->paid_amount) || Payment::query()
                ->where('payable_type', 'event_registration')
                ->where('payable_id', $locked->id)
                ->whereIn('status', [PaymentStatus::Paid->value, PaymentStatus::AwaitingVerification->value])
                ->exists();

            if ($hasMoney) {
                throw new ScoutException('This registration has a payment and cannot be cancelled. Ask an administrator.');
            }

            $locked->update(['status' => EventRegistrationStatus::Cancelled]);
            $this->audit->record('event.registration_cancelled', $locked, [], $actor);
        });
    }

    /**
     * What to order: quantity per item and size across active registrations.
     *
     * @return Collection<int, array{item: string, size: ?string, quantity: int, amount: string}>
     */
    public function orderSummary(Event $event): Collection
    {
        return EventRegistrationItem::query()
            ->whereHas('registration', fn ($q) => $q->where('event_id', $event->id)->where('status', EventRegistrationStatus::Registered->value))
            ->selectRaw('item_name, size, SUM(quantity) as quantity, SUM(total_amount) as amount')
            ->groupBy('item_name', 'size')
            ->orderBy('item_name')
            ->orderBy('size')
            ->get()
            ->map(fn ($row) => [
                'item' => (string) $row->item_name,
                'size' => $row->size,
                'quantity' => (int) $row->quantity,
                'amount' => Money::normalize($row->amount),
            ]);
    }

    /**
     * @param  list<array{item: string, quantity: int|string, size?: ?string}>  $lines
     * @return list<array{event_item_id: int, item_name: string, size: ?string, quantity: int, unit_price: string, total_amount: string}>
     */
    private function orderLines(Event $event, array $lines, ?int $exceptRegistrationId): array
    {
        $result = [];
        $wanted = [];

        foreach ($lines as $line) {
            $quantity = (int) ($line['quantity'] ?? 0);

            if ($quantity <= 0) {
                continue;
            }

            $item = EventItem::query()->where('event_id', $event->id)->where('uuid', $line['item'] ?? '')->lockForUpdate()->first();

            if ($item === null || ! $item->active) {
                throw new ScoutException('One of the chosen items is no longer available.');
            }

            if ($quantity > $item->max_per_registration) {
                throw new ScoutException("You can order at most {$item->max_per_registration} × {$item->name}.");
            }

            $sizes = $item->sizeList();
            $size = trim((string) ($line['size'] ?? ''));

            if ($sizes !== [] && ! in_array($size, $sizes, true)) {
                throw new ScoutException("Choose a size for {$item->name}.");
            }

            $wanted[$item->id] = ($wanted[$item->id] ?? 0) + $quantity;

            if ($item->stock !== null && $item->orderedQuantity($exceptRegistrationId) + $wanted[$item->id] > $item->stock) {
                $left = max(0, $item->stock - $item->orderedQuantity($exceptRegistrationId));

                throw new ScoutException("Only {$left} × {$item->name} left.");
            }

            $result[] = [
                'event_item_id' => $item->id,
                'item_name' => $item->name,
                'size' => $sizes === [] ? null : $size,
                'quantity' => $quantity,
                'unit_price' => Money::normalize($item->price),
                'total_amount' => Money::mul($item->price, $quantity),
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'location' => $data['location'] ?? null,
            'starts_at' => $this->localTime($data['starts_at']),
            'ends_at' => $this->localTime($data['ends_at'] ?? null),
            'registration_closes_at' => $this->localTime($data['registration_closes_at'] ?? null),
            'fee' => Money::normalize($data['fee'] ?? 0),
            'capacity' => filled($data['capacity'] ?? null) ? (int) $data['capacity'] : null,
            'sections' => array_values(array_unique($data['sections'] ?? [])) ?: null,
        ];
    }

    /**
     * Form times are entered in the organisation timezone; store them in UTC.
     */
    private function localTime(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        return $value instanceof CarbonInterface
            ? Carbon::instance($value)->utc()
            : Carbon::parse((string) $value, config('scout.timezone'))->utc();
    }

    /**
     * @return list<string>|null
     */
    private function sizes(mixed $value): ?array
    {
        $list = is_array($value) ? $value : preg_split('/[,;\n]+/', (string) $value);
        $list = array_values(array_unique(array_filter(array_map('trim', $list ?: []))));

        return $list === [] ? null : $list;
    }
}
