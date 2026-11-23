@extends('layouts.admin')

@section('title', '编辑实名资料')
@section('page-title', '编辑实名资料')

@section('content')
    <div class="page-head">
        <div><h1>编辑实名资料</h1><p>{{ $user->name }} · {{ $user->email }}</p></div>
        <a class="btn btn-ghost" href="{{ route('admin.real-names.index') }}">返回实名审核</a>
    </div>

    @if ($errors->any())
        <div class="note danger" style="margin-bottom:14px">{{ $errors->first() }}</div>
    @endif

    <div class="panel">
        <div class="panel-head"><h2>认证信息</h2><span class="meta">修改后会覆盖客户提交的资料</span></div>
        <div class="panel-body">
            <form method="POST" action="{{ route('admin.real-names.update', $user) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="form-grid">
                    <div class="form-field"><label class="form-label">认证类型</label><select class="select" name="real_name_type"><option value="personal" @selected(old('real_name_type', $user->real_name_type ?: 'personal') === 'personal')>个人认证</option><option value="enterprise" @selected(old('real_name_type', $user->real_name_type) === 'enterprise')>企业认证</option></select></div>
                    <div class="form-field"><label class="form-label">审核状态</label><select class="select" name="real_name_status"><option value="unverified" @selected(old('real_name_status', $user->real_name_status) === 'unverified')>未认证</option><option value="pending" @selected(old('real_name_status', $user->real_name_status) === 'pending')>待审核</option><option value="verified" @selected(old('real_name_status', $user->real_name_status) === 'verified')>已认证</option><option value="rejected" @selected(old('real_name_status', $user->real_name_status) === 'rejected')>已驳回</option></select></div>
                </div>

                <h3 style="margin:22px 0 12px;font-size:14px">个人资料</h3>
                <div class="form-grid">
                    <div class="form-field"><label class="form-label">真实姓名</label><input class="input" name="real_name" value="{{ old('real_name', $user->real_name) }}"></div>
                    <div class="form-field"><label class="form-label">身份证号码</label><input class="input mono" name="real_name_id" value="{{ old('real_name_id', $user->real_name_id) }}" maxlength="18"></div>
                    <div class="form-field"><label class="form-label">手机号码</label><input class="input mono" name="real_name_phone" value="{{ old('real_name_phone', $user->real_name_phone) }}" maxlength="20"></div>
                </div>

                <h3 style="margin:22px 0 12px;font-size:14px">企业资料</h3>
                <div class="form-grid">
                    <div class="form-field"><label class="form-label">公司名称</label><input class="input" name="real_name_company" value="{{ old('real_name_company', $user->real_name_company) }}"></div>
                    <div class="form-field"><label class="form-label">统一社会信用代码</label><input class="input mono" name="real_name_credit_code" value="{{ old('real_name_credit_code', $user->real_name_credit_code) }}" maxlength="32"></div>
                    <div class="form-field"><label class="form-label">法人姓名</label><input class="input" name="real_name_legal_name" value="{{ old('real_name_legal_name', $user->real_name_legal_name) }}"></div>
                    <div class="form-field"><label class="form-label">法人手机号</label><input class="input mono" name="real_name_legal_phone" value="{{ old('real_name_legal_phone', $user->real_name_legal_phone) }}" maxlength="20"></div>
                    <div class="form-field"><label class="form-label">营业执照</label><input class="input" type="file" name="real_name_license" accept=".jpg,.jpeg,.png,.pdf"><span class="small muted">不选择则保留原文件@if (is_array($user->real_name_license)) · <a href="{{ route('admin.customers.real-name.file', [$user, 'license']) }}" target="_blank">查看原文件</a>@endif</span></div>
                    <div class="form-field"><label class="form-label">法人身份证照</label><input class="input" type="file" name="real_name_legal_id" accept=".jpg,.jpeg,.png,.pdf"><span class="small muted">不选择则保留原文件@if (is_array($user->real_name_legal_id)) · <a href="{{ route('admin.customers.real-name.file', [$user, 'legal_id']) }}" target="_blank">查看原文件</a>@endif</span></div>
                </div>

                <div class="form-field" style="margin-top:16px"><label class="form-label">驳回原因</label><input class="input" name="real_name_reject_reason" value="{{ old('real_name_reject_reason', $user->real_name_reject_reason) }}" placeholder="仅状态为已驳回时显示给客户"></div>

                <div class="flex items-center gap-3 mt-4">
                    <button class="btn btn-primary" type="submit">保存实名资料</button>
                    <a class="btn btn-ghost" href="{{ route('admin.real-names.index') }}">取消</a>
                </div>
            </form>
        </div>
    </div>
@endsection
