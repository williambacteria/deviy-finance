<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_metric_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->timestamp('recorded_at');
            $table->decimal('cpu_percent', 8, 2)->default(0);
            $table->decimal('memory_used_gb', 10, 3)->default(0);
            $table->decimal('disk_read_mbps', 10, 3)->default(0);
            $table->decimal('disk_write_mbps', 10, 3)->default(0);
            $table->decimal('net_rx_mbps', 10, 3)->default(0);
            $table->decimal('net_tx_mbps', 10, 3)->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['service_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_metric_samples');
    }
};
