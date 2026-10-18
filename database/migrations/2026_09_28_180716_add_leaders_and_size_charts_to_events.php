<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Leaders register themselves (no scout record), and event items can
     * carry a size chart with measurements.
     */
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->foreignId('student_id')->nullable()->change();
            $table->foreignId('user_id')->nullable()->after('student_id')->constrained()->restrictOnDelete();
            $table->unique(['event_id', 'user_id']);
        });

        Schema::table('event_items', function (Blueprint $table) {
            $table->json('size_chart')->nullable()->after('sizes');
            $table->text('size_guide')->nullable()->after('size_chart');
        });
    }

    public function down(): void
    {
        Schema::table('event_items', function (Blueprint $table) {
            $table->dropColumn(['size_chart', 'size_guide']);
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropUnique(['event_id', 'user_id']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
