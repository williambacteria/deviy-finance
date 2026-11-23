<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('title', 160);
            $table->text('body');
            $table->json('data')->nullable();
            $table->string('dedupe_key', 160);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['admin_id', 'dedupe_key']);
            $table->index(['admin_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notices');
    }
};
