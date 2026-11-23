<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->time('quiet_hours_start')->nullable()->after('phone');
            $table->time('quiet_hours_end')->nullable()->after('quiet_hours_start');
        });

        $now = now();
        $templates = [
            ['ticket_reply', 'email', '【Deviy Cloud】工单有新回复', "您好 {{customer}}：\n\n您的工单有新回复，请登录控制台查看。\n\nDeviy Cloud", $now, $now],
            ['ticket_reply', 'sms', null, '【{{sign}}】您的工单有新回复，请登录控制台查看。', $now, $now],
            ['payment_succeeded', 'email', '【Deviy Cloud】支付成功', "您好 {{customer}}：\n\n订单 {{name}} 已支付成功，服务正在开通。\n\nDeviy Cloud", $now, $now],
            ['payment_succeeded', 'sms', null, '【{{sign}}】订单 {{name}} 支付成功。', $now, $now],
            ['service_suspended', 'email', '【Deviy Cloud】实例已停机', "您好 {{customer}}：\n\n实例 {{name}} 因到期未续费已停机，请及时续费恢复。\n\nDeviy Cloud", $now, $now],
            ['service_suspended', 'sms', null, '【{{sign}}】实例 {{name}} 已停机，请及时续费。', $now, $now],
        ];

        DB::table('notification_templates')->insertOrIgnore(array_map(fn (array $row): array => [
            'key' => $row[0],
            'channel' => $row[1],
            'subject' => $row[2],
            'body' => $row[3],
            'enabled' => true,
            'created_at' => $row[4],
            'updated_at' => $row[5],
        ], $templates));
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['quiet_hours_start', 'quiet_hours_end']);
        });
    }
};
