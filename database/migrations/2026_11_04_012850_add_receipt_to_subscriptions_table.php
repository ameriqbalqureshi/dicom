<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The payment methods purchases used to record, and how they are recorded now: as the name of the account paid.
     *
     * @var array<string, string>
     */
    private const METHOD_NAMES = [
        'bank_transfer' => 'Bank transfer',
        'jazzcash' => 'JazzCash',
        'easypaisa' => 'Easypaisa',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('receipt_path')->nullable()->after('payment_reference');
        });

        foreach (self::METHOD_NAMES as $method => $name) {
            DB::table('subscriptions')->where('payment_method', $method)->update(['payment_method' => $name]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::METHOD_NAMES as $method => $name) {
            DB::table('subscriptions')->where('payment_method', $name)->update(['payment_method' => $method]);
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('receipt_path');
        });
    }
};
