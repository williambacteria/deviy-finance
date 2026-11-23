<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('real_name')->nullable()->after('real_name_status');
            $table->text('real_name_id')->nullable()->after('real_name');
            $table->text('real_name_phone')->nullable()->after('real_name_id');
            $table->timestamp('real_name_submitted_at')->nullable()->after('real_name_phone');
            $table->timestamp('real_name_verified_at')->nullable()->after('real_name_submitted_at');
            $table->string('real_name_reject_reason', 255)->nullable()->after('real_name_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'real_name',
                'real_name_id',
                'real_name_phone',
                'real_name_submitted_at',
                'real_name_verified_at',
                'real_name_reject_reason',
            ]);
        });
    }
};
