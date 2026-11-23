<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_drift_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->unique()->constrained('cloud_nodes')->cascadeOnDelete();
            $table->string('status', 20)->default('unknown')->index();
            $table->unsignedInteger('expected_count')->default(0);
            $table->unsignedInteger('actual_count')->default(0);
            $table->json('missing')->nullable();
            $table->json('extra')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_drift_checks');
    }
};
