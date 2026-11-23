<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->foreignId('notice_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->unsignedInteger('attempts')->default(0)->after('status');
            $table->timestamp('next_retry_at')->nullable()->after('attempts')->index();
        });

        DB::table('settings')->insertOrIgnore([
            'group_name' => 'notification',
            'key' => 'rate_limit_per_hour',
            'value' => '20',
            'type' => 'integer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('notice_id');
            $table->dropColumn(['attempts', 'next_retry_at']);
        });
    }
};
