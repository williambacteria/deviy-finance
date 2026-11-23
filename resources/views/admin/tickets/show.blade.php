@extends('layouts.admin')

@section('title', '工单详情')
@section('page-title', '工单详情')

@section('content')
    <div class="page-head">
        <div><div class="flex items-center gap-3"><h1>{{ $ticket->subject }}</h1><x-status :status="$ticket->status" /></div><p>{{ $ticket->ticket_no }} · {{ $ticket->user?->name }} · {{ $ticket->created_at?->format('Y-m-d H:i') }}</p></div>
        <a class="btn btn-ghost" href="{{ route('admin.tickets.index') }}">返回列表</a>
    </div>

    @if ($errors->any())
        <div class="note warn" style="margin-bottom:16px">{{ $errors->first() }}</div>
    @endif

    <div class="grid-2">
        <div class="panel">
            <div class="panel-head"><h2>对话记录</h2><span class="meta">{{ $ticket->replies->count() }} 条</span></div>
            <div class="panel-body">
                @foreach ($ticket->replies as $reply)
                    <div style="padding:13px 0;border-bottom:1px solid var(--dt-e-f1f5f9,#f1f5f9)">
                        <div class="flex justify-between">
                            <div class="flex items-center gap-2">
                                <b class="small">{{ $reply->admin?->name ?? $reply->user?->name ?? '客户' }}</b>
                                @if ($reply->admin_id)<span class="badge info" style="height:20px;font-size:10px">客服</span>@endif
                                @if ($reply->is_internal)<span class="badge violet" style="height:20px;font-size:10px">内部</span>@endif
                            </div>
                            <span class="small muted">{{ $reply->created_at?->format('m-d H:i') }}</span>
                        </div>
                        <div class="small" style="margin-top:7px;line-height:1.8;color:var(--dt-t-334155,#334155);white-space:pre-line">{{ $reply->body }}</div>
                        @if ($reply->attachments->isNotEmpty())
                            <div class="flex flex-wrap gap-2" style="margin-top:10px">
                                @foreach ($reply->attachments as $attachment)
                                    @php($previewable = str_starts_with((string) $attachment->mime, 'image/') || $attachment->mime === 'application/pdf')
                                    @if ($previewable)
                                        <button class="chip" type="button" style="border:0;cursor:pointer"
                                            data-attachment-preview
                                            data-preview-url="{{ route('admin.tickets.attachments.preview', $attachment) }}"
                                            data-download-url="{{ route('admin.tickets.attachments.download', $attachment) }}"
                                            data-preview-type="{{ str_starts_with((string) $attachment->mime, 'image/') ? 'image' : 'pdf' }}"
                                            data-preview-name="{{ $attachment->original_name }}">{{ $attachment->original_name }} · {{ $attachment->humanSize() }}</button>
                                    @else
                                        <a class="chip" href="{{ route('admin.tickets.attachments.download', $attachment) }}">{{ $attachment->original_name }} · {{ $attachment->humanSize() }}</a>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach

                <form method="POST" action="{{ route('admin.tickets.reply', $ticket) }}" class="mt-4" enctype="multipart/form-data">
                    @csrf
                    <div class="form-grid">
                        <div class="form-field"><label class="form-label">处理状态</label><select class="select" name="status">@foreach (['open' => '待回复', 'pending' => '处理中', 'resolved' => '已解决', 'closed' => '已关闭'] as $value => $label)<option value="{{ $value }}" @selected($ticket->status === $value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="form-field" style="align-self:end"><label class="checkbox"><input type="checkbox" name="is_internal" value="1"> 内部备注（客户不可见）</label></div>
                        <div class="form-field full"><label class="form-label">回复内容</label><textarea class="textarea" name="body" rows="5" required></textarea></div>
                        <div class="form-field full"><label class="form-label">附件（可选，最多 3 个，每个最大 20MB）</label><input class="input" type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.zip,.txt" data-attachment-picker></div>
                    </div>
                    <button class="btn btn-primary mt-3" type="submit">发送回复</button>
                </form>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head"><h2>工单信息</h2></div>
            <div class="panel-body">
                <table class="spec-table">
                    <tr><td>客户</td><td><a href="{{ route('admin.customers.show', $ticket->user) }}" style="color:var(--dt-t-2563eb,#2563eb)">{{ $ticket->user?->name }}</a></td></tr>
                    <tr><td>邮箱</td><td>{{ $ticket->user?->email }}</td></tr>
                    <tr><td>部门</td><td>{{ ['technical' => '技术', 'billing' => '财务', 'sales' => '销售', 'other' => '其他'][$ticket->department] ?? $ticket->department }}</td></tr>
                    <tr><td>优先级</td><td>{{ ['urgent' => '紧急', 'high' => '高', 'normal' => '普通', 'low' => '低'][$ticket->priority] ?? $ticket->priority }}</td></tr>
                    <tr><td>处理人</td><td>{{ $ticket->assignedAdmin?->name ?? '待分配' }}</td></tr>
                    <tr><td>创建时间</td><td>{{ $ticket->created_at?->format('Y-m-d H:i') }}</td></tr>
                </table>
                <form method="POST" action="{{ route('admin.tickets.status', $ticket) }}" class="mt-4">
                    @csrf @method('PUT')
                    <div class="form-field"><label class="form-label">快捷改状态</label><select class="select" name="status" data-auto-submit>@foreach (['open' => '待回复', 'pending' => '处理中', 'resolved' => '已解决', 'closed' => '已关闭'] as $value => $label)<option value="{{ $value }}" @selected($ticket->status === $value)>{{ $label }}</option>@endforeach</select></div>
                </form>
                <div class="note info mt-3">附件存放在私有磁盘；客户只能下载自己工单的附件。</div>
            </div>
        </div>
    </div>
    <div class="modal" id="attachment-preview-modal" aria-hidden="true">
        <div class="modal-card" style="width:min(900px,100%)">
            <div class="modal-head">
                <h2 data-preview-title>附件预览</h2>
                <div class="flex items-center gap-2">
                    <a class="btn btn-ghost btn-sm" data-preview-download href="#" download>下载</a>
                    <button class="modal-close" type="button" data-preview-close>×</button>
                </div>
            </div>
            <div class="modal-body" data-preview-body style="min-height:240px;background:var(--dt-b-f8fafc,#f8fafc)"></div>
        </div>
    </div>
@endsection

@push('scripts')
    <script nonce="{{ $cspNonce ?? '' }}">
        (function () {
            const modal = document.getElementById('attachment-preview-modal');
            const body = modal.querySelector('[data-preview-body]');
            const title = modal.querySelector('[data-preview-title]');
            const download = modal.querySelector('[data-preview-download]');

            function close() {
                modal.classList.remove('open');
                modal.setAttribute('aria-hidden', 'true');
                body.innerHTML = '';
            }

            document.querySelectorAll('[data-attachment-preview]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const type = button.dataset.previewType;
                    const url = button.dataset.previewUrl;
                    title.textContent = button.dataset.previewName || '附件预览';
                    download.href = button.dataset.downloadUrl || '#';
                    body.innerHTML = '';

                    if (type === 'image') {
                        const image = document.createElement('img');
                        image.src = url;
                        image.alt = button.dataset.previewName || '';
                        image.style.cssText = 'display:block;max-width:100%;max-height:70vh;margin:0 auto;border-radius:8px';
                        body.appendChild(image);
                    } else {
                        const frame = document.createElement('iframe');
                        frame.src = url;
                        frame.title = button.dataset.previewName || 'PDF 预览';
                        frame.style.cssText = 'width:100%;height:70vh;border:0;background:#fff;border-radius:8px';
                        body.appendChild(frame);
                    }

                    modal.classList.add('open');
                    modal.setAttribute('aria-hidden', 'false');
                });
            });

            modal.querySelectorAll('[data-preview-close]').forEach(function (button) {
                button.addEventListener('click', close);
            });
            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    close();
                }
            });
        })();
    </script>
    <script src="{{ asset('assets/js/ticket-attachments.js') }}" defer></script>
@endpush
