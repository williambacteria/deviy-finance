<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('type', 32)->default('purchase')->after('status')->index();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('service_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->string('billing_cycle', 20)->default('monthly')->after('status');
            $table->boolean('auto_renew')->default(false)->after('billing_cycle');
            $table->timestamp('last_renewed_at')->nullable()->after('expires_at');
            $table->timestamp('grace_ends_at')->nullable()->after('last_renewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['billing_cycle', 'auto_renew', 'last_renewed_at', 'grace_ends_at']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
