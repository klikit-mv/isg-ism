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
        if (Schema::hasColumn('certificates', 'issuer')) {
            return;
        }

        Schema::table('certificates', function (Blueprint $table) {
            $table->string('issuer')->nullable()->after('title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('certificates', 'issuer')) {
            return;
        }

        Schema::table('certificates', function (Blueprint $table) {
            $table->dropColumn('issuer');
        });
    }
};
