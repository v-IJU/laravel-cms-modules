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
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'onboard_status')) {
                $table->string('onboard_status')->default('trial')
                    ->after('status');
                // trial → pending → active → rejected
            }
            if (!Schema::hasColumn('tenants', 'trial_ends_at')) {
                $table->timestamp('trial_ends_at')->nullable()->after('onboard_status');
            }
            if (!Schema::hasColumn('tenants', 'onboard_notes')) {
                $table->text('onboard_notes')->nullable()->after('trial_ends_at');
            }
            if (!Schema::hasColumn('tenants', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('onboard_notes');
            }
            if (!Schema::hasColumn('tenants', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'onboard_status',
                'trial_ends_at',
                'onboard_notes',
                'approved_by',
                'approved_at',
            ]);
        });
    }
};
