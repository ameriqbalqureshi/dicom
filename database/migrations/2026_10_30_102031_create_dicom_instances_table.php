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
        Schema::create('dicom_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('series_id')->constrained()->cascadeOnDelete();
            $table->string('sop_instance_uid', 64)->unique();
            $table->string('sop_class_uid', 64)->nullable();
            $table->string('transfer_syntax_uid', 64);
            $table->unsignedInteger('instance_number')->nullable();
            $table->unsignedSmallInteger('rows')->nullable();
            $table->unsignedSmallInteger('columns')->nullable();
            $table->unsignedInteger('number_of_frames')->default(1);
            $table->string('file_path');
            $table->unsignedBigInteger('file_size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dicom_instances');
    }
};
