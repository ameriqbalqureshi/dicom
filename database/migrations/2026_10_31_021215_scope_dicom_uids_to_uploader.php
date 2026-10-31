<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make DICOM UIDs unique per uploader, so each user keeps an independent copy of a study they upload.
     */
    public function up(): void
    {
        Schema::table('studies', function (Blueprint $table) {
            $table->dropUnique(['study_instance_uid']);
            $table->unique(['uploaded_by', 'study_instance_uid']);
        });

        Schema::table('series', function (Blueprint $table) {
            $table->dropUnique(['series_instance_uid']);
            $table->unique(['study_id', 'series_instance_uid']);
        });

        Schema::table('dicom_instances', function (Blueprint $table) {
            $table->dropUnique(['sop_instance_uid']);
            $table->unique(['uploaded_by', 'sop_instance_uid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dicom_instances', function (Blueprint $table) {
            $table->dropUnique(['uploaded_by', 'sop_instance_uid']);
            $table->unique(['sop_instance_uid']);
        });

        Schema::table('series', function (Blueprint $table) {
            $table->dropUnique(['study_id', 'series_instance_uid']);
            $table->unique(['series_instance_uid']);
        });

        Schema::table('studies', function (Blueprint $table) {
            $table->dropUnique(['uploaded_by', 'study_instance_uid']);
            $table->unique(['study_instance_uid']);
        });
    }
};
