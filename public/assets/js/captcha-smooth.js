/* Presentation adapter for the installed xfCaptcha. Server checks and payloads remain unchanged. */
(()=>{
    'use strict';
    const captcha=window.xfCaptcha;
    if(!captcha||captcha.__smoothAdapter)return;
    captcha.__smoothAdapter=true;
    if(typeof captcha.init==='function'){
        const originalInit=captcha.init;
        captcha.init=function(options){
            const theme=document.documentElement.dataset.theme||(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');
            return originalInit.call(captcha,{...options,theme});
        };
        // Scope component changes to its own modal instead of resetting the page theme.
        captcha.setTheme=function(theme){
            const selected=theme==='dark'?'dark':'light';
            captcha._options.theme=selected;
            document.getElementById('captcha_div')?.setAttribute('data-theme',selected);
            return captcha;
        };
        window.addEventListener('deviy:themechange',event=>captcha.setTheme(event.detail.theme));
    }
    let frame=0,pending=null;
    const originalStart=captcha._blockStartMove;
    const originalEnd=captcha._blockOnEnd;
    const originalBind=captcha._bindDragListeners;
    const originalUnbind=captcha._unbindDragListeners;
    const block=()=>captcha._el('.captcha_slide_block','_blockEl');
    function flush(){
        if(frame){cancelAnimationFrame(frame);frame=0;}
        if(pending===null)return;
        const offset=pending;pending=null;
        const element=block();
        if(element){element.style.transform='translate3d('+offset+'px,0,0)';element.setAttribute('aria-valuenow',String(Math.round(offset/Math.max(1,(captcha._imgWidth-captcha._markWidth)*(captcha._imgScale||1))*100)));}
        captcha._markOffset=offset;
        captcha._drawMark();
    }
    captcha._blockStartMove=function(event){
        pending=null;if(frame){cancelAnimationFrame(frame);frame=0;}
        return originalStart.call(captcha,event);
    };
    captcha._blockOnMove=function(event){
        if(!captcha._doing||!captcha._isMoving)return;
        if(event.cancelable)event.preventDefault();
        const point=event.touches?event.touches[0]:event;
        if(!point)return;
        const scale=captcha._imgScale||1;
        const max=(captcha._imgWidth-captcha._markWidth)*scale;
        const offset=Math.max(0,Math.min(point.clientX-captcha._blockStartX,max));
        captcha._slideTrack.push({x:Math.round(offset/scale),y:Math.round((captcha._markTopY||0)+(point.clientY-captcha._blockStartY)/scale),t:Date.now()-(captcha._trackStartTime||0)});
        pending=offset;
        if(!frame)frame=requestAnimationFrame(flush);
    };
    captcha._blockOnEnd=function(event){
        if(captcha._doing&&captcha._isMoving){
            const point=event.changedTouches?.[0]||event;
            if(Number.isFinite(point.clientX))pending=Math.max(0,Math.min(point.clientX-captcha._blockStartX,(captcha._imgWidth-captcha._markWidth)*(captcha._imgScale||1)));
            flush();
        }
        return originalEnd.call(captcha,event);
    };
    function cancelDrag(){
        if(!captcha._isMoving)return;
        pending=null;if(frame){cancelAnimationFrame(frame);frame=0;}
        captcha._isMoving=false;captcha._doing=false;captcha._unbindDragListeners();captcha.refresh();
    }
    captcha._bindDragListeners=function(){originalBind.call(captcha);document.addEventListener('touchcancel',cancelDrag,{passive:true});window.addEventListener('blur',cancelDrag);};
    captcha._unbindDragListeners=function(){originalUnbind.call(captcha);document.removeEventListener('touchcancel',cancelDrag);window.removeEventListener('blur',cancelDrag);};
})();
