<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('badge_id')->unique();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('section')->nullable();
            $table->text('description')->nullable();
            $table->string('category')->default('proficiency');
            $table->string('image_path')->nullable();
            $table->foreignId('certificate_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number_prefix')->nullable();
            $table->timestamps();
        });

        Schema::create('badge_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('request_id')->unique();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->string('student_name');
            $table->foreignId('badge_id')->constrained()->restrictOnDelete();
            $table->string('badge_name');
            $table->string('status')->default('requested')->index();
            $table->string('certificate_number')->nullable();
            $table->date('date_awarded')->nullable();
            $table->string('certificate_path')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('certificate_counters', function (Blueprint $table) {
            $table->id();
            $table->string('counter_id')->unique();
            $table->foreignId('badge_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('cert_id')->unique();
            $table->string('type')->index();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->string('student_name');
            $table->string('title')->nullable();
            $table->string('cert_number')->unique();
            $table->string('id_card_no')->nullable();
            $table->date('date_awarded');
            $table->string('path')->nullable();
            $table->string('status')->default('issued')->index();
            $table->foreignId('badge_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('badge_name')->nullable();
            $table->foreignId('template_id')->nullable()->constrained('certificate_templates')->restrictOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('badge_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('leadership_records', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->string('patrol_or_six');
            $table->string('troop_or_group');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->foreignId('certificate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leadership_records');
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('certificate_counters');
        Schema::dropIfExists('badge_requests');
        Schema::dropIfExists('badges');
    }
};
