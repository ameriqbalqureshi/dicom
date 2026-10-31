<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. A subscription is a purchase of a package: it records the payment and, once approved, copies
     * the package's limits and counts what the user has used of them. Existing users start on their role's trial.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->constrained()->restrictOnDelete();
            $table->string('status');
            $table->unsignedInteger('amount')->default(0);
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('max_uploads')->nullable();
            $table->unsignedInteger('max_storage_mb')->nullable();
            $table->unsignedInteger('max_reports')->nullable();
            $table->unsignedInteger('max_shares')->nullable();
            $table->unsignedInteger('max_guest_links')->nullable();
            $table->unsignedInteger('uploads_used')->default(0);
            $table->unsignedInteger('reports_used')->default(0);
            $table->unsignedInteger('shares_used')->default(0);
            $table->unsignedInteger('guest_links_used')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('status');
        });

        $trials = DB::table('packages')->where('is_trial', true)->get()->keyBy('role');

        DB::table('users')->where('role', '!=', 'admin')->orderBy('id')->each(function (object $user) use ($trials): void {
            $trial = $trials->get($user->role);

            if ($trial === null) {
                return;
            }

            DB::table('subscriptions')->insert([
                'user_id' => $user->id,
                'package_id' => $trial->id,
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => now()->addDays($trial->duration_days),
                'max_uploads' => $trial->max_uploads,
                'max_storage_mb' => $trial->max_storage_mb,
                'max_reports' => $trial->max_reports,
                'max_shares' => $trial->max_shares,
                'max_guest_links' => $trial->max_guest_links,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
