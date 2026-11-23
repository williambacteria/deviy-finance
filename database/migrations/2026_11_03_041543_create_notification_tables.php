<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60);
            $table->string('channel', 20);
            $table->string('subject', 160)->nullable();
            $table->text('body');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['key', 'channel']);
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 20);
            $table->string('recipient', 160);
            $table->string('template_key', 60)->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('error')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['channel', 'status']);
        });

        $now = now();
        DB::table('notification_templates')->insert([
            [
                'key' => 'service_due',
                'channel' => 'email',
                'subject' => '【Deviy Cloud】实例 {{name}} 即将到期',
                'body' => "您好 {{customer}}：\n\n您的实例 {{name}}（{{service_no}}）将于 {{date}} 到期。\n为避免服务中断，请及时续费。\n\nDeviy Cloud",
                'enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'service_due',
                'channel' => 'sms',
                'subject' => null,
                'body' => '【{{sign}}】{{name}} 将于 {{date}} 到期，请及时续费。',
                'enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('settings')->insertOrIgnore([
            ['group_name' => 'notification', 'key' => 'email_enabled', 'value' => 'true', 'type' => 'boolean', 'created_at' => $now, 'updated_at' => $now],
            ['group_name' => 'notification', 'key' => 'sms_enabled', 'value' => 'false', 'type' => 'boolean', 'created_at' => $now, 'updated_at' => $now],
            ['group_name' => 'notification', 'key' => 'sms_sign', 'value' => 'Deviy', 'type' => 'string', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('notification_templates');
    }
};
