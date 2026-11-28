<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->nullable();
            $table->dateTime('registration_closes_at')->nullable();
            $table->decimal('fee', 12, 2)->default(0);
            $table->unsignedInteger('capacity')->nullable();
            $table->json('sections')->nullable();
            $table->string('status')->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('event_items', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->json('sizes')->nullable();
            $table->unsignedInteger('stock')->nullable();
            $table->unsignedInteger('max_per_registration')->default(5);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('registered')->index();
            $table->string('payment_option')->default('online');
            $table->decimal('fee_amount', 12, 2)->default(0);
            $table->decimal('items_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('outstanding_amount', 12, 2)->default(0);
            $table->string('payment_status')->default('Pending')->index();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'student_id']);
        });

        Schema::create('event_registration_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_item_id')->constrained()->restrictOnDelete();
            $table->string('item_name');
            $table->string('size')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registration_items');
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('event_items');
        Schema::dropIfExists('events');
    }
};
