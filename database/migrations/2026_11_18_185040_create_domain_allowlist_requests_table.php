<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_allowlist_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('region', 8);
            $table->string('domain', 253);
            $table->string('target_ip', 45);
            $table->string('status', 24)->default('processing');
            $table->string('icp_status', 32)->nullable();
            $table->string('provider_status', 40)->nullable();
            $table->string('response_code', 64)->nullable();
            $table->string('message', 1000)->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'region', 'domain', 'target_ip'], 'domain_allowlist_customer_target_unique');
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_allowlist_requests');
    }
};
