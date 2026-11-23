(() => {
    document.querySelectorAll('[data-copy-picker]').forEach(menu => {
        const filter = menu.querySelector('[data-copy-filter]');
        const search = menu.querySelector('[data-copy-search]');
        const groups = [...menu.querySelectorAll('[data-copy-group]')];
        const normalize = text => text.normalize('NFKC').toLocaleLowerCase().trim();
        const update = () => {
            const terms = normalize(search.value).split(/\s+/).filter(Boolean);
            const selected = filter.value;
            const [kind, id] = selected.split(':');
            let total = 0;
            groups.forEach(group => {
                const included = selected === 'all' || (kind === 'root' ? group.dataset.rootId === id : group.dataset.groupId === id);
                let count = 0;
                group.querySelectorAll('[data-copy-product]').forEach(form => {
                    const text = normalize(form.dataset.copyText);
                    const show = included && terms.every(term => text.includes(term));
                    form.hidden = !show;
                    if (show) count++;
                });
                group.hidden = count === 0;
                group.querySelector('[data-copy-group-count]').textContent = count;
                if (terms.length || selected !== 'all') group.open = count > 0;
                else group.open = false;
                total += count;
            });
            menu.querySelector('[data-copy-count]').textContent = `${total} 个商品`;
            menu.querySelector('[data-copy-empty]').hidden = total > 0;
        };
        filter.addEventListener('change', update);
        search.addEventListener('input', update);
        search.addEventListener('keydown', event => { if (event.key === 'Enter') event.preventDefault(); });
        menu.querySelector('[data-copy-reset]').addEventListener('click', () => { filter.value = 'all'; search.value = ''; update(); search.focus(); });
        menu.querySelector('[data-copy-close]').addEventListener('click', () => { menu.open = false; menu.querySelector(':scope > summary').focus(); });
        document.addEventListener('click', event => { if (!menu.contains(event.target)) menu.open = false; });
        menu.addEventListener('keydown', event => { if (event.key === 'Escape') { menu.open = false; menu.querySelector(':scope > summary').focus(); } });
        menu.querySelectorAll('[data-copy-product]').forEach(form => form.addEventListener('submit', () => { form.classList.add('is-submitting'); form.querySelector('button').disabled = true; }));
        update();
    });
})();
