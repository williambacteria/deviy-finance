<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setting_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained()->nullOnDelete();
            $table->string('group_name', 40);
            $table->string('key', 120);
            $table->text('old_value')->nullable();
            $table->text('new_value');
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reason', 500)->nullable();
            $table->timestamps();
            $table->index(['group_name', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setting_change_requests');
    }
};
