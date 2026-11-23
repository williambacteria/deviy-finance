<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setting_change_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('setting_change_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['setting_change_request_id', 'admin_id'], 'setting_change_approval_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setting_change_approvals');
    }
};
