<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('key', 160)->unique();
            $table->string('source', 40)->index();
            $table->string('severity', 20)->default('warning')->index();
            $table->string('title', 180);
            $table->text('body');
            $table->json('data')->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->foreignId('assigned_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('silenced_until')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_alerts');
    }
};
