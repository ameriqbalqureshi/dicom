<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An instance from a zip has no file of its own: it records where its bytes are inside the stored zip.
     */
    public function up(): void
    {
        Schema::table('dicom_instances', function (Blueprint $table) {
            $table->string('file_path')->nullable()->change();
            $table->foreignId('dicom_archive_id')->nullable()->after('series_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('archive_entry_offset')->nullable()->after('file_size');
            $table->unsignedBigInteger('archive_entry_length')->nullable()->after('archive_entry_offset');
            $table->unsignedBigInteger('archive_entry_size')->nullable()->after('archive_entry_length');
            $table->unsignedTinyInteger('archive_entry_method')->nullable()->after('archive_entry_size');
        });
    }

    /**
     * Reverse the migrations. Instances stored in zips have no file of their own, so they cannot survive this.
     */
    public function down(): void
    {
        DB::table('dicom_instances')->whereNotNull('dicom_archive_id')->delete();

        Schema::table('dicom_instances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dicom_archive_id');
            $table->dropColumn(['archive_entry_offset', 'archive_entry_length', 'archive_entry_size', 'archive_entry_method']);
            $table->string('file_path')->nullable(false)->change();
        });
    }
};
