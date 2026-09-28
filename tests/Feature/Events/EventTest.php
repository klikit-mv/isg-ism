<?php

namespace Tests\Feature\Events;

use App\Enums\EventRegistrationStatus;
use App\Enums\EventStatus;
use App\Enums\FeeStatus;
use App\Enums\ScoutSection;
use App\Models\Event;
use App\Models\EventItem;
use App\Models\EventRegistration;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Services\EventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function openEvent(array $overrides = []): Event
    {
        return Event::query()->create($overrides + [
            'name' => 'Summer Camp',
            'starts_at' => now()->addMonth(),
            'fee' => '100.00',
            'status' => EventStatus::Open,
            'created_by' => $this->admin()->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function shirt(Event $event, array $overrides = []): EventItem
    {
        return $event->items()->create($overrides + [
            'name' => 'Camp T-shirt',
            'price' => '50.00',
            'sizes' => ['S', 'M', 'L'],
            'max_per_registration' => 3,
            'active' => true,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function register(User $user, Event $event, Student $student, array $items = [], string $option = 'online'): TestResponse
    {
        return $this->actingAs($user)->post("/events/{$event->uuid}/register", [
            'student' => $student->uuid,
            'payment_option' => $option,
            'items' => $items,
        ]);
    }

    public function test_leader_creates_an_event_as_draft_and_sets_it_up(): void
    {
        $leader = $this->leader();

        $this->actingAs($leader)->post('/events', [
            'name' => 'Hike Day', 'starts_at' => '2026-11-10T08:00', 'fee' => '25', 'sections' => ['Scout', 'Rover'],
        ])->assertSessionHasNoErrors();

        $event = Event::query()->where('name', 'Hike Day')->firstOrFail();
        $this->assertSame('10.11.2026 08:00', scout_datetime($event->starts_at));
        $this->assertSame(EventStatus::Draft, $event->status);
        $this->assertSame([ScoutSection::Scout, ScoutSection::Rover], $event->eligibleSections());

        $this->actingAs($leader)->post("/events/{$event->uuid}/items", [
            'name' => 'Badge', 'price' => '15', 'sizes' => 'S, M ,L', 'max_per_registration' => 2, 'active' => 1,
        ])->assertSessionHas('success');
        $this->assertSame(['S', 'M', 'L'], $event->items()->firstOrFail()->sizeList());

        $this->actingAs($leader)->post("/events/{$event->uuid}/status", ['status' => 'open'])->assertSessionHas('success');
        $this->assertSame(EventStatus::Open, $event->fresh()->status);
    }

    public function test_parents_cannot_create_or_manage_events_and_drafts_are_hidden(): void
    {
        $parent = $this->parentOf(Student::factory()->create());
        $draft = $this->openEvent(['status' => EventStatus::Draft]);

        $this->actingAs($parent)->post('/events', ['name' => 'Nope', 'starts_at' => now()->addWeek(), 'fee' => 0])->assertForbidden();
        $this->actingAs($parent)->post("/events/{$draft->uuid}/items", ['name' => 'X', 'price' => 1, 'max_per_registration' => 1])->assertForbidden();
        $this->actingAs($parent)->get("/events/{$draft->uuid}")->assertNotFound();
        $this->actingAs($parent)->get('/events')->assertOk()->assertDontSee('Summer Camp');
    }

    public function test_another_leader_cannot_manage_an_event_they_did_not_create(): void
    {
        $event = $this->openEvent();

        $this->actingAs($this->leader())->put("/events/{$event->uuid}", ['name' => 'Taken', 'starts_at' => now()->addWeek(), 'fee' => 0])->assertForbidden();
    }

    public function test_parent_registers_a_child_with_preordered_items_and_totals_add_up(): void
    {
        $student = Student::factory()->create();
        $parent = $this->parentOf($student);
        $event = $this->openEvent();
        $shirt = $this->shirt($event);

        $this->register($parent, $event, $student, [['item' => $shirt->uuid, 'quantity' => 2, 'size' => 'M']])
            ->assertSessionHas('open_payment');

        $registration = EventRegistration::query()->firstOrFail();
        $this->assertSame('100.00', $registration->fee_amount);
        $this->assertSame('100.00', $registration->items_amount);
        $this->assertSame('200.00', $registration->total_amount);
        $this->assertSame(FeeStatus::Pending, $registration->payment_status);
        $this->assertSame('M', $registration->items->first()->size);

        $this->actingAs($this->admin())->get("/events/{$event->uuid}")->assertOk()->assertSee('Camp T-shirt')->assertSee('200.00');
    }

    public function test_registration_rules_are_enforced(): void
    {
        $student = Student::factory()->create();
        $parent = $this->parentOf($student);
        $event = $this->openEvent(['capacity' => 1]);
        $shirt = $this->shirt($event, ['stock' => 1]);

        $this->register($parent, $event, $student, [['item' => $shirt->uuid, 'quantity' => 1]])
            ->assertSessionHas('error', 'Choose a size for Camp T-shirt.');
        $this->register($parent, $event, $student, [['item' => $shirt->uuid, 'quantity' => 2, 'size' => 'S']])
            ->assertSessionHas('error', 'Only 1 × Camp T-shirt left.');

        $this->register($parent, $event, $student)->assertSessionHas('success');
        $this->register($parent, $event, $student)->assertSessionHas('error', "{$student->name} is already registered for Summer Camp.");

        $other = Student::factory()->create();
        $this->register($this->parentOf($other), $event, $other)->assertSessionHas('error', 'This event is full.');

        $cub = Student::factory()->section(ScoutSection::CubScout)->create();
        $roverOnly = $this->openEvent(['name' => 'Rover Moot', 'sections' => ['Rover']]);
        $this->register($this->parentOf($cub), $roverOnly, $cub)->assertSessionHas('error', 'Rover Moot is only for Rover.');

        $closed = $this->openEvent(['name' => 'Past', 'registration_closes_at' => now()->subDay()]);
        $this->register($parent, $closed, $student)->assertSessionHas('error', 'Registration for this event is not open.');
    }

    public function test_parent_cannot_register_someone_elses_child(): void
    {
        $event = $this->openEvent();

        $this->register($this->parentOf(Student::factory()->create()), $event, Student::factory()->create())->assertSessionHas('error', 'You cannot register this scout.');
        $this->assertSame(0, EventRegistration::query()->count());
    }

    public function test_online_payment_is_verified_and_marks_registration_paid(): void
    {
        $student = Student::factory()->create();
        $parent = $this->parentOf($student);
        $event = $this->openEvent();
        $this->register($parent, $event, $student);
        $registration = EventRegistration::query()->firstOrFail();

        $this->actingAs($parent)->post('/payments', [
            'payable_type' => 'event_registration', 'payable_id' => $registration->uuid, 'amount' => '100.00', 'method' => 'online',
            'proof' => UploadedFile::fake()->image('proof.png'),
        ])->assertSessionHasNoErrors();

        $payment = Payment::query()->firstOrFail();
        $this->actingAs($this->admin())->post("/payments/{$payment->uuid}/approve");

        $registration->refresh();
        $this->assertSame(FeeStatus::Paid, $registration->payment_status);
        $this->assertSame('0.00', $registration->outstanding_amount);

        $this->actingAs($parent)->post("/events/registrations/{$registration->uuid}/cancel")
            ->assertSessionHas('error', 'This registration has a payment and cannot be cancelled. Ask an administrator.');
    }

    public function test_free_event_without_items_is_paid_straight_away(): void
    {
        $student = Student::factory()->create();
        $event = $this->openEvent(['fee' => '0']);

        $this->register($this->parentOf($student), $event, $student)->assertSessionMissing('open_payment');

        $this->assertSame(FeeStatus::Paid, EventRegistration::query()->firstOrFail()->payment_status);
    }

    public function test_unpaid_registration_can_be_cancelled_and_registered_again(): void
    {
        $student = Student::factory()->create();
        $parent = $this->parentOf($student);
        $event = $this->openEvent();
        $this->register($parent, $event, $student);
        $registration = EventRegistration::query()->firstOrFail();

        $this->actingAs($parent)->post("/events/registrations/{$registration->uuid}/cancel")->assertSessionHas('success');
        $this->assertSame(EventRegistrationStatus::Cancelled, $registration->fresh()->status);

        $this->register($parent, $event, $student)->assertSessionHas('success');
        $this->assertSame(1, EventRegistration::query()->count());
        $this->assertTrue($registration->fresh()->isActive());
    }

    public function test_order_summary_groups_items_by_size(): void
    {
        $event = $this->openEvent();
        $shirt = $this->shirt($event);
        $admin = $this->admin();

        foreach (['M', 'M', 'L'] as $size) {
            $this->register($admin, $event, Student::factory()->create(), [['item' => $shirt->uuid, 'quantity' => 1, 'size' => $size]]);
        }

        $this->actingAs($admin)->get("/events/{$event->uuid}")->assertOk();
        $summary = app(EventService::class)->orderSummary($event)->keyBy('size');
        $this->assertSame(2, $summary['M']['quantity']);
        $this->assertSame('100.00', $summary['M']['amount']);
        $this->assertSame(1, $summary['L']['quantity']);
    }

    public function test_event_pages_render(): void
    {
        $student = Student::factory()->create();
        $parent = $this->parentOf($student);
        $event = $this->openEvent();
        $this->shirt($event);

        $this->actingAs($parent)->get('/events')->assertOk()->assertSee('Summer Camp');
        $this->actingAs($parent)->get("/events/{$event->uuid}")->assertOk()->assertSee($student->name);
        $this->register($parent, $event, $student);
        $this->actingAs($parent)->get('/events/registrations')->assertOk()->assertSee('Summer Camp');
        $this->actingAs($this->admin())->get('/events/create')->assertOk();
        $this->actingAs($this->admin())->get("/events/{$event->uuid}/edit")->assertOk();
    }
}
