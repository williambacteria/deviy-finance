<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nat_port_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('external_ip', 45);
            $table->unsignedInteger('port_start');
            $table->unsignedInteger('port_end');
            $table->string('status', 20)->default('allocated')->index();
            $table->timestamps();
            $table->unique(['external_ip', 'port_start']);
            $table->index(['external_ip', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nat_port_allocations');
    }
};
