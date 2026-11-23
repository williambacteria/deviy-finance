<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\OpsOncallShift;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OncallController extends Controller
{
    public function index(): View
    {
        return view('admin.oncall.index', [
            'shifts' => OpsOncallShift::query()->with('admin')->orderBy('starts_at')->get(),
            'current' => [
                1 => OpsOncallShift::query()->with('admin')->current(1)->latest('starts_at')->first(),
                2 => OpsOncallShift::query()->with('admin')->current(2)->latest('starts_at')->first(),
            ],
            'admins' => Admin::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'admin_id' => ['required', 'exists:admins,id'],
            'level' => ['required', 'integer', 'in:1,2'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $overlap = OpsOncallShift::query()
            ->where('level', $data['level'])
            ->where('starts_at', '<', $data['ends_at'])
            ->where('ends_at', '>', $data['starts_at'])
            ->exists();

        if ($overlap) {
            return back()->withErrors(['starts_at' => '该级别在此时间段已有值班安排。'])->withInput();
        }

        $shift = OpsOncallShift::create($data);
        $audit->record($request->user('admin'), 'ops.oncall_created', '新增值班安排', $shift);

        return back()->with('status', '值班安排已添加。');
    }

    public function destroy(Request $request, OpsOncallShift $shift, AuditLogger $audit): RedirectResponse
    {
        $shift->delete();
        $audit->record($request->user('admin'), 'ops.oncall_deleted', '删除值班安排', null, ['shift_id' => $shift->id]);

        return back()->with('status', '值班安排已删除。');
    }
}
