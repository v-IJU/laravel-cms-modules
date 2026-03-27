<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_module_access', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('module_name');
            $table->boolean('is_enabled')->default(true);

            // Custom limits override plan defaults
            // e.g. {"max_posts": 50, "can_export": true, "max_users": 20}
            $table->json('custom_limits')->nullable();

            $table->text('notes')->nullable();
            $table->unsignedBigInteger('granted_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'module_name']);
            $table->foreign('tenant_id')
                  ->references('id')
                  ->on('tenants')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_module_access');
    }
};
