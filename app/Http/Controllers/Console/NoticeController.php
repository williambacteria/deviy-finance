<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NoticeController extends Controller
{
    public function index(Request $request): View
    {
        $notices = $request->user()->notices()->latest()->paginate(15);

        return view('console.notices.index', compact('notices'));
    }

    public function read(Request $request, Notice $notice): RedirectResponse
    {
        abort_unless($notice->user_id === $request->user()->id, 404);

        $notice->update(['read_at' => now()]);

        return back()->with('status', '通知已标记为已读。');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->notices()->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('status', '全部通知已标记为已读。');
    }
}
