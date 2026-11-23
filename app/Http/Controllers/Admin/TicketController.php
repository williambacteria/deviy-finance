<?php

namespace App\Http\Controllers\Admin;

use App\Filesystem\S3UrlGenerator;
use App\Http\Controllers\Controller;
use App\Models\Notice;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketReply;
use App\Services\AuditLogger;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Security\AttachmentQuota;
use App\Services\Security\SecureUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TicketController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $priority = $request->string('priority')->toString();

        $tickets = Ticket::query()
            ->with(['user', 'assignedAdmin'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($priority !== '', fn ($query) => $query->where('priority', $priority))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.tickets.index', compact('tickets', 'status', 'priority'));
    }

    public function show(Ticket $ticket): View
    {
        $ticket->load(['user', 'assignedAdmin', 'replies.admin', 'replies.user', 'replies.attachments']);

        return view('admin.tickets.show', compact('ticket'));
    }

    public function reply(Request $request, Ticket $ticket, AuditLogger $audit, SecureUploadService $uploads, AttachmentQuota $quota): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'is_internal' => ['nullable', 'boolean'],
            'status' => ['required', 'in:open,pending,resolved,closed'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['file', 'max:20480'],
            'attachment' => ['nullable', 'file', 'max:20480'],
        ]);

        $files = $this->uploadedFiles($request);
        $this->assertQuota($ticket, $files, $quota);
        $stored = $this->storeFiles($files, $uploads);

        try {
            $reply = DB::transaction(function () use ($request, $ticket, $data, $stored): TicketReply {
                $reply = TicketReply::create([
                    'ticket_id' => $ticket->id,
                    'admin_id' => $request->user('admin')->id,
                    'body' => $data['body'],
                    'is_internal' => (bool) ($data['is_internal'] ?? false),
                ]);

                foreach ($stored as $file) {
                    TicketAttachment::create([
                        'ticket_id' => $ticket->id,
                        'ticket_reply_id' => $reply->id,
                        'admin_id' => $request->user('admin')->id,
                        'disk' => $file['disk'],
                        'path' => $file['path'],
                        'original_name' => $file['name'],
                        'mime' => $file['mime'],
                        'size' => $file['size'],
                    ]);
                }

                $ticket->update([
                    'status' => $data['status'],
                    'last_reply_at' => now(),
                    'assigned_admin_id' => $ticket->assigned_admin_id ?? $request->user('admin')->id,
                ]);

                return $reply;
            });
        } catch (Throwable $exception) {
            $this->deleteStoredFiles($stored);
            throw $exception;
        }

        if (! $reply->is_internal) {
            $notice = Notice::create([
                'user_id' => $ticket->user_id,
                'type' => 'ticket_reply',
                'title' => '工单有新回复',
                'body' => '工单 '.$ticket->ticket_no.' 有新回复，请登录控制台查看。',
                'data' => ['ticket_id' => $ticket->id],
                'dedupe_key' => 'ticket_reply:'.$reply->id,
            ]);
            app(NotificationDispatcher::class)->dispatch($notice);
        }

        $audit->record($request->user('admin'), 'ticket.replied', '回复工单：'.$ticket->ticket_no, $ticket);

        if ($ticket->user !== null) {
            $quota->warnIfNearLimit($ticket->user);
        }

        return back()->with('status', '回复已发送。');
    }

    public function status(Request $request, Ticket $ticket, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:open,pending,resolved,closed'],
        ]);

        $ticket->update(['status' => $data['status']]);
        $audit->record($request->user('admin'), 'ticket.status_changed', '工单状态改为 '.$data['status'], $ticket);

        return back()->with('status', '工单状态已更新。');
    }

    public function download(TicketAttachment $attachment, AuditLogger $audit, S3UrlGenerator $s3): StreamedResponse|RedirectResponse
    {
        abort_unless($attachment->ticket !== null, 404);

        $disk = $attachment->disk ?: 'local';
        abort_unless(Storage::disk($disk)->exists($attachment->path), 404);
        $audit->record(request()->user('admin'), 'ticket.attachment_downloaded', '管理员下载工单附件：'.$attachment->original_name, $attachment);

        if ($disk === 's3') {
            return redirect()->away($s3->url($attachment->path));
        }

        return Storage::disk($disk)->download($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime,
        ]);
    }

    public function preview(TicketAttachment $attachment, S3UrlGenerator $s3): StreamedResponse|RedirectResponse
    {
        abort_unless($attachment->ticket !== null, 404);

        $disk = $attachment->disk ?: 'local';
        abort_unless(Storage::disk($disk)->exists($attachment->path), 404);

        if ($disk === 's3') {
            return redirect()->away($s3->url($attachment->path));
        }

        return Storage::disk($disk)->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime,
            'Content-Disposition' => 'inline; filename="'.addslashes($attachment->original_name).'"',
        ]);
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, array{disk: string, path: string, name: string, mime: string, size: int}>
     */
    private function storeFiles(array $files, SecureUploadService $uploads): array
    {
        $stored = [];
        try {
            foreach ($files as $file) {
                $stored[] = $uploads->store($file, 'tickets');
            }
        } catch (Throwable $exception) {
            $this->deleteStoredFiles($stored);
            throw $exception;
        }

        return $stored;
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    private function assertQuota(Ticket $ticket, array $files, AttachmentQuota $quota): void
    {
        $ticketQuota = (int) config('deviy.ticket_attachment_quota', 50 * 1024 * 1024);
        $incoming = array_sum(array_map(fn (UploadedFile $file): int => (int) $file->getSize(), $files));

        if ($ticket->user !== null) {
            $quota->assertCanUpload($ticket->user, $incoming, $ticket);
        }

        if ($ticket->attachments()->sum('size') + $incoming > $ticketQuota) {
            throw ValidationException::withMessages([
                'attachments' => '单张工单附件总量不能超过 '.round($ticketQuota / 1024 / 1024).' MB。',
            ]);
        }
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function uploadedFiles(Request $request): array
    {
        $files = $request->file('attachments', []);
        $files = is_array($files) ? $files : [$files];
        if ($request->hasFile('attachment')) {
            $files[] = $request->file('attachment');
        }

        return array_values(array_filter($files, fn ($file): bool => $file instanceof UploadedFile));
    }

    /**
     * @param  array<int, array{disk: string, path: string, name: string, mime: string, size: int}>  $stored
     */
    private function deleteStoredFiles(array $stored): void
    {
        foreach ($stored as $file) {
            Storage::disk($file['disk'])->delete($file['path']);
        }
    }
}
