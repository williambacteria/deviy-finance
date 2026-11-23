<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InboxController extends Controller
{
    public function index(Request $request): View
    {
        $admin = $request->user('admin');
        $notices = $admin->adminNotices()->latest()->paginate(20);

        return view('admin.inbox.index', [
            'notices' => $notices,
            'unread' => $admin->adminNotices()->whereNull('read_at')->count(),
        ]);
    }

    public function read(Request $request, AdminNotice $notice): RedirectResponse
    {
        abort_unless($notice->admin_id === $request->user('admin')->id, 404);
        $notice->update(['read_at' => $notice->read_at ?? now()]);

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user('admin')->adminNotices()->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('status', '全部消息已标记为已读。');
    }
}
