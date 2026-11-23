<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quarantine_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_name', 160);
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->string('disk', 30)->default('local');
            $table->string('path');
            $table->string('scanner', 40);
            $table->string('reason', 200);
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->unique('path');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quarantine_files');
    }
};
