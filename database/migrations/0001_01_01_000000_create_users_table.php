<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('name');
            $table->string('national_id')->unique();
            $table->string('email')->nullable()->unique();
            $table->string('password');
            $table->string('status')->default('inactive')->index();
            $table->unsignedBigInteger('student_id')->nullable()->unique();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->string('signature_path')->nullable();
            $table->boolean('email_notifications_enabled')->default(true);
            $table->boolean('telegram_notifications_enabled')->default(false);
            $table->string('telegram_chat_id')->nullable();
            $table->string('telegram_connect_token', 64)->nullable()->index();
            $table->timestamp('telegram_connect_token_expires_at')->nullable();
            $table->string('legacy_pin_hash')->nullable();
            $table->string('legacy_pin_salt')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('legacy_id')->nullable()->index();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();
            $table->unique(['user_id', 'role']);
        });

        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('permission');
            $table->timestamps();
            $table->unique(['user_id', 'permission']);
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('users');
    }
};
