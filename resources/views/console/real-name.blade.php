@extends('layouts.console')

@section('title', '实名认证')
@section('page-title', '实名认证')

@section('content')
    <div class="page-head">
        <div><h1>实名认证</h1><p>购买云服务器前需完成实名认证。个人认证自动核验，企业认证由后台人工审核。</p></div>
    </div>

    @if (session('status'))
        <div class="note success" style="margin-bottom:14px">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="note danger" style="margin-bottom:14px">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="note danger" style="margin-bottom:14px">{{ $errors->first() }}</div>
    @endif

    @if ($user->real_name_status === 'verified')
        <div class="panel">
            <div class="panel-head"><h2>已实名认证</h2><x-status status="active" label="已验证" /></div>
            <div class="panel-body">
                @if ($user->real_name_type === 'enterprise')
                    <div class="profile-facts">
                        <div><span>认证类型</span><b>企业认证</b></div>
                        <div><span>公司名称</span><b>{{ $user->real_name_company }}</b></div>
                        <div><span>统一社会信用代码</span><b class="mono">{{ $user->real_name_credit_code }}</b></div>
                        <div><span>法人姓名</span><b>{{ $user->real_name_legal_name }}</b></div>
                        <div><span>法人手机号</span><b class="mono">{{ substr((string) $user->real_name_legal_phone, 0, 3).'****'.substr((string) $user->real_name_legal_phone, -4) }}</b></div>
                        <div><span>认证时间</span><b>{{ $user->real_name_verified_at?->format('Y-m-d H:i') ?? '—' }}</b></div>
                    </div>
                @else
                    <div class="profile-facts">
                        <div><span>认证类型</span><b>个人认证</b></div>
                        <div><span>姓名</span><b>{{ $user->real_name }}</b></div>
                        <div><span>身份证号</span><b class="mono">{{ $user->maskedRealNameId() }}</b></div>
                        <div><span>手机号</span><b class="mono">{{ substr((string) $user->real_name_phone, 0, 3).'****'.substr((string) $user->real_name_phone, -4) }}</b></div>
                        <div><span>认证时间</span><b>{{ $user->real_name_verified_at?->format('Y-m-d H:i') ?? '—' }}</b></div>
                    </div>
                @endif
                <p class="small muted" style="margin:16px 0 0">实名信息已加密保存。如需修改认证信息，请联系客服提交工单。</p>
            </div>
        </div>
    @else
        @php($locked = $user->real_name_status === 'pending')
        <div class="panel real-name-banner">
            <div class="panel-head"><h2>实名认证</h2><span class="meta">{{ $locked ? '资料审核中' : '选择认证类型' }}</span></div>
            <div class="panel-body">
                @if ($user->real_name_status === 'pending')
                    <div class="note" style="margin-bottom:16px">实名资料已提交，正在等待后台审核。审核通过后即可购买商品。</div>
                @elseif ($user->real_name_status === 'rejected')
                    <div class="note danger" style="margin-bottom:16px">实名认证未通过：{{ $user->real_name_reject_reason ?: '资料不完整，请重新提交。' }}</div>
                @endif

                <form method="POST" action="{{ route('console.real-name.store') }}" enctype="multipart/form-data" data-real-name-form>
                    @csrf
                    <div class="real-name-type-row">
                        <label class="form-label">认证类型 <span>*</span></label>
                        <select class="select" name="real_name_type" data-real-name-type @disabled($locked)>
                            <option value="personal" @selected(old('real_name_type', $user->real_name_type ?: 'personal') === 'personal')>个人认证</option>
                            <option value="enterprise" @selected(old('real_name_type', $user->real_name_type) === 'enterprise')>企业认证</option>
                        </select>
                        <span class="small muted" data-real-name-hint>{{ old('real_name_type', $user->real_name_type) === 'enterprise' ? '企业资料由后台人工审核' : '提交后由运营商三要素接口自动核验' }}</span>
                    </div>

                    <div class="real-name-panel" data-real-name-panel="personal">
                        <div class="form-grid">
                            <div class="form-field"><label class="form-label">真实姓名 <span>*</span></label><input class="input" name="real_name" value="{{ old('real_name', $user->real_name_type === 'personal' ? $user->real_name : '') }}" placeholder="与身份证一致" @disabled($locked)></div>
                            <div class="form-field"><label class="form-label">身份证号码 <span>*</span></label><input class="input mono" name="real_name_id" value="{{ old('real_name_id') }}" placeholder="18 位身份证号码" maxlength="18" @disabled($locked)></div>
                            <div class="form-field"><label class="form-label">手机号码 <span>*</span></label><input class="input mono" name="real_name_phone" value="{{ old('real_name_phone', $user->real_name_type === 'personal' ? $user->real_name_phone : '') }}" placeholder="11 位手机号码" maxlength="11" @disabled($locked)></div>
                        </div>
                        <p class="small muted" style="margin:14px 0 18px">姓名、身份证号、手机号必须为同一人，提交后自动核验。</p>
                    </div>

                    <div class="real-name-panel" data-real-name-panel="enterprise" hidden>
                        <div class="form-grid">
                            <div class="form-field"><label class="form-label">公司名称 <span>*</span></label><input class="input" name="real_name_company" value="{{ old('real_name_company', $user->real_name_company) }}" placeholder="营业执照上的公司全称" @disabled($locked)></div>
                            <div class="form-field"><label class="form-label">统一社会信用代码 <span>*</span></label><input class="input mono" name="real_name_credit_code" value="{{ old('real_name_credit_code', $user->real_name_credit_code) }}" placeholder="18 位统一社会信用代码" maxlength="18" @disabled($locked)></div>
                            <div class="form-field"><label class="form-label">法人姓名 <span>*</span></label><input class="input" name="real_name_legal_name" value="{{ old('real_name_legal_name', $user->real_name_legal_name) }}" placeholder="与身份证一致" @disabled($locked)></div>
                            <div class="form-field"><label class="form-label">法人手机号 <span>*</span></label><input class="input mono" name="real_name_legal_phone" value="{{ old('real_name_legal_phone', $user->real_name_legal_phone) }}" placeholder="11 位手机号码" maxlength="11" @disabled($locked)></div>
                            <div class="form-field"><label class="form-label">营业执照 <span>*</span></label><input class="input" type="file" name="real_name_license" accept=".jpg,.jpeg,.png,.pdf" @disabled($locked)><span class="small muted">JPG / PNG / PDF，最大 10MB</span></div>
                            <div class="form-field"><label class="form-label">法人身份证照 <span>*</span></label><input class="input" type="file" name="real_name_legal_id" accept=".jpg,.jpeg,.png,.pdf" @disabled($locked)><span class="small muted">JPG / PNG / PDF，最大 10MB</span></div>
                        </div>
                        <p class="small muted" style="margin:14px 0 18px">提交后由后台人工审核营业执照、法人和信用代码信息。</p>
                    </div>

                    @if (! $locked)
                        <button class="btn btn-primary" type="submit" data-real-name-submit>提交个人认证</button>
                    @endif
                </form>
            </div>
        </div>
    @endif

    <script src="{{ asset('assets/js/real-name.js') }}" defer></script>
@endsection
