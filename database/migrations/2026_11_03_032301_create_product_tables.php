<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('type', 32)->default('cloud_vm');
            $table->string('region', 64)->default('default');
            $table->string('short_description')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('stock')->default(999);
            $table->string('status', 24)->default('draft');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('upstream_source', 32)->default('local');
            $table->string('upstream_product_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'type']);
            $table->index(['product_group_id', 'sort_order']);
            $table->index(['upstream_source', 'upstream_product_id']);
        });

        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('billing_cycle', 24);
            $table->decimal('price', 20, 6);
            $table->decimal('setup_fee', 20, 6)->default(0);
            $table->decimal('cost_price', 20, 6)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'billing_cycle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_prices');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_groups');
    }
};
