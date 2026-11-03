<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Everyone who already has an accepted share with a physician is allowed, so existing working
     * relationships keep working without a new acceptance.
     */
    public function up(): void
    {
        Schema::create('sender_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->string('status');
            $table->timestamps();

            $table->unique(['recipient_id', 'sender_id']);
            $table->index('sender_id');
        });

        $now = now();

        DB::table('sender_permissions')->insertUsing(
            ['recipient_id', 'sender_id', 'status', 'created_at', 'updated_at'],
            DB::table('study_shares')
                ->whereNotNull('accepted_at')
                ->whereNotNull('shared_by')
                ->whereColumn('user_id', '!=', 'shared_by')
                ->groupBy('user_id', 'shared_by')
                ->select('user_id', 'shared_by')
                ->selectRaw('?, ?, ?', ['allowed', $now, $now]),
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sender_permissions');
    }
};
