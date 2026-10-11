<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('name');
            $table->string('bank_name');
            $table->string('account_name')->nullable();
            $table->string('account_number');
            $table->decimal('opening_balance', 12, 2)->default(0);
            $table->string('status')->default('Active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->string('type')->index();
            $table->decimal('amount', 12, 2);
            $table->date('transaction_date')->index();
            $table->string('party')->comment('Deposit: collected from. Expense: requested by.');
            $table->string('purpose')->nullable();
            $table->text('details')->nullable();
            $table->string('reference')->nullable();
            $table->string('attachment_disk')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('bank_accounts');
    }
};
