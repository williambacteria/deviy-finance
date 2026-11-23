<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\SettingChangeRequest;
use App\Services\AdminNotifier;
use App\Services\AuditLogger;
use App\Services\SettingChangeNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingChangeController extends Controller
{
    public function index(): View
    {
        return view('admin.setting-changes.index', [
            'pending' => SettingChangeRequest::query()->with('admin')->withCount('approvals')->where('status', 'pending')->latest()->get(),
            'history' => SettingChangeRequest::query()->with(['admin', 'reviewer'])->where('status', '!=', 'pending')->latest('reviewed_at')->paginate(20),
            'requiredApprovals' => $this->requiredApprovals(),
        ]);
    }

    public function approve(Request $request, SettingChangeRequest $change, AuditLogger $audit, SettingChangeNotifier $notifier, AdminNotifier $adminNotifier): RedirectResponse
    {
        abort_unless($change->status === 'pending', 404);
        $admin = $request->user('admin');

        if (! config('deviy.settings_requester_can_approve', true) && $change->admin_id === $admin->id) {
            return back()->withErrors(['approval' => '职责分离策略不允许提交人批准自己的变更。']);
        }

        if ($change->approvals()->where('admin_id', $admin->id)->exists()) {
            return back()->withErrors(['approval' => '同一管理员不能重复批准同一变更。']);
        }

        $change->approvals()->create(['admin_id' => $admin->id]);
        $approvals = $change->approvals()->count();
        $required = $this->requiredApprovals();

        if ($approvals < $required) {
            $audit->record($admin, 'settings.change_approval_recorded', '记录设置变更审批：'.$change->group_name.'.'.$change->key, null, [
                'approvals' => $approvals,
                'required' => $required,
            ]);
            $notifier->notify($change, '【Deviy Cloud】设置变更等待复核');
            $adminNotifier->notifyRole(
                'super_admin',
                'setting_change_review',
                '设置变更等待复核',
                $change->group_name.'.'.$change->key.' 已获 1 票，仍需复核。',
                ['change_id' => $change->id],
                'setting_change_review:'.$change->id,
                $admin->id,
            );

            return back()->with('status', "已记录审批（{$approvals}/{$required}），还需其他超级管理员复核。");
        }

        Setting::updateOrCreate(
            ['group_name' => $change->group_name, 'key' => $change->key],
            ['value' => $change->new_value],
        );

        $change->update([
            'status' => 'approved',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        $audit->record($admin, 'settings.change_approved', '批准设置变更：'.$change->group_name.'.'.$change->key, null, [
            'old' => $change->old_value,
            'new' => $change->new_value,
            'approvals' => $approvals,
        ]);

        return back()->with('status', '设置变更已批准并生效。');
    }

    public function reject(Request $request, SettingChangeRequest $change, AuditLogger $audit): RedirectResponse
    {
        abort_unless($change->status === 'pending', 404);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $change->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user('admin')->id,
            'reviewed_at' => now(),
            'reason' => $data['reason'] ?? null,
        ]);

        $audit->record($request->user('admin'), 'settings.change_rejected', '拒绝设置变更：'.$change->group_name.'.'.$change->key, null, [
            'reason' => $data['reason'] ?? null,
        ]);

        return back()->with('status', '设置变更已拒绝。');
    }

    private function requiredApprovals(): int
    {
        return config('deviy.settings_dual_approval', false) ? 2 : 1;
    }
}
