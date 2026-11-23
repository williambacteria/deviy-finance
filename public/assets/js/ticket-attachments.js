(function () {
    const formatSize = function (bytes) {
        if (bytes >= 1024 * 1024) {
            return (bytes / 1024 / 1024).toFixed(1) + ' MB';
        }
        return Math.max(1, Math.round(bytes / 1024)) + ' KB';
    };

    document.querySelectorAll('input[type="file"][data-attachment-picker]').forEach(function (input) {
        const preview = document.createElement('div');
        preview.className = 'attachment-picker-preview';
        input.insertAdjacentElement('afterend', preview);

        let files = [];
        let objectUrls = [];

        function clearObjectUrls() {
            objectUrls.forEach(function (url) {
                URL.revokeObjectURL(url);
            });
            objectUrls = [];
        }

        function syncInput() {
            const transfer = new DataTransfer();
            files.forEach(function (file) {
                transfer.items.add(file);
            });
            input.files = transfer.files;
        }

        function render() {
            clearObjectUrls();
            preview.innerHTML = '';

            if (files.length === 0) {
                return;
            }

            const head = document.createElement('div');
            head.className = 'attachment-picker-head';
            head.textContent = '已选择 ' + files.length + ' 个附件：';
            preview.appendChild(head);

            files.forEach(function (file, index) {
                const item = document.createElement('div');
                item.className = 'attachment-picker-item';

                const isImage = file.type.indexOf('image/') === 0;
                const isPdf = file.type === 'application/pdf';
                const objectUrl = (isImage || isPdf) ? URL.createObjectURL(file) : '';
                if (objectUrl) {
                    objectUrls.push(objectUrl);
                }

                if (isImage) {
                    const image = document.createElement('img');
                    image.src = objectUrl;
                    image.alt = file.name;
                    image.onerror = function () {
                        image.replaceWith(Object.assign(document.createElement('span'), {
                            className: 'attachment-picker-icon',
                            textContent: '图片',
                        }));
                    };
                    item.appendChild(image);
                } else {
                    const icon = document.createElement('span');
                    icon.className = 'attachment-picker-icon';
                    icon.textContent = isPdf ? 'PDF' : '文件';
                    item.appendChild(icon);
                }

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'attachment-picker-remove';
                remove.setAttribute('aria-label', '删除附件');
                remove.textContent = '×';
                remove.addEventListener('click', function () {
                    files.splice(index, 1);
                    syncInput();
                    render();
                });
                item.appendChild(remove);

                const meta = document.createElement('div');
                meta.className = 'attachment-picker-meta';
                const name = document.createElement('span');
                name.className = 'attachment-picker-name';
                name.textContent = file.name;
                const size = document.createElement('small');
                size.textContent = formatSize(file.size);
                meta.appendChild(name);
                meta.appendChild(size);
                item.appendChild(meta);

                if (objectUrl) {
                    const link = document.createElement('a');
                    link.href = objectUrl;
                    link.target = '_blank';
                    link.rel = 'noopener';
                    link.className = 'attachment-picker-link';
                    link.textContent = isPdf ? '打开 PDF' : '查看大图';
                    item.appendChild(link);
                }

                preview.appendChild(item);
            });
        }

        input.addEventListener('change', function () {
            const selected = Array.from(input.files || []);
            const existing = new Set(files.map(function (file) {
                return file.name + '|' + file.size + '|' + file.lastModified;
            }));

            selected.forEach(function (file) {
                const key = file.name + '|' + file.size + '|' + file.lastModified;
                if (!existing.has(key)) {
                    files.push(file);
                    existing.add(key);
                }
            });

            syncInput();
            render();
        });
    });
})();
