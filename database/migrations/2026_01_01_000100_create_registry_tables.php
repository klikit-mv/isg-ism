<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('index_number')->unique();
            $table->string('name');
            $table->string('national_id')->unique();
            $table->string('email')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('gender')->nullable();
            $table->string('permanent_address')->nullable();
            $table->string('present_address')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('parent_name')->nullable();
            $table->string('primary_mobile')->nullable();
            $table->string('secondary_mobile')->nullable();
            $table->string('section')->index();
            $table->string('class_name')->nullable();
            $table->string('patrol')->nullable();
            $table->string('status')->default('pending')->index();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('legacy_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('parent_student_links', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('parent_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending')->index();
            $table->timestamps();
            $table->unique(['parent_user_id', 'student_id']);
        });

        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('name');
            $table->string('type')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('Active');
            $table->string('legacy_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['group_id', 'student_id']);
        });

        Schema::create('group_leaders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['group_id', 'user_id']);
        });

        Schema::create('group_assistant_leaders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['group_id', 'student_id']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('entity_type')->nullable();
            $table->string('entity_id')->nullable();
            $table->string('action')->index();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->nullable()->index();
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('group_assistant_leaders');
        Schema::dropIfExists('group_leaders');
        Schema::dropIfExists('group_members');
        Schema::dropIfExists('groups');
        Schema::dropIfExists('parent_student_links');
        Schema::dropIfExists('students');
    }
};
