(()=>{
    'use strict';
    const nav=document.getElementById('dv-nav');
    if(!nav)return;
    const links=[...nav.querySelectorAll(':scope > a')];
    const pageLink=links.find(a=>a.classList.contains('active'));
    function syncNavigation(){
        const hash=location.hash;
        const anchor=links.find(a=>new URL(a.href).pathname===location.pathname&&new URL(a.href).hash===hash&&hash!=='');
        for(const link of links){
            const selected=anchor?link===anchor:link===pageLink;
            link.classList.toggle('active',selected);
            if(selected)link.setAttribute('aria-current',anchor?'location':'page');else link.removeAttribute('aria-current');
        }
    }
    window.addEventListener('hashchange',syncNavigation);
    syncNavigation();
})();
