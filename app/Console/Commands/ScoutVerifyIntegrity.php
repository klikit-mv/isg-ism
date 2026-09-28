<?php

namespace App\Console\Commands;

use App\Enums\ParentLinkStatus;
use App\Enums\Role;
use App\Enums\ScoutSection;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('scout:verify-integrity')]
#[Description('Check the database for broken rules; exits non-zero on problems')]
class ScoutVerifyIntegrity extends Command
{
    public function handle(): int
    {
        $checks = [
            'Duplicate attendance rows' => DB::table('attendance_records')->select('activity_id', 'student_id')->groupBy('activity_id', 'student_id')->havingRaw('COUNT(*) > 1')->get()->count(),
            'Duplicate class fees' => DB::table('class_fees')->select('activity_id', 'student_id')->groupBy('activity_id', 'student_id')->havingRaw('COUNT(*) > 1')->get()->count(),
            'Duplicate parent links' => DB::table('parent_student_links')->select('parent_user_id', 'student_id')->groupBy('parent_user_id', 'student_id')->havingRaw('COUNT(*) > 1')->get()->count(),
            'Scouts with more than one open parent' => DB::table('parent_student_links')->whereIn('status', [ParentLinkStatus::Pending->value, ParentLinkStatus::Approved->value])->select('student_id')->groupBy('student_id')->havingRaw('COUNT(*) > 1')->get()->count(),
            'Negative stock' => DB::table('shop_items')->where('stock_qty', '<', 0)->count(),
            'Orphan payment proofs' => DB::table('payment_proofs')->leftJoin('payments', 'payments.id', '=', 'payment_proofs.payment_id')->whereNull('payments.id')->count(),
            'Online payments without proof' => DB::table('payments')->where('method', 'online')->whereNull('legacy_id')->whereNotExists(fn ($q) => $q->selectRaw('1')->from('payment_proofs')->whereColumn('payment_proofs.payment_id', 'payments.id'))->count(),
            'Orphan payments' => $this->orphanPayments(),
            'Orphan group members' => DB::table('group_members')->leftJoin('students', 'students.id', '=', 'group_members.student_id')->whereNull('students.id')->count()
                + DB::table('group_members')->leftJoin('groups', 'groups.id', '=', 'group_members.group_id')->whereNull('groups.id')->count(),
            'Invalid sections' => DB::table('students')->whereNotIn('section', ScoutSection::values())->count(),
            'Invalid roles' => DB::table('user_roles')->whereNotIn('role', Role::values())->count(),
        ];

        $rows = [];
        $problems = 0;

        foreach ($checks as $label => $count) {
            $rows[] = [$label, $count, $count === 0 ? 'ok' : 'PROBLEM'];
            $problems += $count;
        }

        $this->table(['Check', 'Count', 'Result'], $rows);

        $this->table(['Information', 'Count'], [
            ['Students', DB::table('students')->whereNull('deleted_at')->count()],
            ['Users', DB::table('users')->whereNull('deleted_at')->count()],
            ['Groups', DB::table('groups')->whereNull('deleted_at')->count()],
            ['Activities', DB::table('activities')->whereNull('deleted_at')->count()],
            ['Payments', DB::table('payments')->count()],
            ['Certificates', DB::table('certificates')->count()],
        ]);

        if ($problems > 0) {
            $this->error("{$problems} problem(s) found.");

            return self::FAILURE;
        }

        $this->info('No problems found.');

        return self::SUCCESS;
    }

    private function orphanPayments(): int
    {
        $count = 0;

        foreach (['class_fee' => 'class_fees', 'annual_fee' => 'annual_fees', 'purchase' => 'purchases', 'event_registration' => 'event_registrations'] as $type => $table) {
            $count += DB::table('payments')->where('payable_type', $type)
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from($table)->whereColumn("{$table}.id", 'payments.payable_id'))
                ->count();
        }

        return $count + DB::table('payments')->whereNotIn('payable_type', ['class_fee', 'annual_fee', 'purchase', 'event_registration'])->count();
    }
}
