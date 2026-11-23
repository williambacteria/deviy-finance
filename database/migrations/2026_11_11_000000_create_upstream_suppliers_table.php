<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upstream_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('contact', 120)->nullable();
            $table->text('note')->nullable();
            $table->string('driver', 32)->default('mofang');
            $table->string('base_url', 255);
            $table->string('username', 120);
            $table->text('api_key');
            $table->string('status', 24)->default('active');
            $table->string('last_test_status', 24)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'driver']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upstream_suppliers');
    }
};
