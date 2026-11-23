@extends('layouts.admin')

@section('title', '隔离文件')
@section('page-title', '隔离文件')

@section('content')
    <div class="page-head">
        <div><h1>隔离文件</h1><p>病毒扫描未通过的文件样本。仅保留元数据与私有文件，不提供在线下载。</p></div>
        <a class="btn btn-ghost" href="{{ route('admin.storage.index') }}">附件存储</a>
    </div>

    <div class="panel">
        <div class="panel-head"><h2>隔离记录</h2><span class="meta">{{ $files->total() }} 条</span></div>
        <div class="panel-body tight">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>时间</th><th>原始文件</th><th>上传者</th><th>大小</th><th>扫描器 / 原因</th><th>IP</th><th>操作</th></tr></thead>
                    <tbody>
                        @forelse ($files as $file)
                            <tr>
                                <td class="small muted">{{ $file->created_at?->format('Y-m-d H:i:s') }}</td>
                                <td class="small">{{ $file->original_name }}</td>
                                <td class="small">{{ $file->user?->name ?? '—' }}</td>
                                <td class="small">{{ $file->humanSize() }}</td>
                                <td class="small"><span class="chip">{{ $file->scanner }}</span> {{ $file->reason }}</td>
                                <td class="mono small muted">{{ $file->ip_address }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.quarantine.destroy', $file) }}" data-confirm="确认删除隔离文件？">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-danger btn-sm" type="submit">删除</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="table-empty">暂无隔离文件。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="panel-footer">{{ $files->links('partials.pagination') }}</div>
        </div>
    </div>
@endsection
