(() => {
    if (window.deviyCdnCredentialsReady) return;
    window.deviyCdnCredentialsReady = true;
    document.addEventListener('click', async event => {
        const button = event.target.closest('[data-cdn-reveal], [data-cdn-copy]');
        if (!button) return;
        const panel = button.closest('[data-cdn-credentials]');
        const field = panel?.querySelector('[data-cdn-password]');
        if (!field) return;
        if (button.hasAttribute('data-cdn-reveal')) {
            const reveal = field.type === 'password';
            field.type = reveal ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(reveal));
            button.textContent = reveal ? '隐藏密码' : '显示密码';
            return;
        }
        const feedback = panel.querySelector('[data-cdn-copy-feedback]');
        try {
            try { await navigator.clipboard.writeText(field.value); }
            catch {
                const element = document.createElement('textarea');
                element.value = field.value; document.body.appendChild(element); element.select();
                let ok = false;
                try { ok = document.execCommand('copy'); } finally { element.remove(); }
                if (!ok) throw new Error('Copy failed');
            }
            if (feedback) feedback.textContent = '密码已复制';
        } catch { if (feedback) feedback.textContent = '复制失败，请显示密码后手动复制。'; }
    });
})();
