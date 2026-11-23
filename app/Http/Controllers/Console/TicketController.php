<?php

namespace App\Http\Controllers\Console;

use App\Filesystem\S3UrlGenerator;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketReply;
use App\Services\AuditLogger;
use App\Services\Security\AttachmentQuota;
use App\Services\Security\SecureUploadService;
use App\Support\DocumentNumber;
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
        $tickets = $request->user()->tickets()
            ->latest()
            ->paginate(12);

        return view('console.tickets.index', compact('tickets'));
    }

    public function create(): View
    {
        return view('console.tickets.create');
    }

    public function store(Request $request, SecureUploadService $uploads, AttachmentQuota $quota): RedirectResponse
    {
        $data = $request->validate([
            'department' => ['required', 'in:technical,billing,sales,other'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'subject' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['file', 'max:20480'],
            'attachment' => ['nullable', 'file', 'max:20480'],
        ]);

        $files = $this->uploadedFiles($request);
        $this->assertQuota(null, $files, $quota);
        $stored = $this->storeFiles($files, $uploads);

        try {
            $ticket = DB::transaction(function () use ($request, $data, $stored): Ticket {
                $ticket = Ticket::create([
                    'ticket_no' => DocumentNumber::make('TKT'),
                    'user_id' => $request->user()->id,
                    'department' => $data['department'],
                    'priority' => $data['priority'],
                    'subject' => $data['subject'],
                    'status' => 'open',
                    'last_reply_at' => now(),
                ]);

                $reply = TicketReply::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $request->user()->id,
                    'body' => $data['body'],
                ]);

                $this->persistAttachments($ticket, $reply, $stored, $request->user()->id, null);

                return $ticket;
            });
        } catch (Throwable $exception) {
            $this->deleteStoredFiles($stored);
            throw $exception;
        }

        $quota->warnIfNearLimit($request->user());

        return redirect()->route('console.tickets.show', $ticket)->with('status', '工单已提交。');
    }

    public function show(Request $request, Ticket $ticket): View
    {
        $this->authorizeTicket($request, $ticket);

        $ticket->load([
            'replies' => fn ($query) => $query->where('is_internal', false)->oldest(),
            'replies.attachments',
            'assignedAdmin',
        ]);

        return view('console.tickets.show', compact('ticket'));
    }

    public function reply(Request $request, Ticket $ticket, SecureUploadService $uploads, AttachmentQuota $quota): RedirectResponse
    {
        $this->authorizeTicket($request, $ticket);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['file', 'max:20480'],
            'attachment' => ['nullable', 'file', 'max:20480'],
        ]);

        $files = $this->uploadedFiles($request);
        $this->assertQuota($ticket, $files, $quota);
        $stored = $this->storeFiles($files, $uploads);

        try {
            DB::transaction(function () use ($request, $ticket, $data, $stored): void {
                $reply = TicketReply::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $request->user()->id,
                    'body' => $data['body'],
                ]);

                $this->persistAttachments($ticket, $reply, $stored, $request->user()->id, null);
                $ticket->update(['status' => 'open', 'last_reply_at' => now()]);
            });
        } catch (Throwable $exception) {
            $this->deleteStoredFiles($stored);
            throw $exception;
        }

        $quota->warnIfNearLimit($request->user());

        return back()->with('status', '回复已发送。');
    }

    public function download(Request $request, TicketAttachment $attachment, AuditLogger $audit, S3UrlGenerator $s3): StreamedResponse|RedirectResponse
    {
        abort_unless($attachment->ticket !== null, 404);
        $this->authorizeTicket($request, $attachment->ticket);

        $disk = $attachment->disk ?: 'local';
        abort_unless(Storage::disk($disk)->exists($attachment->path), 404);
        $audit->record(null, 'ticket.attachment_downloaded', '客户下载工单附件：'.$attachment->original_name, $attachment);

        if ($disk === 's3') {
            return redirect()->away($s3->url($attachment->path));
        }

        return Storage::disk($disk)->download($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime,
        ]);
    }

    public function preview(Request $request, TicketAttachment $attachment, S3UrlGenerator $s3): StreamedResponse|RedirectResponse
    {
        abort_unless($attachment->ticket !== null, 404);
        $this->authorizeTicket($request, $attachment->ticket);

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
     * @param  array<int, array{disk: string, path: string, name: string, mime: string, size: int}>  $stored
     */
    private function persistAttachments(Ticket $ticket, TicketReply $reply, array $stored, ?int $userId, ?int $adminId): void
    {
        foreach ($stored as $file) {
            TicketAttachment::create([
                'ticket_id' => $ticket->id,
                'ticket_reply_id' => $reply->id,
                'user_id' => $userId,
                'admin_id' => $adminId,
                'disk' => $file['disk'],
                'path' => $file['path'],
                'original_name' => $file['name'],
                'mime' => $file['mime'],
                'size' => $file['size'],
            ]);
        }
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    private function assertQuota(?Ticket $ticket, array $files, AttachmentQuota $quota): void
    {
        $ticketQuota = (int) config('deviy.ticket_attachment_quota', 50 * 1024 * 1024);
        $existing = $ticket?->attachments()->sum('size') ?? 0;
        $incoming = array_sum(array_map(fn (UploadedFile $file): int => (int) $file->getSize(), $files));

        $quota->assertCanUpload(request()->user(), $incoming, $ticket);

        if ($existing + $incoming > $ticketQuota) {
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

    private function authorizeTicket(Request $request, Ticket $ticket): void
    {
        abort_unless($ticket->user_id === $request->user()->id, 404);
    }
}
