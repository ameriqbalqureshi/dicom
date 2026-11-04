<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Existing packages and subscriptions get no limit (null means unlimited).
     */
    public function up(): void
    {
        foreach (['packages', 'subscriptions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedInteger('max_patient_records')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['packages', 'subscriptions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('max_patient_records');
            });
        }
    }
};
