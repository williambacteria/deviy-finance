<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $templates = [
            [
                'key' => 'attachment_quota',
                'channel' => 'email',
                'subject' => '【Deviy Cloud】附件容量接近上限',
                'body' => "你好 {{customer}}：\n\n你的工单附件已使用 {{percent}}%（{{used}} / {{limit}}）。\n请清理历史附件或联系客服提升配额。\n\nDeviy Cloud",
            ],
            [
                'key' => 'attachment_quota',
                'channel' => 'sms',
                'subject' => null,
                'body' => '【{{sign}}】附件已使用 {{percent}}%，请及时清理或联系客服。',
            ],
        ];

        DB::table('notification_templates')->insertOrIgnore(array_map(fn (array $row): array => [
            'key' => $row['key'],
            'channel' => $row['channel'],
            'subject' => $row['subject'],
            'body' => $row['body'],
            'enabled' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $templates));
    }

    public function down(): void
    {
        DB::table('notification_templates')->where('key', 'attachment_quota')->delete();
    }
};
