<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        \App\Services\Mail\MailSettings::ensureTemplates();
        $channel = $request->string('channel')->toString();

        $logs = NotificationLog::query()
            ->with('user')
            ->when($channel !== '', fn ($query) => $query->where('channel', $channel))
            ->latest()
            ->paginate(20);

        return view('admin.notifications.index', [
            'logs' => $logs,
            'templates' => NotificationTemplate::query()->orderBy('key')->orderBy('channel')->get(),
            'settings' => Setting::query()->where('group_name', 'notification')->pluck('value', 'key'),
            'channel' => $channel,
        ]);
    }

    public function editTemplate(NotificationTemplate $template): View
    {
        return view('admin.notifications.edit', ['template' => $template, 'mailDefinition' => \App\Services\Mail\MailPresentation::definitions()[$template->key] ?? null]);
    }

    public function previewTemplate(NotificationTemplate $template): \Illuminate\Http\Response
    {
        abort_unless($template->channel === 'email', 404);
        $sample = new \App\Models\User(['name' => '示例客户', 'email' => 'preview@example.com']);
        $rendered = \App\Services\Mail\MailPresentation::render($template, $sample, [
            'service_name' => '云服务器 · 示例资源', 'service_no' => 'SVC-PREVIEW-001',
            'date' => now()->addDays(7)->format('Y-m-d H:i').'（北京时间）', 'days' => '7',
            'due_description' => '距离到期还有 7 天', 'code' => '386219', 'expires_minutes' => '10',
            'order_no' => 'ORD-PREVIEW-001', 'payment_no' => 'PAY-PREVIEW-001',
            'amount' => '¥150.00', 'ticket_no' => 'TKT-PREVIEW-001', 'ticket_subject' => '资源使用咨询',
        ]);

        return response($rendered['body'])->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Cache-Control', 'no-store')->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; img-src https: data:; form-action 'none'; frame-ancestors 'self'");
    }

    public function updateTemplate(Request $request, NotificationTemplate $template, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
            'enabled' => ['nullable', 'boolean'],
        ]);

        $template->update([
            'subject' => $data['subject'] ?? null,
            'body' => $data['body'],
            'enabled' => (bool) ($data['enabled'] ?? false),
        ]);

        $audit->record($request->user('admin'), 'notification.template.updated', '更新通知模板：'.$template->key.'/'.$template->channel, $template);

        return redirect()->route('admin.notifications.index')->with('status', '通知模板已保存。');
    }

    public function retry(Request $request, NotificationLog $log, NotificationDispatcher $dispatcher, AuditLogger $audit): RedirectResponse
    {
        if ($log->status === 'sent') {
            return back()->with('status', '该通知已发送成功，不会重复发送。');
        }
        $log->update(['attempts' => 0, 'status' => 'pending', 'next_retry_at' => null]);
        $dispatcher->retry($log);
        $audit->record($request->user('admin'), 'notification.retried', '手动重试通知 #'.$log->id, $log);

        return back()->with('status', '已重新发送通知，请查看日志状态。');
    }
}
