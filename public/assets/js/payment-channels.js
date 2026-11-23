(() => {
    'use strict';
    const root = document.querySelector('[data-payment-center]');
    if (!root) return;
    const tabs = [...root.querySelectorAll('[data-channel]')];
    const panels = [...root.querySelectorAll('[data-channel-panel]')];
    const select = (name, updateUrl = true) => {
        if (!tabs.some(tab => tab.dataset.channel === name)) return;
        tabs.forEach(tab => {
            const selected = tab.dataset.channel === name;
            tab.classList.toggle('is-selected', selected);
            tab.setAttribute('aria-selected', String(selected));
            tab.tabIndex = selected ? 0 : -1;
        });
        panels.forEach(panel => { panel.hidden = panel.dataset.channelPanel !== name; });
        if (updateUrl) {
            const url = new URL(location.href);
            url.searchParams.set('channel', name);
            history.replaceState(null, '', url);
        }
    };
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', event => {
            if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            event.preventDefault(); select(tab.dataset.channel);
        });
        tab.addEventListener('keydown', event => {
            let target;
            if (event.key === 'ArrowRight' || event.key === 'ArrowDown') target = tabs[(index + 1) % tabs.length];
            if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') target = tabs[(index - 1 + tabs.length) % tabs.length];
            if (event.key === 'Home') target = tabs[0];
            if (event.key === 'End') target = tabs[tabs.length - 1];
            if (target) { event.preventDefault(); select(target.dataset.channel); target.focus(); }
        });
    });
    addEventListener('popstate', () => select(new URL(location.href).searchParams.get('channel') || 'alipay', false));
    root.querySelectorAll('[data-payment-form]').forEach(form => {
        const dirty = () => { const note = form.querySelector('[data-unsaved-note]'); if (note) { note.textContent = '有未保存的更改'; note.classList.add('is-dirty'); } };
        form.addEventListener('input', dirty);
        form.addEventListener('change', dirty);
        form.querySelectorAll('input[type=file]').forEach(input => input.addEventListener('change', () => {
            const tile = input.closest('.pc-upload'); const label = tile?.querySelector('[data-file-label]');
            if (label) label.textContent = input.files?.length ? `已选择：${input.files[0].name}` : '未选择新文件';
            tile?.classList.toggle('has-file', !!input.files?.length);
        }));
    });
    root.querySelectorAll('[data-payment-copy]').forEach(button => button.addEventListener('click', async () => {
        const input = document.getElementById(button.dataset.paymentCopy);
        if (!input) return;
        const label = button.querySelector('span');
        try { await navigator.clipboard.writeText(input.value); if (label) label.textContent = '已复制'; }
        catch { input.select(); if (label) label.textContent = '已选中'; }
    }));
})();
