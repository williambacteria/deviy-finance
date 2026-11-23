<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upstream_products', function (Blueprint $table) {
            $table->id();
            $table->string('source', 32)->default('mofang');
            $table->string('upstream_id', 100);
            $table->string('name');
            $table->string('group_name', 80)->nullable();
            $table->string('region', 64)->nullable();
            $table->text('description')->nullable();
            $table->json('prices');
            $table->char('currency', 3)->default('CNY');
            $table->string('status', 24)->default('available');
            $table->decimal('markup_percent', 8, 2)->nullable();
            $table->foreignId('mapped_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->json('raw_payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['source', 'upstream_id']);
            $table->index(['source', 'status']);
            $table->index('mapped_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upstream_products');
    }
};
