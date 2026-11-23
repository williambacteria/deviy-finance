@php
    $tabParams = ['area' => 'website', 'tab' => 'friend-links'];
    $tabUrl = route('admin.workspace', $tabParams);
@endphp

<div class="panel friend-links-panel">
    <div class="panel-head">
        <h2>{{ $editedLink ? '编辑友情链接' : '新增友情链接' }}</h2>
        @if ($editedLink)
            <a class="btn btn-sm" href="{{ $tabUrl }}">取消编辑</a>
        @endif
    </div>
    <form class="panel-body friend-links-form" method="POST" enctype="multipart/form-data"
        action="{{ $editedLink ? route('admin.homepage-friend-links.update', $editId) : route('admin.homepage-friend-links.store') }}">
        @csrf
        @if ($editedLink) @method('PUT') @endif
        @if ($errors->any())
            <div class="flash flash-error friend-links-full" role="alert">{{ $errors->first() }}</div>
        @endif
        <label class="field">
            <span>网站名称</span>
            <input class="input" name="name" maxlength="60" required value="{{ old('name', $editedLink['name'] ?? '') }}">
        </label>
        <label class="field">
            <span>链接地址</span>
            <input class="input" type="url" name="url" maxlength="2048" required placeholder="https://example.com" value="{{ old('url', $editedLink['url'] ?? '') }}">
        </label>
        <label class="field friend-links-full">
            <span>网站介绍</span>
            <textarea class="input" name="description" rows="2" maxlength="160">{{ old('description', $editedLink['description'] ?? '') }}</textarea>
        </label>
        <div class="friend-links-logo-field">
            @if (! empty($editedLink['logo']))
                <img class="friend-links-admin-logo" src="{{ asset('storage/'.$editedLink['logo']) }}" alt="当前 Logo">
            @endif
            <div>
                <label class="field">
                    <span>网站 Logo</span>
                    <input type="file" name="logo" accept="image/png,image/jpeg,image/webp">
                    <small class="muted">支持 PNG、JPG、WebP，最大 2 MB。</small>
                </label>
                @if (! empty($editedLink['logo']))
                    <label class="friend-links-check"><input type="checkbox" name="remove_logo" value="1" @checked(old('remove_logo'))> 移除当前 Logo</label>
                @endif
            </div>
        </div>
        <label class="field">
            <span>排序（数字越小越靠前）</span>
            <input class="input" type="number" name="position" min="0" max="9999" required value="{{ old('position', $editedLink['position'] ?? 100) }}">
        </label>
        <div class="friend-links-form-actions friend-links-full">
            <label class="friend-links-check">
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $editedLink['enabled'] ?? true))> 在首页展示
            </label>
            <button type="submit" class="btn btn-primary">{{ $editedLink ? '保存修改' : '添加友链' }}</button>
        </div>
    </form>
</div>

<div class="panel friend-links-panel">
    <div class="panel-head"><h2>已添加的友情链接</h2></div>
    <div class="panel-body friend-links-list">
        @forelse ($friendLinks as $id => $link)
            <div class="friend-links-list-item">
                @if ($link['logo'])
                    <img class="friend-links-admin-logo" src="{{ asset('storage/'.$link['logo']) }}" alt="" loading="lazy">
                @else
                    <span class="friend-links-admin-logo" aria-hidden="true"><x-icon name="globe" :size="20" /></span>
                @endif
                <div class="friend-links-info">
                    <strong>{{ $link['name'] }}</strong>
                    <div class="muted small">{{ $link['url'] }}</div>
                    @if ($link['description'] !== '') <div class="small">{{ $link['description'] }}</div> @endif
                </div>
                <div class="friend-links-row-actions">
                    <span class="chip">{{ $link['enabled'] ? '展示中' : '已隐藏' }} · {{ $link['position'] }}</span>
                    <a class="btn btn-sm" href="{{ route('admin.workspace', $tabParams + ['edit' => $id]) }}">编辑</a>
                    <form method="POST" action="{{ route('admin.homepage-friend-links.destroy', $id) }}" data-confirm="确定删除这条友情链接？">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm">删除</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="muted">还没有友情链接。</p>
        @endforelse
    </div>
</div>

@push('head')
    <link rel="stylesheet" href="{{ asset('assets/css/friend-links.css') }}?v={{ filemtime(public_path('assets/css/friend-links.css')) }}">
@endpush
