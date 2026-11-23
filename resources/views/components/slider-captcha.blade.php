<div class="slider-captcha" data-slider-captcha data-endpoint="{{ route('captcha.slider') }}" data-verify-endpoint="{{ route('captcha.slider.verify') }}">
    <div class="slider-captcha-frame">
        <img class="slider-captcha-bg" data-captcha-bg alt="拖动拼图验证" draggable="false">
        <img class="slider-captcha-piece" data-captcha-piece alt="" draggable="false" hidden>
        <div class="slider-captcha-loading" data-captcha-loading>正在生成验证图片…</div>
    </div>
    <div class="slider-captcha-controls">
        <div class="slider-captcha-track" data-captcha-track>
            <span class="slider-captcha-progress" data-captcha-progress></span>
            <span class="slider-captcha-label" data-captcha-label>拖动滑块完成拼图</span>
            <button class="slider-captcha-handle" type="button" data-captcha-handle aria-label="拖动完成验证">→</button>
        </div>
        <button class="slider-captcha-refresh" type="button" data-captcha-refresh>换一张</button>
    </div>
    <input type="hidden" name="captcha_x" value="" data-captcha-x>
    <input type="hidden" name="captcha_token" value="" data-captcha-token>
    <div class="slider-captcha-error" data-captcha-error></div>
    <script src="{{ asset('assets/js/slider-captcha.js') }}" defer></script>
</div>
