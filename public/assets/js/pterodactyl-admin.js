(function () {
    const egg = document.getElementById('ptero-egg');
    const nest = document.getElementById('ptero-nest');
    if (nest && egg) {
        const endpoint = nest.dataset.eggsEndpoint || '';
        nest.addEventListener('change', async function () {
            egg.innerHTML = '<option value="">加载中...</option>';
            if (!this.value) {
                egg.innerHTML = '<option value="">先选择 Nest</option>';
                return;
            }
            try {
                const response = await fetch(endpoint + '?nest_id=' + encodeURIComponent(this.value), {
                    headers: {Accept: 'application/json'},
                });
                const payload = await response.json();
                const items = payload.data || [];
                egg.innerHTML = '<option value="">选择 Egg</option>' + items.map(function (item) {
                    return '<option value="' + item.attributes.id + '">' + item.attributes.name + ' · #' + item.attributes.id + '</option>';
                }).join('');
            } catch (error) {
                egg.innerHTML = '<option value="">加载失败</option>';
            }
        });
    }

    const viewer = document.getElementById('ptero-nest-view');
    if (viewer) {
        viewer.addEventListener('change', function () {
            if (!this.value) {
                return;
            }
            const base = viewer.dataset.viewEndpoint || '';
            window.location.href = base + '?nest_id=' + encodeURIComponent(this.value);
        });
    }
})();
