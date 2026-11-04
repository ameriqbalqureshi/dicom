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
        Schema::create('patient_record_studies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('study_id')->nullable()->constrained()->nullOnDelete();
            $table->date('study_date')->nullable();
            $table->string('study_description')->nullable();
            $table->timestamps();

            $table->unique(['patient_record_id', 'study_id']);
            $table->index('study_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_record_studies');
    }
};
