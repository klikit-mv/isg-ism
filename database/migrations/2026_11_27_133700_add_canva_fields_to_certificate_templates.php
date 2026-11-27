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
        if (Schema::hasColumn('certificate_templates', 'canva_url')) {
            return;
        }

        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->string('canva_url', 500)->nullable();
            $table->string('background_path')->nullable();
            $table->unsignedTinyInteger('text_top')->default(38);
            $table->string('text_color', 7)->default('#1f2937');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('certificate_templates', 'canva_url')) {
            return;
        }

        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->dropColumn(['canva_url', 'background_path', 'text_top', 'text_color']);
        });
    }
};
