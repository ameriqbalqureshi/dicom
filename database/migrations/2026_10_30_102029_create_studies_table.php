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
        Schema::create('studies', function (Blueprint $table) {
            $table->id();
            $table->string('study_instance_uid', 64)->unique();
            $table->string('patient_name')->nullable()->index();
            $table->string('patient_id', 64)->nullable()->index();
            $table->date('patient_birth_date')->nullable();
            $table->string('patient_sex', 16)->nullable();
            $table->date('study_date')->nullable()->index();
            $table->string('study_description')->nullable();
            $table->string('accession_number', 64)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('studies');
    }
};
