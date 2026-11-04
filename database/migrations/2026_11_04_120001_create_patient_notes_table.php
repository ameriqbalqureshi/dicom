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
        Schema::create('patient_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_record_id')->constrained()->cascadeOnDelete();
            $table->date('noted_on');
            $table->mediumText('body');
            $table->timestamps();

            $table->index(['patient_record_id', 'noted_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_notes');
    }
};
