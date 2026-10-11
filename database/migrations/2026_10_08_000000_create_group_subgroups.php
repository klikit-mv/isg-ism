<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_subgroups', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['group_id', 'name']);
        });

        Schema::table('group_members', function (Blueprint $table) {
            $table->foreignId('subgroup_id')->nullable()->after('student_id')->constrained('group_subgroups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subgroup_id');
        });
        Schema::dropIfExists('group_subgroups');
    }
};
