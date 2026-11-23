/* Click verification presentation. Verification tokens and security checks stay server-side. */
(()=>{
    'use strict';
    const captcha=window.xfCaptcha;
    if(!captcha||captcha.__clickPresentation)return;
    captcha.__clickPresentation=true;
    const originalInit=captcha.init;
    const originalClick=captcha._handleClickCaptcha;
    const originalRefresh=captcha.refresh;
    function theme(){return document.documentElement.dataset.theme||(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');}
    function updateProgress(){
        const modal=document.getElementById('captcha_div');
        if(!modal)return;
        let status=modal.querySelector('.captcha-click-progress');
        if(!status){status=document.createElement('div');status.className='captcha-click-progress';status.setAttribute('role','status');status.setAttribute('aria-live','polite');modal.querySelector('.captcha_tools')?.before(status);}
        const count=captcha._clickCount||0,total=captcha._options.charCount||3;
        status.textContent=count>=total?'正在核验，请稍候…':'按提示顺序点选文字 · 已完成 '+count+' / '+total;
        const canvas=modal.querySelector('.captcha_canvas_bg');
        if(canvas){canvas.setAttribute('aria-label','请按上方提示顺序点选图片中的三个汉字');canvas.style.touchAction='manipulation';}
    }
    captcha.init=function(options){
        const result=originalInit.call(captcha,{...options,theme:theme(),charCount:3,placeholder:'点击完成点选验证',clickText:'请按提示顺序，点选图片中的三个汉字',showRipple:false});
        const modal=document.getElementById('captcha_div');if(modal)modal.classList.add('captcha-click-mode');
        updateProgress();return result;
    };
    captcha.setTheme=function(selected){captcha._options.theme=selected==='dark'?'dark':'light';document.getElementById('captcha_div')?.setAttribute('data-theme',captcha._options.theme);return captcha;};
    captcha._handleClickCaptcha=function(event){const result=originalClick.call(captcha,event);updateProgress();return result;};
    captcha.refresh=function(...args){const result=originalRefresh.apply(captcha,args);updateProgress();return result;};
    window.addEventListener('deviy:themechange',event=>captcha.setTheme(event.detail.theme));
})();
