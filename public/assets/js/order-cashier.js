(() => {
    const dialog=document.getElementById('order-cashier'),configNode=document.getElementById('order-cashier-config');
    if(!dialog||!configNode)return;
    const config=JSON.parse(configNode.textContent),csrf=document.querySelector('meta[name=csrf-token]')?.content;
    const pay=dialog.querySelector('[data-cashier-pay]'),error=dialog.querySelector('[data-cashier-error]'),qrPanel=dialog.querySelector('[data-cashier-qr-panel]'),methods=dialog.querySelector('.oc-methods'),success=dialog.querySelector('[data-cashier-success]'),status=dialog.querySelector('[data-cashier-status]');
    let timer,busy=false,active=config.initial,lastQr='';
    const message=text=>{error.textContent=text;error.hidden=!text;};
    const post=async(url,data={})=>{const response=await fetch(url,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify(data)});const result=await response.json();if(!response.ok)throw new Error(result.message||Object.values(result.errors||{}).flat()[0]||'付款暂未完成，请稍后核对。');return result;};
    const render=data=>{
        active=data;
        if(data.paid){clearInterval(timer);qrPanel.hidden=true;methods.hidden=true;pay.hidden=true;success.hidden=false;message('');return;}
        const hasQr=!!data.qr_code;
        qrPanel.hidden=!hasQr;pay.hidden=hasQr;methods.hidden=hasQr;success.hidden=true;
        if(hasQr){
            dialog.querySelector('[data-cashier-scan-label]').textContent=data.gateway==='wechat'?'请使用微信扫码付款':'请使用支付宝扫码付款';
            dialog.querySelector('[data-cashier-payment-number]').textContent='订单支付单 #'+data.payment_id;
            const target=dialog.querySelector('[data-order-qr]');
            if(lastQr!==data.qr_code){target.replaceChildren();new QRCode(target,{text:data.qr_code,width:205,height:205,correctLevel:QRCode.CorrectLevel.M});lastQr=data.qr_code;}
            status.textContent='等待付款，支付成功后自动更新。';
        }else if(data.payment_id){status.textContent='二维码未取得或已过期，点击确认支付以恢复。';}
    };
    const poll=async()=>{if(!dialog.open||busy)return;try{const r=await fetch(config.status,{headers:{Accept:'application/json'},credentials:'same-origin'});if(!r.ok)return;const data=await r.json();render(data);}catch(e){status.textContent='网络暂时中断，系统会自动再次查询。';}};
    const open=()=>{if(typeof dialog.showModal==='function'&&!dialog.open)dialog.showModal();render(active);clearInterval(timer);timer=setInterval(poll,3500);poll();};
    document.querySelectorAll('[data-open-order-cashier]').forEach(button=>button.addEventListener('click',open));
    dialog.querySelector('[data-close-order-cashier]').addEventListener('click',()=>dialog.close());
    dialog.addEventListener('close',()=>clearInterval(timer));
    dialog.addEventListener('click',event=>{if(event.target===dialog){const r=dialog.getBoundingClientRect();if(event.clientX<r.left||event.clientX>r.right||event.clientY<r.top||event.clientY>r.bottom)dialog.close();}});
    pay.addEventListener('click',async()=>{if(busy)return;const gateway=dialog.querySelector('input[name=cashier_gateway]:checked:not(:disabled)')?.value;if(!gateway){message('请选择可用的支付方式。');return;}busy=true;pay.disabled=true;const label=pay.textContent;pay.textContent='正在处理…';message('');try{render(await post(config.start,{gateway}));}catch(e){message(e.message);}finally{busy=false;pay.disabled=false;pay.textContent=label;}});
    dialog.querySelector('[data-cashier-restart]').addEventListener('click',async()=>{if(busy)return;busy=true;message('');try{render(await post(config.close));}catch(e){message(e.message);}finally{busy=false;}});
    if(config.autoOpen||config.initial.payment_id)open();
})();
