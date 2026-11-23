@extends('layouts.admin')

@section('title', $announcement->exists ? '编辑内容' : '新建内容')
@section('page-title', $announcement->exists ? '编辑内容' : '新建内容')

@section('content')
    <div class="page-head">
        <div><h1>{{ $announcement->exists ? '编辑内容' : '新建内容' }}</h1><p>公告、新闻与帮助文章共用一套内容模型。</p></div>
        <a class="btn btn-ghost" href="{{ route('admin.announcements.index') }}">返回列表</a>
    </div>

    <div class="panel" style="max-width:980px">
        <div class="panel-body">
            <form method="POST" action="{{ $announcement->exists ? route('admin.announcements.update', $announcement) : route('admin.announcements.store') }}">
                @csrf
                @if ($announcement->exists) @method('PUT') @endif
                <div class="form-grid">
                    <div class="form-field full"><label class="form-label">标题 <span>*</span></label><input class="input" name="title" value="{{ old('title', $announcement->title) }}" required></div>
                    <div class="form-field"><label class="form-label">类型</label><select class="select" name="type">@foreach (['announcement' => '公告', 'news' => '新闻', 'help' => '帮助'] as $value => $label)<option value="{{ $value }}" @selected(old('type', $announcement->type ?: 'announcement') === $value)>{{ $label }}</option>@endforeach</select></div>
                    <div class="form-field"><label class="form-label">Slug</label><input class="input" name="slug" value="{{ old('slug', $announcement->slug) }}" placeholder="留空自动生成"></div>
                    <div class="form-field full"><label class="form-label">摘要</label><textarea class="textarea" name="summary" rows="2">{{ old('summary', $announcement->summary) }}</textarea></div>
                    <div class="form-field full"><label class="form-label">正文 <span>*</span></label><textarea class="textarea" name="body" rows="12" required>{{ old('body', $announcement->body) }}</textarea></div>
                    <div class="form-field"><label class="form-label">发布时间</label><input class="input" type="datetime-local" name="published_at" value="{{ old('published_at', $announcement->published_at?->format('Y-m-d\TH:i')) }}"></div>
                    <div class="form-field" style="align-self:end"><label class="checkbox"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $announcement->is_published))> 立即发布</label></div>
                </div>
                <button class="btn btn-primary mt-4" type="submit">保存内容</button>
            </form>
        </div>
    </div>
@endsection
