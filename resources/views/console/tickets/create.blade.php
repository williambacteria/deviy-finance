@extends('layouts.console')

@section('title', '新建工单')
@section('page-title', '新建工单')

@section('content')
    <div class="page-head">
        <div><h1>新建工单</h1><p>描述越清楚，处理越快。涉及实例时请附上实例编号。</p></div>
        <a class="btn btn-ghost" href="{{ route('console.tickets.index') }}">返回列表</a>
    </div>

    @if ($errors->any())
        <div class="note warn" style="margin-bottom:16px">{{ $errors->first() }}</div>
    @endif

    <div class="panel" style="max-width:840px">
        <div class="panel-body">
            <form method="POST" action="{{ route('console.tickets.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="form-grid">
                    <div class="form-field">
                        <label class="form-label">部门 <span>*</span></label>
                        <select class="select" name="department">
                            <option value="technical">技术</option>
                            <option value="billing">财务</option>
                            <option value="sales">销售</option>
                            <option value="other">其他</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label class="form-label">优先级 <span>*</span></label>
                        <select class="select" name="priority">
                            <option value="normal">普通</option>
                            <option value="high">高</option>
                            <option value="urgent">紧急</option>
                            <option value="low">低</option>
                        </select>
                    </div>
                    <div class="form-field full">
                        <label class="form-label">主题 <span>*</span></label>
                        <input class="input" name="subject" value="{{ old('subject') }}" placeholder="例如：NAT 端口 20002 无法访问" required>
                    </div>
                    <div class="form-field full">
                        <label class="form-label">详细描述 <span>*</span></label>
                        <textarea class="textarea" name="body" rows="8" placeholder="请描述现象、发生时间、实例编号，以及你已尝试的操作。" required>{{ old('body') }}</textarea>
                    </div>
                    <div class="form-field full">
                        <label class="form-label">附件（可选，最多 3 个，每个最大 20MB）</label>
                        <input class="input" type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.zip,.txt" data-attachment-picker>
                        <div class="small muted" style="margin-top:6px">支持 JPG/PNG/WebP/PDF/ZIP/TXT，文件内容与扩展名必须一致。</div>
                    </div>
                </div>
                <button class="btn btn-primary mt-4" type="submit">提交工单</button>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/ticket-attachments.js') }}" defer></script>
@endpush
