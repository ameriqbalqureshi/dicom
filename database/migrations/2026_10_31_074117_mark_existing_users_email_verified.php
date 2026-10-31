<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations. Email verification is now required; accounts created before it were added by an
     * administrator, so their addresses are trusted.
     */
    public function up(): void
    {
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    /**
     * Reverse the migrations. Which accounts were marked verified here is not recorded, so nothing is undone.
     */
    public function down(): void
    {
        //
    }
};
