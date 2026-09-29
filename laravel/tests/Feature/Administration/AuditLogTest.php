<?php

namespace Tests\Feature\Administration;

use App\Models\AuditLog;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_is_append_only(): void
    {
        $log = app(AuditLogService::class)->record('test.event', null, ['a' => 1]);

        $this->expectException(LogicException::class);
        $log->update(['action' => 'changed']);
    }

    public function test_audit_log_cannot_be_deleted(): void
    {
        $log = app(AuditLogService::class)->record('test.event');

        $this->expectException(LogicException::class);
        $log->delete();
    }

    public function test_sensitive_keys_are_stripped(): void
    {
        $log = app(AuditLogService::class)->record('test.event', null, ['pin' => '1234', 'nested' => ['password' => 'x', 'ok' => 'y'], 'pin_salt' => 's']);

        $this->assertSame(['nested' => ['ok' => 'y']], $log->details);
    }

    public function test_admin_lists_and_filters_audit_logs(): void
    {
        $admin = $this->admin();
        app(AuditLogService::class)->record('payment.approved', null, [], $admin);
        app(AuditLogService::class)->record('student.created', null, [], $admin);

        $this->actingAs($admin)->get('/audit-logs?action=payment')->assertOk()->assertSee('payment.approved')->assertDontSee('student.created');
        $this->actingAs($this->leader())->get('/audit-logs')->assertForbidden();
        $this->assertSame(2, AuditLog::query()->count());
    }
}
