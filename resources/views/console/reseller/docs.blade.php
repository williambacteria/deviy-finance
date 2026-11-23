@extends('layouts.console')
@section('title','代理 API 接口文档')
@section('page-title','代理 API 接口文档')
@section('content')
@include('console.reseller.style')
<header class="api-hero"><div><small>DEVELOPER DOCUMENTATION · V1</small><h1>代理 API 接口文档</h1><p>魔方财务 zjmf_api 对接指南、参数、响应示例与错误处理。</p></div><div class="api-actions"><a class="btn" href="{{ route('console.reseller.docs',['download'=>1]) }}">下载文档</a><a class="btn" href="{{ route('console.reseller.index') }}">返回申请与密钥</a></div></header>
<article class="api-card api-doc">{!! \Illuminate\Support\Str::markdown($markdown,['html_input'=>'strip','allow_unsafe_links'=>false]) !!}</article>
@endsection
