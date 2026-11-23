(() => {
    const form = document.querySelector('[data-real-name-form]');
    const type = form?.querySelector('[data-real-name-type]');
    if (!form || !type) {
        return;
    }

    const panels = [...form.querySelectorAll('[data-real-name-panel]')];
    const hint = form.querySelector('[data-real-name-hint]');
    const submit = form.querySelector('[data-real-name-submit]');
    const locked = type.disabled;

    const sync = () => {
        const selected = type.value;
        panels.forEach((panel) => {
            const active = panel.dataset.realNamePanel === selected;
            panel.hidden = !active;
            panel.querySelectorAll('input, select, textarea').forEach((field) => {
                field.disabled = locked || !active;
            });
        });

        if (hint) {
            hint.textContent = selected === 'enterprise' ? '企业资料由后台人工审核' : '提交后由运营商三要素接口自动核验';
        }
        if (submit) {
            submit.textContent = selected === 'enterprise' ? '提交企业认证' : '提交个人认证';
        }
    };

    type.addEventListener('change', sync);
    sync();
})();
