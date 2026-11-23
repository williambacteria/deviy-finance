(() => {
    const table = document.querySelector('.table tbody');
    if (!table) {
        return;
    }

    const rows = () => [...table.querySelectorAll('.group-sort-row')];
    let dragged = null;
    let autoScrollSpeed = 0;
    let autoScrollFrame = null;

    const stopAutoScroll = () => {
        autoScrollSpeed = 0;
        if (autoScrollFrame !== null) {
            window.cancelAnimationFrame(autoScrollFrame);
            autoScrollFrame = null;
        }
        document.body.classList.remove('group-sorting');
    };

    const autoScroll = () => {
        if (!dragged) {
            stopAutoScroll();
            return;
        }
        if (autoScrollSpeed !== 0) {
            window.scrollBy(0, autoScrollSpeed);
        }
        autoScrollFrame = window.requestAnimationFrame(autoScroll);
    };

    const startAutoScroll = () => {
        document.body.classList.add('group-sorting');
        if (autoScrollFrame === null) {
            autoScrollFrame = window.requestAnimationFrame(autoScroll);
        }
    };

    document.addEventListener('dragover', (event) => {
        if (!dragged) {
            return;
        }

        const edge = 120;
        if (event.clientY < edge) {
            autoScrollSpeed = -Math.ceil((edge - event.clientY) / 4);
        } else if (event.clientY > window.innerHeight - edge) {
            autoScrollSpeed = Math.ceil((event.clientY - (window.innerHeight - edge)) / 4);
        } else {
            autoScrollSpeed = 0;
        }
    }, true);

    const clearDropState = () => {
        rows().forEach((row) => row.classList.remove('is-drop-before', 'is-drop-after'));
    };

    const rootBlock = (rootRow) => {
        const block = [rootRow];
        let next = rootRow.nextElementSibling;
        while (
            next
            && next.classList.contains('group-sort-row')
            && next.dataset.level === 'child'
            && next.dataset.parent === rootRow.dataset.groupId
        ) {
            block.push(next);
            next = next.nextElementSibling;
        }
        return block;
    };

    const compatible = (source, target) => {
        if (!source || !target || source === target) {
            return false;
        }
        if (source.dataset.level === 'root') {
            return target.dataset.level === 'root';
        }
        return target.dataset.level === 'child' && target.dataset.parent === source.dataset.parent;
    };

    const dropAfter = (source, target) => {
        const peers = rows().filter((row) => {
            if (source.dataset.level === 'root') {
                return row.dataset.level === 'root';
            }
            return row.dataset.level === 'child' && row.dataset.parent === source.dataset.parent;
        });

        return peers.indexOf(source) < peers.indexOf(target);
    };

    const moveBefore = (nodes, reference) => {
        nodes.forEach((node) => {
            table.insertBefore(node, reference);
        });
    };

    const moveRootBlock = (source, target, after) => {
        const sourceBlock = rootBlock(source);
        const targetBlock = rootBlock(target);
        const reference = after ? targetBlock[targetBlock.length - 1].nextElementSibling : target;

        if (reference === sourceBlock[0]) {
            return;
        }
        moveBefore(sourceBlock, reference);
    };

    const moveChild = (source, target, after) => {
        const reference = after ? target.nextElementSibling : target;
        if (reference === source) {
            return;
        }
        table.insertBefore(source, reference);
    };

    const syncSortOrders = () => {
        const rootRows = rows().filter((row) => row.dataset.level === 'root');
        rootRows.forEach((row, index) => {
            const input = row.querySelector('input[name*="[sort_order]"]');
            if (input) {
                input.value = index;
            }
            const display = row.querySelector('.group-sort-display');
            if (display) {
                display.textContent = index;
            }
        });

        const childRows = rows().filter((row) => row.dataset.level === 'child');
        const childIndex = {};
        childRows.forEach((row) => {
            const parent = row.dataset.parent;
            childIndex[parent] = (childIndex[parent] ?? -1) + 1;
            const input = row.querySelector('input[name*="[sort_order]"]');
            if (input) {
                input.value = childIndex[parent];
            }
            const display = row.querySelector('.group-sort-display');
            if (display) {
                display.textContent = childIndex[parent];
            }
        });
    };

    rows().forEach((row) => {
        const handle = row.querySelector('[data-group-drag]');
        if (!handle) {
            return;
        }

        handle.addEventListener('dragstart', (event) => {
            dragged = row;
            row.classList.add('is-dragging');
            startAutoScroll();
            event.dataTransfer.effectAllowed = 'move';
            try {
                event.dataTransfer.setData('text/plain', row.dataset.groupId);
            } catch (error) {
                // Ignore browsers that restrict drag payloads.
            }
        });

        handle.addEventListener('dragend', () => {
            row.classList.remove('is-dragging');
            dragged = null;
            clearDropState();
            stopAutoScroll();
        });

        row.addEventListener('dragover', (event) => {
            if (!compatible(dragged, row)) {
                return;
            }
            event.preventDefault();
            clearDropState();
            row.classList.add(dropAfter(dragged, row) ? 'is-drop-after' : 'is-drop-before');
        });

        row.addEventListener('dragleave', () => {
            row.classList.remove('is-drop-before', 'is-drop-after');
        });

        row.addEventListener('drop', (event) => {
            if (!compatible(dragged, row)) {
                return;
            }
            event.preventDefault();
            const after = dropAfter(dragged, row);

            if (dragged.dataset.level === 'root') {
                moveRootBlock(dragged, row, after);
            } else {
                moveChild(dragged, row, after);
            }

            syncSortOrders();
            clearDropState();
        });
    });
})();
