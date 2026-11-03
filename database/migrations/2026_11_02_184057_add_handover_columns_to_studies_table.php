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
        Schema::table('studies', function (Blueprint $table) {
            $table->foreignId('released_by')->nullable()->after('uploaded_by')->constrained('users')->nullOnDelete();
            $table->timestamp('handover_ends_at')->nullable()->after('expires_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('studies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('released_by');
            $table->dropIndex(['handover_ends_at']);
            $table->dropColumn('handover_ends_at');
        });
    }
};
