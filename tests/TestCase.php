<?php

namespace Tests;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    protected function leader(Permission ...$permissions): User
    {
        return User::factory()->leader()->withPermissions(...$permissions)->create();
    }

    /**
     * A parent with approved links to the given scouts.
     */
    protected function parentOf(Student ...$students): User
    {
        $parent = User::factory()->withRoles(Role::Parent)->create();

        foreach ($students as $student) {
            $parent->parentLinks()->create(['student_id' => $student->id, 'status' => 'approved']);
        }

        return $parent;
    }

    protected function studentUser(?Student $student = null): User
    {
        return User::factory()->forStudent($student)->create();
    }
}
