<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cloud_nodes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('provider', 24);
            $table->string('host');
            $table->unsignedInteger('port')->default(8006);
            $table->string('region', 64)->default('default');
            $table->string('status', 24)->default('offline');
            $table->string('agent_version', 24)->nullable();
            $table->json('capacity')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->boolean('is_maintenance')->default(false);
            $table->timestamps();

            $table->index(['provider', 'status']);
            $table->index('region');
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('service_no', 40)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('node_id')->nullable()->constrained('cloud_nodes')->nullOnDelete();
            $table->string('name');
            $table->string('status', 24)->default('pending');
            $table->string('region', 64)->default('default');
            $table->string('ip_address', 45)->nullable();
            $table->string('nat_ip', 45)->nullable();
            $table->unsignedInteger('nat_port_start')->nullable();
            $table->unsignedInteger('nat_port_end')->nullable();
            $table->unsignedInteger('bandwidth_mbps')->default(10);
            $table->string('os', 80)->nullable();
            $table->json('specs')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('next_due_at')->nullable();
            $table->string('provision_status', 24)->default('pending');
            $table->string('external_ref')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'expires_at']);
            $table->index(['node_id', 'status']);
        });

        Schema::create('ip_pools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->nullable()->constrained('cloud_nodes')->nullOnDelete();
            $table->string('name');
            $table->string('cidr', 64);
            $table->string('type', 24)->default('dedicated');
            $table->string('gateway', 45)->nullable();
            $table->string('vlan', 32)->nullable();
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('used')->default(0);
            $table->timestamps();
        });

        Schema::create('ip_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pool_id')->constrained('ip_pools')->cascadeOnDelete();
            $table->string('address', 45);
            $table->string('status', 24)->default('available');
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->unique(['pool_id', 'address']);
            $table->index(['status', 'address']);
        });

        Schema::create('nat_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('external_ip', 45);
            $table->unsignedInteger('external_port');
            $table->string('internal_ip', 45);
            $table->unsignedInteger('internal_port');
            $table->string('protocol', 8)->default('tcp');
            $table->string('status', 24)->default('pending');
            $table->timestamps();

            $table->unique(['external_ip', 'external_port', 'protocol']);
            $table->index(['service_id', 'status']);
        });

        Schema::create('provision_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('operation', 40);
            $table->string('status', 24)->default('queued');
            $table->json('payload')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['service_id', 'operation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provision_tasks');
        Schema::dropIfExists('nat_mappings');
        Schema::dropIfExists('ip_addresses');
        Schema::dropIfExists('ip_pools');
        Schema::dropIfExists('services');
        Schema::dropIfExists('cloud_nodes');
    }
};
