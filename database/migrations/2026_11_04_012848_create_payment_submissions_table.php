<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. A submission is a receipt sent through the public form, kept until an administrator links it
     * to an account or deletes it.
     */
    public function up(): void
    {
        Schema::create('payment_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('account_identifier');
            $table->string('phone')->nullable();
            $table->foreignId('package_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_method');
            $table->string('payment_reference')->nullable();
            $table->string('receipt_path');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_submissions');
    }
};
