<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('mail:test {--to=}')]
#[Description('发送一封 SMTP 测试邮件')]
class MailTestCommand extends Command
{
    public function handle(): int
    {
        $to = (string) ($this->option('to') ?: config('mail.from.address') ?: 'support@deviy.cn');
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error('收件人邮箱无效：'.$to);

            return self::FAILURE;
        }

        try {
            Mail::raw(
                'Deviy Cloud SMTP 测试邮件。发送时间：'.now()->toDateTimeString(),
                function ($message) use ($to): void {
                    $message->to($to)->subject('【Deviy Cloud】SMTP 测试');
                },
            );
        } catch (\Throwable $exception) {
            $this->error('SMTP 发送失败：'.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('测试邮件已提交：'.$to);

        return self::SUCCESS;
    }
}
