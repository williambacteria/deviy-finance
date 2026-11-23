document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('service-modal');
    const openButton = document.getElementById('open-service-modal');
    if (modal && openButton) {
        const productSelect = modal.querySelector('[data-service-product]');
        const cycleSelect = modal.querySelector('[data-service-cycle]');
        const panels = [...modal.querySelectorAll('[data-product-panel]')];
        const cycleLabels = { monthly: '月付', quarterly: '季付', semiannual: '半年付', annual: '年付' };
        const firstPrice = modal.querySelector('[data-service-first-price]');
        const renewalPrice = modal.querySelector('[data-service-renewal-price]');
        const initialCycle = modal.querySelector('form')?.dataset.initialCycle || '';

        const updatePrice = (prices) => {
            const value = prices[cycleSelect.value];
            const text = value === undefined ? '' : Number(value).toFixed(2);
            if (firstPrice) {
                firstPrice.value = text;
            }
            if (renewalPrice) {
                renewalPrice.value = text;
            }
        };

        const syncIpMode = (panel) => {
            const mode = panel?.querySelector('[data-ip-mode]');
            const field = panel?.querySelector('[data-ip-field]');
            const portsField = panel?.querySelector('[data-nat-ports-field]');
            if (!mode) {
                return;
            }
            const dedicated = mode.value === 'dedicated';
            if (field) {
                field.hidden = !dedicated;
                field.querySelectorAll('input').forEach((input) => {
                    input.disabled = !dedicated;
                });
            }
            if (portsField) {
                portsField.hidden = dedicated;
                portsField.querySelectorAll('input').forEach((input) => {
                    input.disabled = dedicated;
                });
            }
        };

        const syncProduct = () => {
            const option = productSelect?.selectedOptions[0];
            const productId = option?.value || '';
            panels.forEach((panel) => {
                const active = panel.dataset.productPanel === productId;
                panel.hidden = !active;
                panel.querySelectorAll('input, select').forEach((field) => {
                    field.disabled = !active;
                });
                if (active) {
                    syncIpMode(panel);
                }
            });

            if (!option || !productId) {
                cycleSelect.innerHTML = '<option value="">请先选择商品</option>';
                updatePrice({});
                return;
            }

            let prices = {};
            try {
                prices = JSON.parse(option.dataset.prices || '{}');
            } catch {
                prices = {};
            }

            const previous = cycleSelect.value || initialCycle;
            cycleSelect.innerHTML = '';
            Object.entries(cycleLabels).forEach(([key, label]) => {
                if (prices[key] === undefined) {
                    return;
                }
                const item = document.createElement('option');
                item.value = key;
                item.textContent = label + ' · ¥' + Number(prices[key]).toFixed(2);
                cycleSelect.appendChild(item);
            });
            if ([...cycleSelect.options].some((item) => item.value === previous)) {
                cycleSelect.value = previous;
            }
            updatePrice(prices);
        };

        productSelect?.addEventListener('change', syncProduct);
        modal.addEventListener('change', (event) => {
            if (event.target.matches('[data-ip-mode]')) {
                syncIpMode(event.target.closest('[data-product-panel]'));
            }
        });
        cycleSelect?.addEventListener('change', () => {
            let prices = {};
            try {
                prices = JSON.parse(productSelect.selectedOptions[0]?.dataset.prices || '{}');
            } catch {
                prices = {};
            }
            updatePrice(prices);
        });
        syncProduct();

        const open = () => {
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
            setTimeout(() => modal.querySelector('select[name="product_id"]')?.focus(), 50);
        };
        const close = () => {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
        };

        openButton.addEventListener('click', open);
        document.getElementById('close-service-modal')?.addEventListener('click', close);
        document.getElementById('cancel-service-modal')?.addEventListener('click', close);
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                close();
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                close();
            }
        });

        if (modal.dataset.errors === '1') {
            open();
        }
    }

    const editModal=document.getElementById('service-edit-modal');
    if(!editModal)return;
    const editForm=document.getElementById('service-edit-form'),deleteForm=document.getElementById('service-delete-form'),destroy=document.getElementById('service-destroy-dialog'),expiry=document.getElementById('service-edit-expiry'),renewal=document.getElementById('service-edit-renewal'),mode=document.getElementById('service-edit-renewal-mode'),fields=editForm.querySelector('fieldset'),confirmNumber=document.getElementById('service-delete-number'),deleteSubmit=deleteForm.querySelector('[data-submit-service-delete]');
    let trigger=null,busy=false,number='',pending=false;
    const text=(selector,value)=>document.querySelectorAll(selector).forEach(el=>el.textContent=value||'未绑定');
    function closeEdit(){if(busy)return;editModal.classList.remove('open');editModal.setAttribute('aria-hidden','true');document.body.classList.remove('service-adjust-open');trigger?.focus();}
    function syncConfirm(){deleteSubmit.disabled=confirmNumber.value!==number||!deleteForm.querySelector('[name=acknowledge]').checked||deleteForm.querySelector('[name=reason]').value.trim().length<2||busy;}
    mode.addEventListener('change',()=>{renewal.disabled=mode.value!=='custom';renewal.required=mode.value==='custom';});
    document.querySelectorAll('[data-service-edit]').forEach(button=>button.addEventListener('click',()=>{
        trigger=button;busy=false;number=button.dataset.serviceNo;pending=button.dataset.deletionPending==='1';editForm.reset();deleteForm.reset();editForm.action=button.dataset.action;deleteForm.action=button.dataset.deleteAction;fields.disabled=pending;
        text('#service-edit-name',button.dataset.name);text('[data-adjust-service-no],[data-destroy-number]',number);text('[data-adjust-provider],[data-destroy-provider]',button.dataset.provider);text('[data-adjust-binding],[data-destroy-binding]',button.dataset.externalRef);text('[data-destroy-name]',button.dataset.name);text('[data-adjust-cycle]',button.dataset.cycle);
        document.getElementById('service-edit-display-name').value=button.dataset.name;expiry.value=button.dataset.expiry||'';renewal.value=button.dataset.renewal||'';renewal.disabled=true;renewal.required=false;document.getElementById('service-edit-auto-renew').checked=button.dataset.autoRenew==='1';
        const synced=button.dataset.source==='mofang';expiry.disabled=synced;editModal.querySelector('[data-expiry-shortcuts]').hidden=synced;editModal.querySelector('[data-expiry-help]').textContent=synced?'此商品到期由供应商接口同步，续费请通过订单完成，不能在此单独延长供应商租期。':button.dataset.source==='qzvps'?'保存时同步轻舟到期日期，并核对结果；轻舟续期接口会解除到期锁定。此操作不产生收费订单。':'调整本站账务到期时间，不产生付款订单，也不会自动延长资源平台租期。';editModal.querySelector('[data-adjust-pending]').hidden=!pending;
        editModal.querySelector('[data-open-service-delete]').textContent=pending?'核对删除结果':'删除服务';deleteSubmit.textContent=pending?'核对删除结果':'同步销毁并删除';editModal.querySelector('[data-adjust-live]').textContent='';deleteForm.querySelector('[data-delete-live]').textContent='';editModal.classList.add('open');editModal.setAttribute('aria-hidden','false');document.body.classList.add('service-adjust-open');setTimeout(()=>document.getElementById(pending?'close-service-edit':'service-edit-display-name').focus(),30);
    }));
    document.getElementById('close-service-edit').addEventListener('click',closeEdit);document.getElementById('cancel-service-edit').addEventListener('click',closeEdit);editModal.addEventListener('click',e=>{if(e.target===editModal)closeEdit();});
    document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!destroy.open)closeEdit();if(e.key==='Tab'&&editModal.classList.contains('open')&&!destroy.open){const focusable=[...editModal.querySelectorAll('button,input,select,textarea,a')].filter(el=>!el.disabled&&el.getClientRects().length>0),first=focusable[0],last=focusable.at(-1);if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}}});
    editModal.querySelectorAll('[data-expiry-days]').forEach(button=>button.addEventListener('click',()=>{if(expiry.disabled)return;const date=expiry.value?new Date(expiry.value):new Date();date.setDate(date.getDate()+Number(button.dataset.expiryDays));const local=new Date(date.getTime()-date.getTimezoneOffset()*60000);expiry.value=local.toISOString().slice(0,16);}));
    editModal.querySelector('[data-open-service-delete]').addEventListener('click',()=>{deleteForm.reset();deleteForm.querySelector('[name=reason]').value=document.getElementById('service-edit-reason').value;syncConfirm();destroy.showModal();confirmNumber.focus();});
    destroy.querySelectorAll('[data-cancel-service-delete]').forEach(button=>button.addEventListener('click',()=>{if(!busy)destroy.close();}));destroy.addEventListener('cancel',e=>{if(busy)e.preventDefault();});destroy.addEventListener('close',()=>editModal.querySelector('[data-open-service-delete]').focus());deleteForm.addEventListener('input',syncConfirm);
    editForm.addEventListener('submit',e=>{if(busy){e.preventDefault();return;}busy=true;editForm.querySelector('[type=submit]').disabled=true;editModal.querySelector('[data-adjust-live]').textContent='正在保存调整，请稍候…';});
    deleteForm.addEventListener('submit',e=>{if(busy||confirmNumber.value!==number||!deleteForm.checkValidity()){e.preventDefault();syncConfirm();return;}busy=true;deleteSubmit.disabled=true;destroy.querySelectorAll('[data-cancel-service-delete]').forEach(button=>button.disabled=true);deleteForm.querySelector('[data-delete-live]').textContent=pending?'正在核对对应平台的资源状态，请稍候…':'正在提交同步销毁并核对结果，请勿重复操作…';});
});
