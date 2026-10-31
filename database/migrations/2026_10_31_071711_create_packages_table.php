<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations, creating a trial package for each role that can sign up. Administrators can change them.
     * A null limit means unlimited.
     */
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('role');
            $table->unsignedInteger('price')->default(0);
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->unsignedInteger('max_uploads')->nullable();
            $table->unsignedInteger('max_storage_mb')->nullable();
            $table->unsignedInteger('max_reports')->nullable();
            $table->unsignedInteger('max_shares')->nullable();
            $table->unsignedInteger('max_guest_links')->nullable();
            $table->boolean('is_trial')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['role', 'is_active']);
        });

        $trialLimits = [
            'physician' => ['max_uploads' => 5, 'max_storage_mb' => 1024, 'max_reports' => 3, 'max_shares' => 5, 'max_guest_links' => 3],
            'technician' => ['max_uploads' => 5, 'max_storage_mb' => 1024, 'max_reports' => 0, 'max_shares' => 5, 'max_guest_links' => 0],
            'patient' => ['max_uploads' => 1, 'max_storage_mb' => 500, 'max_reports' => 0, 'max_shares' => 2, 'max_guest_links' => 1],
        ];

        foreach ($trialLimits as $role => $limits) {
            DB::table('packages')->insert([
                'name' => 'Free trial',
                'description' => 'Try the service before choosing a package.',
                'role' => $role,
                'price' => 0,
                'duration_days' => 14,
                'is_trial' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
                ...$limits,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
