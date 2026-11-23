(() => {
    const showFeedback = (button, message) => {
        const card = button.closest('[data-connection-card]');
        const feedback = card?.querySelector('[data-connection-feedback]');
        if (feedback) { feedback.textContent = message; }
    };
    document.querySelectorAll('[data-connection-copy]').forEach(button => {
        button.addEventListener('click', async () => {
            const value = button.dataset.connectionCopy || '';
            if (!value) return;
            try {
                try { await navigator.clipboard.writeText(value); }
                catch {
                    const field = document.createElement('textarea');
                    field.value = value; field.style.position = 'fixed'; field.style.opacity = '0';
                    document.body.appendChild(field); field.select();
                    let copied = false;
                    try { copied = document.execCommand('copy'); } finally { field.remove(); }
                    if (!copied) throw new Error('Clipboard unavailable');
                }
                const label = button.querySelector('span');
                if (label) { const original = label.textContent; label.textContent = '已复制'; setTimeout(() => { label.textContent = original; }, 1600); }
                showFeedback(button, button.getAttribute('aria-label')?.includes('密码') ? '登录密码已复制。' : '已复制到剪贴板。');
            } catch { showFeedback(button, '复制未成功，请选中内容后手动复制。'); }
        });
    });
    document.querySelectorAll('[data-connection-reveal]').forEach(button => {
        button.addEventListener('click', () => {
            const field = button.closest('.dv-value-line')?.querySelector('[data-connection-secret]');
            if (!field) return;
            const reveal = field.type === 'password'; field.type = reveal ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(reveal)); button.setAttribute('aria-label', reveal ? '隐藏密码' : '显示密码');
            button.querySelector('span').textContent = reveal ? '隐藏' : '显示';
        });
    });
    document.querySelectorAll('[data-connection-open]').forEach(button => {
        button.addEventListener('click', () => {
            const dialog = document.getElementById(button.dataset.connectionOpen);
            if (!dialog) return;
            dialog.querySelector('form').reset(); dialog.showModal();
        });
    });
    document.querySelectorAll('[data-connection-dialog]').forEach(dialog => {
        dialog.querySelectorAll('[data-connection-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
        dialog.addEventListener('close', () => {
            const field = dialog.querySelector('[data-connection-new-password]');
            field.value = ''; field.type = 'password';
            dialog.querySelector('[data-connection-new-reveal]').textContent = '显示';
        });
        dialog.querySelector('[data-connection-new-reveal]')?.addEventListener('click', event => {
            const field = dialog.querySelector('[data-connection-new-password]');
            field.type = field.type === 'password' ? 'text' : 'password';
            event.currentTarget.textContent = field.type === 'password' ? '显示' : '隐藏';
        });
        dialog.querySelector('[data-connection-generate]')?.addEventListener('click', () => {
            const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#%+-';
            const random = crypto.getRandomValues(new Uint8Array(20));
            const limit = 256 - 256 % alphabet.length;
            let password = 'Aq7!';
            random.forEach(value => { if (value < limit && password.length < 18) password += alphabet[value % alphabet.length]; });
            while (password.length < 18) {
                const value = crypto.getRandomValues(new Uint8Array(1))[0];
                if (value < limit) password += alphabet[value % alphabet.length];
            }
            const field = dialog.querySelector('[data-connection-new-password]'); field.value = password; field.type = 'text';
            dialog.querySelector('[data-connection-new-reveal]').textContent = '隐藏'; field.focus();
        });
    });
})();
