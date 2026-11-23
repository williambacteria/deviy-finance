@extends('layouts.admin')
@section('title', $workspaceTitle)
@section('page-title', $workspaceTitle)
@section('content')
@include('admin.organization-style')
<div class="admin-business-workspace" data-admin-workspace="{{ $workspaceArea }}">
    <header class="admin-section-heading"><div><span class="admin-eyebrow">运营管理</span><h1>{{ $workspaceTitle }}</h1><p>{{ $workspaceDescription }}</p></div></header>
    <nav class="admin-workspace-tabs" aria-label="{{ $workspaceTitle }}分类">
        @foreach ($workspaceTabs as $key => $item)<a href="{{ route('admin.workspace', ['area' => $workspaceArea, 'tab' => $key]) }}" @class(['is-active' => $key === $workspaceTab]) @if ($key === $workspaceTab) aria-current="page" @endif>{{ $item[0] }}</a>@endforeach
    </nav>
    <div class="admin-merged-content" data-merged-page>@include($workspacePartial, $workspaceData)</div>
</div>
@endsection
