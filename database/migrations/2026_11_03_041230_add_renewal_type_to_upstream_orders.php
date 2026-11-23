<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('upstream_orders', function (Blueprint $table) {
            $table->index('service_id', 'upstream_orders_service_id_index');
        });

        Schema::table('upstream_orders', function (Blueprint $table) {
            $table->dropUnique(['service_id']);
        });

        Schema::table('upstream_orders', function (Blueprint $table) {
            $table->string('type', 32)->default('purchase')->after('source')->index();
            $table->string('billing_cycle', 20)->nullable()->after('amount');
            $table->timestamp('upstream_expires_at')->nullable()->after('billing_cycle');
        });
    }

    public function down(): void
    {
        Schema::table('upstream_orders', function (Blueprint $table) {
            $table->dropColumn(['type', 'billing_cycle', 'upstream_expires_at']);
            $table->unique('service_id');
            $table->dropIndex('upstream_orders_service_id_index');
        });
    }
};
