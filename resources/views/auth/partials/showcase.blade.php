<aside class="auth-showcase" aria-labelledby="auth-showcase-title">
    <div class="auth-showcase-inner">
        <div class="auth-showcase-copy">
            @if ($isRegister)
                <h1 id="auth-showcase-title">开启你的<br class="auth-showcase-break"><span>云端工作区</span></h1>
                <p>快捷方便的下一代云交互模式。验证邮箱后，即可选购并管理云机、裸金属和 CDN。</p>
            @else
                <h1 id="auth-showcase-title">你的云资源<br class="auth-showcase-break"><span>尽在控制台</span></h1>
                <p>从选购、支付到开通、运维，一个账户管理你的全部云资源。云机、裸金属、CDN，统一在控制台完成。</p>
            @endif
        </div>
        <div class="auth-illustration" aria-hidden="true">
            <div class="auth-orbit auth-orbit-outer"><i></i></div>
            <div class="auth-orbit auth-orbit-inner"><i></i></div>
            <div class="auth-illustration-window">
                <div class="auth-illustration-head">
                    <span class="auth-illustration-window-dots"><i></i><i></i><i></i></span>
                    <span>{{ $isRegister ? '开通流程示意' : '云控制台预览' }}</span>
                </div>
                @if ($isRegister)
                    <div class="auth-illustration-steps">
                        <div><span><x-icon name="users" :size="20" /></span><b>创建账户</b></div>
                        <div><span><x-icon name="mail" :size="20" /></span><b>验证邮箱</b></div>
                        <div><span><x-icon name="cloud" :size="20" /></span><b>选购资源</b></div>
                    </div>
                @else
                    <div class="auth-illustration-dashboard">
                        <span><x-icon name="server" :size="20" /> 云服务器</span>
                        <span class="auth-illustration-pulse"><i></i> 运行中</span>
                        <div class="auth-illustration-chart">
                            @foreach ([35, 50, 40, 63, 46, 71, 58, 78, 68, 87, 73, 95] as $level)
                                <i style="--bar-height:{{ $level }}%;--bar-delay:{{ $loop->index * 110 }}ms"></i>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
        <ul class="auth-showcase-links">
            <li><a href="{{ route('products.index') }}"><x-icon name="package" :size="17" /> 云产品</a></li>
            <li><a href="{{ route('help') }}"><x-icon name="life-buoy" :size="17" /> 帮助支持</a></li>
            <li><a href="{{ route('home') }}#contact"><x-icon name="mail" :size="17" /> 联系我们</a></li>
        </ul>
    </div>
</aside>
