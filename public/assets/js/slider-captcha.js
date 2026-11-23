(() => {
    document.querySelectorAll('[data-slider-captcha]').forEach((root) => {
        const endpoint = root.dataset.endpoint;
        const verifyEndpoint = root.dataset.verifyEndpoint;
        const bg = root.querySelector('[data-captcha-bg]');
        const piece = root.querySelector('[data-captcha-piece]');
        const loading = root.querySelector('[data-captcha-loading]');
        const track = root.querySelector('[data-captcha-track]');
        const handle = root.querySelector('[data-captcha-handle]');
        const progress = root.querySelector('[data-captcha-progress]');
        const label = root.querySelector('[data-captcha-label]');
        const error = root.querySelector('[data-captcha-error]');
        const xInput = root.querySelector('[data-captcha-x]');
        const tokenInput = root.querySelector('[data-captcha-token]');
        const refresh = root.querySelector('[data-captcha-refresh]');
        const form = root.closest('form');

        let width = 320;
        let height = 160;
        let pieceSize = 52;
        let ready = false;
        let verified = false;
        let dragging = false;
        let startX = 0;
        let offset = 0;
        let maxOffset = 0;

        const reset = () => {
            verified = false;
            xInput.value = '';
            piece.style.left = '0';
            piece.style.top = '0';
            handle.style.transform = 'translateX(0)';
            handle.disabled = false;
            progress.style.width = '0';
            label.textContent = '拖动滑块完成拼图';
            error.textContent = '';
        };

        const load = async () => {
            ready = false;
            reset();
            loading.hidden = false;
            piece.hidden = true;
            handle.disabled = true;
            try {
                const response = await fetch(endpoint + '?t=' + Date.now(), {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    cache: 'no-store',
                });
                if (!response.ok) {
                    throw new Error('captcha');
                }
                const data = await response.json();
                width = Number(data.width) || 320;
                height = Number(data.height) || 160;
                pieceSize = Number(data.piece_size) || 52;
                bg.src = data.bg;
                piece.src = data.piece;
                piece.style.width = (pieceSize / width * 100) + '%';
                piece.style.height = (pieceSize / height * 100) + '%';
                piece.style.top = (Number(data.y) / height * 100) + '%';
                tokenInput.value = data.token || '';
                maxOffset = Math.max(0, track.clientWidth - handle.offsetWidth);
                handle.disabled = false;
                loading.hidden = true;
                piece.hidden = false;
                ready = true;
            } catch {
                loading.textContent = '验证图片加载失败，请刷新重试';
                loading.hidden = false;
                error.textContent = '验证码加载失败，请换一张重试。';
            }
        };

        handle.addEventListener('pointerdown', (event) => {
            if (!ready) {
                return;
            }
            dragging = true;
            startX = event.clientX;
            offset = Number(handle.dataset.offset || 0);
            handle.setPointerCapture(event.pointerId);
            label.textContent = '松开后提交验证';
            error.textContent = '';
        });

        handle.addEventListener('pointermove', (event) => {
            if (!dragging || !ready) {
                return;
            }
            const next = Math.max(0, Math.min(maxOffset, offset + event.clientX - startX));
            handle.style.transform = 'translateX(' + next + 'px)';
            progress.style.width = (next + handle.offsetWidth) + 'px';
            piece.style.left = (next / Math.max(1, maxOffset) * (100 - pieceSize / width * 100)) + '%';
            xInput.value = Math.round(next / Math.max(1, maxOffset) * (width - pieceSize));
        });

        const verifyPosition = async () => {
            if (!ready || xInput.value === '' || verified) {
                return;
            }
            label.textContent = '正在验证…';
            const body = new URLSearchParams({
                captcha_x: xInput.value,
                captcha_token: tokenInput.value,
                _token: document.querySelector('meta[name="csrf-token"]')?.content || '',
            });
            try {
                const response = await fetch(verifyEndpoint, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    },
                    credentials: 'same-origin',
                    body,
                });
                const data = await response.json();
                if (data.ok) {
                    verified = true;
                    handle.disabled = true;
                    progress.style.width = '100%';
                    label.textContent = '验证通过';
                    error.textContent = '';
                } else {
                    reset();
                    error.textContent = data.message || '验证失败，请重试。';
                    handle.dataset.offset = '0';
                }
            } catch {
                reset();
                error.textContent = '验证失败，请刷新后重试。';
                handle.dataset.offset = '0';
            }
        };

        const finish = (event) => {
            if (!dragging) {
                return;
            }
            dragging = false;
            if (handle.hasPointerCapture(event.pointerId)) {
                handle.releasePointerCapture(event.pointerId);
            }
            offset = Math.max(0, Math.min(maxOffset, offset + event.clientX - startX));
            handle.dataset.offset = String(offset);
            if (xInput.value === '') {
                label.textContent = '请拖动滑块完成拼图';
                return;
            }
            verifyPosition();
        };

        handle.addEventListener('pointerup', finish);
        handle.addEventListener('pointercancel', finish);
        handle.addEventListener('lostpointercapture', () => {
            dragging = false;
        });

        refresh?.addEventListener('click', () => {
            handle.dataset.offset = '0';
            load();
        });

        form?.addEventListener('submit', (event) => {
            if (!verified) {
                event.preventDefault();
                error.textContent = '请先拖动滑块完成拼图验证。';
                label.textContent = '请拖动滑块完成拼图';
            }
        });

        window.addEventListener('resize', () => {
            maxOffset = Math.max(0, track.clientWidth - handle.offsetWidth);
        });

        load();
    });
})();
