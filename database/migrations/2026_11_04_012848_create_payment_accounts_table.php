<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. The accounts buyers pay into move from .env to the database, so accounts already set up in
     * .env are brought across.
     */
    public function up(): void
    {
        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('type');
            $table->string('account_title')->nullable();
            $table->string('account_number')->nullable();
            $table->string('iban')->nullable();
            $table->string('branch')->nullable();
            $table->text('instructions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $configured = config('billing.payment_accounts', []);

        $accounts = [
            [
                'name' => ($configured['bank_transfer']['Bank'] ?? null) ?: 'Bank transfer',
                'type' => 'bank',
                'account_title' => $configured['bank_transfer']['Account title'] ?? null,
                'account_number' => $configured['bank_transfer']['Account number'] ?? null,
                'iban' => $configured['bank_transfer']['IBAN'] ?? null,
                'branch' => $configured['bank_transfer']['Branch'] ?? null,
            ],
            [
                'name' => 'JazzCash',
                'type' => 'mobile_wallet',
                'account_title' => $configured['jazzcash']['Account title'] ?? null,
                'account_number' => $configured['jazzcash']['Mobile number'] ?? null,
                'iban' => null,
                'branch' => null,
            ],
            [
                'name' => 'Easypaisa',
                'type' => 'mobile_wallet',
                'account_title' => $configured['easypaisa']['Account title'] ?? null,
                'account_number' => $configured['easypaisa']['Mobile number'] ?? null,
                'iban' => null,
                'branch' => null,
            ],
        ];

        foreach ($accounts as $sortOrder => $account) {
            if (blank($account['account_number']) && blank($account['iban'])) {
                continue;
            }

            DB::table('payment_accounts')->insert([
                ...$account,
                'is_active' => true,
                'sort_order' => $sortOrder,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_accounts');
    }
};
