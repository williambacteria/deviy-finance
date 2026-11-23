<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OpsAlert;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class OpsAlertController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status', 'open')->toString();
        $severity = $request->string('severity')->toString();

        $alerts = OpsAlert::query()
            ->with('assignedAdmin')
            ->when($status === 'open', fn ($query) => $query->whereIn('status', ['open', 'acked', 'silenced']))
            ->when($status === 'resolved', fn ($query) => $query->where('status', 'resolved'))
            ->when($severity !== '', fn ($query) => $query->where('severity', $severity))
            ->latest('last_seen_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.ops.index', [
            'alerts' => $alerts,
            'status' => $status,
            'severity' => $severity,
            'stats' => [
                'open' => OpsAlert::query()->whereIn('status', ['open', 'acked', 'silenced'])->count(),
                'critical' => OpsAlert::query()->whereIn('status', ['open', 'acked', 'silenced'])->where('severity', 'critical')->count(),
                'acked' => OpsAlert::query()->where('status', 'acked')->count(),
                'resolved' => OpsAlert::query()->where('status', 'resolved')->count(),
            ],
        ]);
    }

    public function scan(): RedirectResponse
    {
        Artisan::call('ops:scan');

        return back()->with('status', trim(Artisan::output()) ?: '扫描完成。');
    }

    public function acknowledge(Request $request, OpsAlert $alert, AuditLogger $audit): RedirectResponse
    {
        $alert->update([
            'status' => 'acked',
            'assigned_admin_id' => $request->user('admin')->id,
            'acknowledged_at' => now(),
        ]);
        $audit->record($request->user('admin'), 'ops.alert_acknowledged', '认领告警：'.$alert->title, $alert);

        return back()->with('status', '告警已认领。');
    }

    public function silence(Request $request, OpsAlert $alert, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'hours' => ['required', 'integer', 'min:1', 'max:168'],
        ]);

        $alert->update([
            'status' => 'silenced',
            'silenced_until' => now()->addHours((int) $data['hours']),
        ]);
        $audit->record($request->user('admin'), 'ops.alert_silenced', '静默告警 '.$data['hours'].' 小时：'.$alert->title, $alert);

        return back()->with('status', '告警已静默。');
    }

    public function resolve(Request $request, OpsAlert $alert, AuditLogger $audit): RedirectResponse
    {
        $alert->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);
        $audit->record($request->user('admin'), 'ops.alert_resolved', '解决告警：'.$alert->title, $alert);

        return back()->with('status', '告警已标记解决。');
    }
}
