<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TicketAttachment;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AttachmentStorageController extends Controller
{
    public function index(): View
    {
        $rows = TicketAttachment::query()
            ->selectRaw('disk, COUNT(*) as files, COALESCE(SUM(size), 0) as bytes')
            ->groupBy('disk')
            ->orderBy('disk')
            ->get();

        $topUsers = DB::table('ticket_attachments')
            ->join('tickets', 'tickets.id', '=', 'ticket_attachments.ticket_id')
            ->join('users', 'users.id', '=', 'tickets.user_id')
            ->select('users.id', 'users.name', 'users.email')
            ->selectRaw('COUNT(ticket_attachments.id) as files, COALESCE(SUM(ticket_attachments.size), 0) as bytes')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('bytes')
            ->limit(5)
            ->get();

        return view('admin.storage.index', [
            'rows' => $rows,
            'topUsers' => $topUsers,
            'totalFiles' => (int) $rows->sum('files'),
            'totalBytes' => (int) $rows->sum('bytes'),
            'quota' => (int) config('deviy.ticket_attachment_quota', 50 * 1024 * 1024),
        ]);
    }
}
