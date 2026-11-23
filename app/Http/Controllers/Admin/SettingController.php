<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\SettingChangeRequest;
use App\Services\AuditLogger;
use App\Services\AdminNotifier;
use App\Services\SettingChangeNotifier;
use App\Support\AdminSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingController extends Controller
{
    /** @return array<string, array<string, mixed>> */
    public static function sections(): array
    {
        return [
            'site' => ['title' => '站点与联系', 'category' => '基础配置', 'icon' => 'globe', 'group' => 'site'],
            'billing' => ['title' => '计费规则', 'category' => '基础配置', 'icon' => 'receipt', 'group' => 'billing'],
            'payment' => ['title' => '支付渠道', 'category' => '基础配置', 'icon' => 'wallet', 'controller' => PaymentSettingsController::class],
            'mail' => ['title' => '邮件通道', 'category' => '通知服务', 'icon' => 'mail', 'controller' => MailPluginController::class],
            'notification' => ['title' => '通知与模板', 'category' => '通知服务', 'icon' => 'bell', 'group' => 'notification', 'controller' => NotificationController::class],
            'plugins' => ['title' => '插件与接入', 'category' => '资源与存储', 'icon' => 'plug', 'controller' => PluginController::class],
            'controller' => ['title' => '主控资源', 'category' => '资源与存储', 'icon' => 'network', 'controller' => ControllerIntegrationController::class],
            'attachment' => ['title' => '附件与存储', 'category' => '资源与存储', 'icon' => 'package', 'group' => 'attachment', 'controller' => AttachmentStorageController::class],
            'quarantine' => ['title' => '文件隔离', 'category' => '资源与存储', 'icon' => 'shield', 'controller' => QuarantineController::class],
            'security' => ['title' => '管理账户安全', 'category' => '安全与记录', 'icon' => 'lock', 'controller' => SecurityController::class, 'method' => 'show'],
            'audit' => ['title' => '操作日志', 'category' => '安全与记录', 'icon' => 'activity', 'controller' => AuditController::class],
            'changes' => ['title' => '配置变更审批', 'category' => '安全与记录', 'icon' => 'check', 'controller' => SettingChangeController::class],
            'system' => ['title' => '运行信息', 'category' => '安全与记录', 'icon' => 'server'],
        ];
    }

    public function index(Request $request): View
    {
        $section = $request->string('section', $request->string('group', 'site')->toString())->toString();
        $sections = self::sections();
        abort_unless(isset($sections[$section]), 404);
        $definition = $sections[$section];
        $settingsPartial = null;
        $settingsData = [];
        if (isset($definition['controller'])) {
            $content = app()->call([app($definition['controller']), $definition['method'] ?? 'index']);
            $settingsPartial = 'admin.merged.'.str_replace('.', '-', $content->getName());
            $settingsData = $content->getData();
        }
        $group = $definition['group'] ?? null;
        $fields = $group ? AdminSettings::fields()[$group] : [];
        $values = $group ? Setting::query()->where('group_name', $group)->pluck('value', 'key')->all() : [];
        $runtime = ['官网域名' => config('app.url'), '结算币种' => '人民币 CNY', 'PHP 版本' => PHP_VERSION, '应用时区' => config('app.timezone'), '文件存储' => config('deviy.upload_disk'), '单文件限制' => round(config('deviy.upload_max_bytes') / 1024 / 1024).' MB', '数据库类型' => config('database.default'), '邮件通道' => \App\Services\Mail\MailSettings::plugin()->is_enabled ? '已启用' : '未启用', '下游 API 兼容' => class_exists(\App\Services\Legacy\LegacyFinanceBridge::class) ? '已接入（保留历史商品 ID）' : '未安装'];

        return view('admin.settings.index', compact('section', 'sections', 'definition', 'group', 'fields', 'values', 'settingsPartial', 'settingsData', 'runtime'));
    }

    public function update(Request $request, AuditLogger $audit, SettingChangeNotifier $notifier, AdminNotifier $adminNotifier): RedirectResponse
    {
        $validated = $request->validate(['group' => ['required', 'in:site,billing,notification,attachment'], 'settings' => ['required', 'array']]);
        $group = $validated['group'];
        $fields = AdminSettings::fields()[$group];
        foreach (array_keys($validated['settings']) as $key) {
            if (! isset($fields[$key])) {
                throw ValidationException::withMessages(['settings' => '包含不支持的设置项，未保存。']);
            }
        }
        $rules = [];
        $names = [];
        foreach ($fields as $key => $field) {
            $rules['settings.'.$key] = $field['rules'];
            $names['settings.'.$key] = $field['label'];
        }
        $data = $request->validate($rules, [], $names)['settings'];
        if ($group === 'notification' && ($data['sms_enabled'] ?? 'false') === 'true' && config('services.sms.driver') !== 'http') {
            throw ValidationException::withMessages(['settings.sms_enabled' => '尚未配置短信网关，无法开启真实短信发送。']);
        }
        $admin = $request->user('admin');
        $before = [];
        $applied = [];
        $pending = [];
        \Illuminate\Support\Facades\DB::transaction(function () use ($data, $group, $fields, $admin, &$before, &$applied, &$pending): void {
            foreach ($data as $key => $value) {
                $value = is_string($value) ? trim($value) : (string) $value;
                $old = Setting::query()->where('group_name', $group)->where('key', $key)->value('value');
                if ((string) $old === (string) $value) {
                    continue;
                }
                if ($group === 'attachment' && str_starts_with($key, 'quota_') && $old !== null && abs((int) $value - (int) $old) / max(1, (int) $old) * 100 > (int) config('deviy.settings_approval_threshold', 25)) {
                    $pending[] = SettingChangeRequest::firstOrCreate(['admin_id' => $admin->id, 'group_name' => $group, 'key' => $key, 'new_value' => $value, 'status' => 'pending'], ['old_value' => $old]);
                    continue;
                }
                $before[$key] = $old;
                $applied[$key] = $value;
                Setting::updateOrCreate(['group_name' => $group, 'key' => $key], ['value' => $value, 'type' => ($fields[$key]['type'] ?? '') === 'number' ? 'integer' : (($fields[$key]['type'] ?? '') === 'boolean' ? 'boolean' : 'string')]);
            }
        });
        if ($applied !== []) {
            $audit->record($admin, 'settings.updated', '更新设置：'.self::sections()[$group]['title'], null, ['before' => $before, 'after' => $applied]);
        }
        foreach ($pending as $change) {
            $notifier->notify($change, '配置变更待审批');
            $adminNotifier->notifyRole('super_admin', 'setting_change', '附件额度变更待审批', $fields[$change->key]['label'], ['change_id' => $change->id], 'setting_change:'.$change->id);
        }

        return redirect()->route('admin.settings.index', ['section' => $group])->with('status', $pending !== [] ? '常规设置已保存；附件额度变更已提交审批。' : '设置已保存，后续请求与任务将使用新配置。');
    }
}
