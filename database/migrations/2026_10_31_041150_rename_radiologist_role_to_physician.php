<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('physician')->change();
        });

        DB::table('users')->where('role', 'radiologist')->update(['role' => 'physician']);
    }

    /**
     * Reverse the migrations. Patients have no earlier equivalent and become radiologists too.
     */
    public function down(): void
    {
        DB::table('users')->whereIn('role', ['physician', 'patient'])->update(['role' => 'radiologist']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('radiologist')->change();
        });
    }
};
