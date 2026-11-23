@extends('layouts.admin')

@section('title', '公告管理')
@section('page-title', '公告管理')

@section('content')
    <div class="page-head">
        <div><h1>公告管理</h1><p>站点公告、新闻与帮助内容，支持定时发布。</p></div>
        <a class="btn btn-primary" href="{{ route('admin.announcements.create') }}"><x-icon name="plus" :size="15" /> 新建内容</a>
    </div>

    <div class="panel">
        <div class="panel-body tight">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>标题</th><th>类型</th><th>状态</th><th>发布时间</th><th>更新时间</th><th class="actions">操作</th></tr></thead>
                    <tbody>
                        @forelse ($announcements as $announcement)
                            <tr>
                                <td><span class="strong">{{ $announcement->title }}</span><div class="sub">/news/{{ $announcement->slug }}</div></td>
                                <td><span class="chip">{{ ['announcement' => '公告', 'news' => '新闻', 'help' => '帮助'][$announcement->type] ?? $announcement->type }}</span></td>
                                <td><x-status :status="$announcement->is_published ? 'active' : 'pending'" :label="$announcement->is_published ? '已发布' : '草稿'" /></td>
                                <td class="small muted">{{ $announcement->published_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td class="small muted">{{ $announcement->updated_at?->format('Y-m-d H:i') }}</td>
                                <td class="actions">
                                    <a class="btn btn-ghost btn-sm" href="{{ route('admin.announcements.edit', $announcement) }}">编辑</a>
                                    <form method="POST" action="{{ route('admin.announcements.destroy', $announcement) }}" style="display:inline" data-confirm="确认删除该内容？">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-danger btn-sm" type="submit">删除</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="table-empty">还没有内容，创建第一条公告。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="panel-footer">{{ $announcements->links('partials.pagination') }}</div>
        </div>
    </div>
@endsection
