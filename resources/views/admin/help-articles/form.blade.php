@extends('layouts.admin')

@section('title', $article->exists ? '编辑帮助文档' : '新建帮助文档')
@section('page-title', $article->exists ? '编辑帮助文档' : '新建帮助文档')

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $article->exists ? '编辑帮助文档' : '新建帮助文档' }}</h1>
            <p>正文支持 HTML，可使用标题、列表、提示块和图片，发布后会展示在官网帮助中心。</p>
        </div>
        <a class="btn btn-ghost" href="{{ route('admin.workspace', ['area' => 'website', 'tab' => 'docs']) }}">返回列表</a>
    </div>

    <div class="panel" style="max-width:1060px">
        <div class="panel-body">
            <form method="POST" enctype="multipart/form-data" action="{{ $article->exists ? route('admin.help-articles.update', $article) : route('admin.help-articles.store') }}">
                @csrf
                @if ($article->exists)
                    @method('PUT')
                @endif

                <div class="form-grid">
                    <div class="form-field full">
                        <label class="form-label">标题 <span>*</span></label>
                        <input class="input" name="title" value="{{ old('title', $article->title) }}" required maxlength="160" placeholder="例如：如何选购并开通云服务器">
                    </div>

                    <div class="form-field">
                        <label class="form-label">分类 <span>*</span></label>
                        <select class="select" name="help_category_id" required>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((int) old('help_category_id', $article->help_category_id) === $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-field">
                        <label class="form-label">Slug</label>
                        <input class="input" name="slug" value="{{ old('slug', $article->slug) }}" maxlength="160" placeholder="留空自动生成">
                    </div>

                    <div class="form-field full">
                        <label class="form-label">摘要</label>
                        <textarea class="textarea" name="summary" rows="2" maxlength="400" placeholder="展示在帮助中心列表中的简短说明">{{ old('summary', $article->summary) }}</textarea>
                    </div>

                    <div class="form-field full">
                        <label class="form-label">正文 HTML <span>*</span></label>
                        <textarea class="textarea" name="body" rows="22" required style="font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:13px;line-height:1.8" placeholder="<h2>第一步</h2><p>正文内容…</p>">{{ old('body', $article->body) }}</textarea>
                        <div class="small muted" style="margin-top:7px">可用结构：<code>&lt;h2&gt;</code>、<code>&lt;p&gt;</code>、<code>&lt;ol class="hc-steps"&gt;</code>、<code>&lt;div class="hc-callout"&gt;</code>、<code>&lt;figure&gt;&lt;img&gt;</code>。图片可填写站内路径或外链。</div>
                    </div>

                    <div class="form-field">
                        <label class="form-label">封面图片路径</label>
                        <input class="input" name="cover_image" value="{{ old('cover_image', $article->cover_image) }}" maxlength="255" placeholder="assets/images/help/purchase-flow.svg">
                        <div class="small muted" style="margin-top:6px">正文已包含图片时不会重复展示封面；也可以直接上传图片，上传后会覆盖此路径。</div>
                    </div>

                    <div class="form-field">
                        <label class="form-label">上传封面</label>
                        <input class="input" type="file" name="cover_image_file" accept="image/*">
                    </div>

                    <div class="form-field">
                        <label class="form-label">排序</label>
                        <input class="input" type="number" name="sort_order" value="{{ old('sort_order', $article->sort_order ?? 0) }}" min="0" max="9999">
                    </div>

                    <div class="form-field">
                        <label class="form-label">发布时间</label>
                        <input class="input" type="datetime-local" name="published_at" value="{{ old('published_at', $article->published_at?->format('Y-m-d\TH:i')) }}">
                    </div>

                    <div class="form-field full">
                        <label class="checkbox"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $article->is_published))> 立即发布</label>
                    </div>

                    @if ($article->cover_image)
                        <div class="form-field full">
                            <label class="form-label">当前封面</label>
                            <img src="{{ asset($article->cover_image) }}" alt="" style="max-width:360px;border:1px solid #e5ebf5;border-radius:10px">
                        </div>
                    @endif
                </div>

                <button class="btn btn-primary mt-4" type="submit">保存文档</button>
            </form>
        </div>
    </div>
@endsection
