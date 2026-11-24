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
        if (Schema::hasColumn('leadership_records', 'post')) {
            return;
        }

        Schema::table('leadership_records', function (Blueprint $table) {
            $table->string('post')->nullable()->after('student_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('leadership_records', 'post')) {
            return;
        }

        Schema::table('leadership_records', function (Blueprint $table) {
            $table->dropColumn('post');
        });
    }
};
