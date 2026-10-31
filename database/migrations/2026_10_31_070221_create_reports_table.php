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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('indication')->nullable();
            $table->text('findings');
            $table->text('impression');
            $table->timestamp('finalized_at')->nullable();
            $table->string('author_name')->nullable();
            $table->string('author_qualifications')->nullable();
            $table->string('author_registration_number')->nullable();
            $table->timestamps();

            $table->index(['study_id', 'finalized_at']);
            $table->index('author_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
