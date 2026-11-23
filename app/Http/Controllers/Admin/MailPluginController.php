<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Mail\MailSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MailPluginController extends Controller
{
    public function index(): View
    {
        MailSettings::ensureTemplates();
        $plugin = MailSettings::plugin();

        return view('admin.plugins.mail', [
            'plugin' => $plugin,
            'config' => MailSettings::configuration(),
            'mailDefinitions' => \App\Services\Mail\MailPresentation::definitions(),
            'templates' => NotificationTemplate::query()
                ->where('channel', 'email')
                ->whereIn('key', array_keys(\App\Services\Mail\MailPresentation::definitions()))
                ->orderBy('key')
                ->get(),
        ]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'host' => ['required', 'string', 'max:190'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['required', 'in:ssl,tls,starttls,none'],
            'username' => ['required', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'max:255'],
            'from_address' => ['required', 'email', 'max:190'],
            'from_name' => ['required', 'string', 'max:120'],
        ]);

        $plugin = MailSettings::plugin();
        if (empty($data['password'])) {
            $data['password'] = (string) (MailSettings::configuration()['password'] ?? '');
        }
        if ($data['password'] === '') {
            throw \Illuminate\Validation\ValidationException::withMessages(['password' => '首次配置需要填写 SMTP 密码。']);
        }
        $plugin->update([
            'config' => $data,
            'is_enabled' => $request->boolean('is_enabled'),
        ]);
        $audit->record($request->user('admin'), 'plugin.mail.configured', '更新阿里云 SMTP 邮件配置', $plugin);

        return back()->with('status', '邮件配置已保存。建议发送一封测试邮件确认。');
    }

    public function test(Request $request): RedirectResponse
    {
        $data = $request->validate(['test_email' => ['required', 'email']]);
        $recipient = new User(['name' => '管理员', 'email' => $data['test_email']]);
        $sent = MailSettings::sendTemplate('email_test', $recipient, [
            'sign' => MailSettings::configuration()['from_name'],
        ], $data['test_email']);

        return $sent
            ? back()->with('status', '测试邮件已发送至 '.$data['test_email'])
            : back()->withErrors(['test_email' => '测试邮件发送失败，请检查 SMTP 配置和发件地址权限。']);
    }
}
