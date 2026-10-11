<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('template_id')->unique();
            $table->string('name');
            $table->string('type')->index();
            $table->string('google_slide_id')->nullable();
            $table->unsignedBigInteger('activity_id')->nullable()->index();
            $table->longText('template_content')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('name');
            $table->date('date')->index();
            $table->text('details')->nullable();
            $table->boolean('all_students')->default(false);
            $table->boolean('charge_fee')->default(false);
            $table->decimal('fee_amount', 12, 2)->nullable();
            $table->foreignId('certificate_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('legacy_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('activity_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->string('section');
            $table->unique(['activity_id', 'section']);
        });

        Schema::create('activity_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->unique(['activity_id', 'group_id']);
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('activity_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->string('status')->index();
            $table->string('remarks')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('marked_at')->nullable();
            $table->timestamps();
            $table->unique(['activity_id', 'student_id']);
        });

        Schema::create('rover_attendance_records', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('activity_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->string('status')->index();
            $table->boolean('is_required')->default(true);
            $table->string('remarks')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('marked_at')->nullable();
            $table->timestamps();
            $table->unique(['activity_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rover_attendance_records');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('activity_groups');
        Schema::dropIfExists('activity_sections');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('certificate_templates');
    }
};
