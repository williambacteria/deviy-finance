(() => {
    document.querySelectorAll('[data-auto-filter]').forEach((form) => {
        form.querySelectorAll('select').forEach((select) => {
            select.addEventListener('change', () => form.requestSubmit());
        });

        const search = form.querySelector('input[type="search"]');
        if (!search) {
            return;
        }

        let timer;
        search.addEventListener('input', () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(() => form.requestSubmit(), 450);
        });
        search.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                window.clearTimeout(timer);
            }
        });
    });

    document.querySelectorAll('[data-group-picker]').forEach((picker) => {
        const form = picker.closest('form');
        const toggle = picker.querySelector('[data-group-picker-toggle]');
        const panel = picker.querySelector('[data-group-picker-panel]');
        const label = picker.querySelector('[data-group-picker-label]');
        const input = picker.querySelector('[data-group-picker-value]');
        if (!form || !toggle || !panel || !label || !input) {
            return;
        }

        const close = () => {
            panel.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
        };

        toggle.addEventListener('click', () => {
            const open = panel.hidden;
            panel.hidden = !open;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        picker.querySelectorAll('[data-group-root]').forEach((root) => {
            root.addEventListener('click', () => {
                root.closest('.group-picker-group')?.classList.toggle('is-open');
            });
        });

        picker.querySelectorAll('[data-group-option]').forEach((option) => {
            option.addEventListener('click', () => {
                input.value = option.dataset.groupOption || '';
                label.textContent = option.dataset.label || '全部分组';
                picker.querySelectorAll('[data-group-option]').forEach((item) => item.classList.toggle('is-selected', item === option));
                close();
                form.requestSubmit();
            });
        });

        document.addEventListener('click', (event) => {
            if (!picker.contains(event.target)) {
                close();
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                close();
            }
        });
    });
})();
