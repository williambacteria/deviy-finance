(() => {
    const root = document.querySelector('[data-product-sort-root]');
    if (!root) {
        return;
    }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    root.querySelectorAll('[data-group-block]').forEach((block) => {
        const button = block.querySelector(':scope > .pg-group-head [data-group-collapse]');
        if (!button) {
            return;
        }
        const key = 'deviy-pg-collapsed-' + block.dataset.groupId;
        const stored = window.localStorage.getItem(key);
        if (stored === '1') {
            block.classList.add('is-collapsed');
            button.setAttribute('aria-expanded', 'false');
        }
        button.addEventListener('click', () => {
            const collapsed = block.classList.toggle('is-collapsed');
            button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            window.localStorage.setItem(key, collapsed ? '1' : '0');
        });
    });

    let dragging = null;
    let draggingSourceList = null;

    root.querySelectorAll('.pg-product[draggable="true"]').forEach((row) => {
        row.addEventListener('dragstart', (event) => {
            dragging = row;
            draggingSourceList = row.closest('[data-product-list]');
            row.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            try {
                event.dataTransfer.setData('text/plain', row.dataset.productId);
            } catch (error) {
                // Some browsers restrict custom drag payloads.
            }
        });
        row.addEventListener('dragend', () => {
            row.classList.remove('is-dragging');
            root.querySelectorAll('.pg-products.is-drop-target').forEach((list) => list.classList.remove('is-drop-target'));
            dragging = null;
            draggingSourceList = null;
        });
    });

    const showStatus = (message, isError = false) => {
        let status = document.querySelector('.pg-sort-status');
        if (!status) {
            status = document.createElement('div');
            status.className = 'pg-sort-status';
            document.body.appendChild(status);
        }
        status.textContent = message;
        status.classList.toggle('is-error', isError);
        status.classList.add('is-visible');
        window.clearTimeout(showStatus.timer);
        showStatus.timer = window.setTimeout(() => status.classList.remove('is-visible'), 2600);
    };

    const persist = async (list) => {
        const productIds = [...list.querySelectorAll('.pg-product')].map((row) => Number(row.dataset.productId));
        const response = await fetch(root.dataset.sortUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({
                group_id: Number(list.dataset.groupId),
                product_ids: productIds,
            }),
        });

        if (!response.ok) {
            throw new Error('save failed');
        }
        showStatus('商品顺序已保存');
    };

    root.querySelectorAll('[data-product-list]').forEach((list) => {
        list.addEventListener('dragover', (event) => {
            if (!dragging) {
                return;
            }
            event.preventDefault();
            list.classList.add('is-drop-target');
            list.querySelector('.pg-empty')?.remove();
            const after = [...list.querySelectorAll('.pg-product:not(.is-dragging)')].find((row) => {
                const rect = row.getBoundingClientRect();
                return event.clientY < rect.top + rect.height / 2;
            });
            if (after) {
                list.insertBefore(dragging, after);
            } else {
                list.appendChild(dragging);
            }
        });

        list.addEventListener('dragleave', (event) => {
            if (!list.contains(event.relatedTarget)) {
                list.classList.remove('is-drop-target');
            }
        });

        list.addEventListener('drop', async (event) => {
            if (!dragging) {
                return;
            }
            event.preventDefault();
            list.classList.remove('is-drop-target');
            const sourceList = draggingSourceList;
            const targetGroupId = list.dataset.groupId;
            dragging.dataset.groupId = targetGroupId;
            try {
                if (sourceList && sourceList !== list && !sourceList.querySelector('.pg-product')) {
                    sourceList.innerHTML = '<div class="pg-empty">该分组暂无商品</div>';
                }
                if (sourceList && sourceList !== list && sourceList.querySelector('.pg-product')) {
                    await persist(sourceList);
                }
                await persist(list);
            } catch (error) {
                showStatus('排序保存失败，请刷新后重试', true);
                window.setTimeout(() => window.location.reload(), 900);
            }
        });
    });
})();
