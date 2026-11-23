@extends('layouts.admin')

@section('title', '新建客户')
@section('page-title', '新建客户')

@section('content')
    <div class="page-head">
        <div><h1>新建客户</h1><p>创建客户账号，可直接设置登录密码、分组、信用额度和初始余额。</p></div>
        <a class="btn btn-ghost" href="{{ route('admin.customers.index') }}">返回客户列表</a>
    </div>

    @if ($errors->any())
        <div class="note danger" style="margin-bottom:14px">{{ $errors->first() }}</div>
    @endif

    <div class="panel" style="max-width:900px">
        <div class="panel-body">
            <div class="note info" style="margin-bottom:16px">
                <b>创建流程：</b> 1. 填写账号资料 → 2. 客户验证邮箱 → 3. 登录客户控制台。默认会在创建后发送验证邮件。
            </div>

            <form method="POST" action="{{ route('admin.customers.store') }}">
                @csrf
                <div class="form-grid">
                    <div class="form-field"><label class="form-label">客户姓名 <span>*</span></label><input class="input" name="name" value="{{ old('name') }}" required autofocus></div>
                    <div class="form-field"><label class="form-label">登录邮箱 <span>*</span></label><input class="input" type="email" name="email" value="{{ old('email') }}" required></div>
                    <div class="form-field"><label class="form-label">手机号码</label><input class="input" name="phone" value="{{ old('phone') }}" placeholder="可选"></div>
                    <div class="form-field"><label class="form-label">QQ号</label><input class="input" name="qq" value="{{ old('qq') }}" placeholder="可选"></div>
                    <div class="form-field"><label class="form-label">公司名称</label><input class="input" name="company" value="{{ old('company') }}" placeholder="可选"></div>
                    <div class="form-field"><label class="form-label">登录密码 <span>*</span></label>
                        <div class="password-field">
                            <input class="input" type="password" name="password" minlength="8" required autocomplete="new-password">
                            <button class="password-toggle" type="button" data-password-toggle aria-label="显示密码">
                                <span data-password-eye-show><x-icon name="eye" :size="16" /></span>
                                <span data-password-eye-hide hidden><x-icon name="eye-off" :size="16" /></span>
                            </button>
                        </div>
                    </div>
                    <div class="form-field"><label class="form-label">确认密码 <span>*</span></label>
                        <div class="password-field">
                            <input class="input" type="password" name="password_confirmation" minlength="8" required autocomplete="new-password">
                            <button class="password-toggle" type="button" data-password-toggle aria-label="显示密码">
                                <span data-password-eye-show><x-icon name="eye" :size="16" /></span>
                                <span data-password-eye-hide hidden><x-icon name="eye-off" :size="16" /></span>
                            </button>
                        </div>
                    </div>
                    <div class="form-field"><label class="form-label">客户分组</label><select class="select" name="customer_group"><option value="default" @selected(old('customer_group', 'default') === 'default')>标准客户</option><option value="vip" @selected(old('customer_group') === 'vip')>VIP 客户</option></select></div>
                    <div class="form-field"><label class="form-label">账号状态</label><select class="select" name="status"><option value="active" @selected(old('status', 'active') === 'active')>正常</option><option value="suspended" @selected(old('status') === 'suspended')>暂停</option></select></div>
                    <div class="form-field"><label class="form-label">信用额度</label><input class="input" type="number" step="0.01" min="0" name="credit_limit" value="{{ old('credit_limit', 0) }}"></div>
                    <div class="form-field"><label class="form-label">初始余额</label><input class="input" type="number" step="0.01" min="0" name="initial_balance" value="{{ old('initial_balance', 0) }}"><div class="meta">大于 0 时会写入一条初始余额流水。</div></div>
                    <div class="form-field full"><label class="form-label">内部备注</label><textarea class="textarea" name="notes" rows="3" placeholder="仅管理员可见">{{ old('notes') }}</textarea></div>
                    <div class="form-field full">
                        <label class="flex items-center gap-2" style="font-size:12px"><input type="checkbox" name="send_verification" value="1" @checked(old('send_verification', true))> 创建后发送邮箱验证邮件</label>
                        <div class="meta" style="margin-top:5px">发送后客户需点击邮件中的验证链接才能登录控制台；取消勾选则创建后直接视为已验证。</div>
                    </div>
                </div>

                <div class="note info" style="margin-top:16px">客户创建后可使用邮箱和密码登录客户控制台；初始余额为 0 时也可在客户详情页后续调整。</div>

                <div class="flex items-center gap-3 mt-4">
                    <button class="btn btn-primary" type="submit">创建客户</button>
                    <a class="btn btn-ghost" href="{{ route('admin.customers.index') }}">取消</a>
                </div>
            </form>
        </div>
    </div>
@endsection
