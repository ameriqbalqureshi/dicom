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
        Schema::create('patient_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('medical_record_number', 100)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('sex', 1)->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('risk_factors')->nullable();
            $table->mediumText('diagnosis')->nullable();
            $table->mediumText('history')->nullable();
            $table->mediumText('medications')->nullable();
            $table->mediumText('allergies')->nullable();
            $table->mediumText('examination')->nullable();
            $table->mediumText('investigations')->nullable();
            $table->mediumText('procedures')->nullable();
            $table->mediumText('plan')->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'updated_at']);
            $table->index(['owner_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_records');
    }
};
