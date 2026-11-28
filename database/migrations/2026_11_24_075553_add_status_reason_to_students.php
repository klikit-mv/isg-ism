<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'status_reason')) {
                $table->text('status_reason')->nullable();
            }

            if (! Schema::hasColumn('students', 'status_changed_at')) {
                $table->timestamp('status_changed_at')->nullable();
            }

            if (! Schema::hasColumn('students', 'status_changed_by')) {
                $table->foreignId('status_changed_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('status_changed_by');
            $table->dropColumn(['status_reason', 'status_changed_at']);
        });
    }
};
