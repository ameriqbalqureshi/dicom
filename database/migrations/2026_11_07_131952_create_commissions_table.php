<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Names, the client's role, the amount and the rate are copies, so past earnings stay as they were
     * when rates change or accounts are deleted.
     */
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('officer_name');
            $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('client_name')->nullable();
            $table->string('client_role')->nullable();
            $table->foreignId('subscription_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('payout_id')->nullable()->constrained('commission_payouts')->nullOnDelete();
            $table->foreignId('reverses_id')->nullable()->constrained('commissions')->nullOnDelete();
            $table->string('type');
            $table->string('status')->default('pending');
            $table->unsignedInteger('base_amount')->nullable();
            $table->decimal('rate_percent', 5, 2)->nullable();
            $table->integer('amount');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('available_at');
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reversal_reason')->nullable();
            $table->timestamps();

            $table->index(['officer_id', 'status', 'available_at']);
            $table->index(['client_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
