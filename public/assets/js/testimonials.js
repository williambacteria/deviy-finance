(()=>{
    'use strict';
    const carousel=document.querySelector('[data-testimonials-carousel]'),button=document.querySelector('[data-testimonial-toggle]');
    if(!carousel||!button)return;
    const count=carousel.querySelectorAll('.sky-testimonial-group:first-child .sky-testimonial-card').length;
    carousel.style.setProperty('--review-duration',Math.max(36,count*12)+'s');
    let paused=false;
    button.addEventListener('click',()=>{
        paused=!paused;carousel.classList.toggle('is-paused',paused);
        button.setAttribute('aria-pressed',String(paused));button.setAttribute('aria-label',paused?'继续评价滚动':'暂停评价滚动');button.textContent=paused?'▶ 继续滚动':'Ⅱ 暂停滚动';
    });
    document.addEventListener('visibilitychange',()=>carousel.classList.toggle('is-paused',document.hidden||paused));
})();
