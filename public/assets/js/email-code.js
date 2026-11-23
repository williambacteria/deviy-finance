(function () {
    const groups = document.querySelectorAll('[data-verification-code]');
    groups.forEach(function (group) {
        const digits = Array.from(group.querySelectorAll('[data-code-digit]'));
        const hidden = group.querySelector('[data-code-value]');
        const form = group.closest('form');
        if (!digits.length || !hidden) {
            return;
        }

        const sync = function () {
            hidden.value = digits.map(function (input) {
                return input.value.replace(/\D/g, '').slice(0, 1);
            }).join('');
        };

        const fill = function (value) {
            const numbers = String(value).replace(/\D/g, '').slice(0, 6).split('');
            digits.forEach(function (input, index) {
                input.value = numbers[index] || '';
            });
            sync();
            const next = digits[Math.min(numbers.length, digits.length - 1)];
            next?.focus();
        };

        if (hidden.value) {
            fill(hidden.value);
        }

        digits.forEach(function (input, index) {
            input.addEventListener('input', function () {
                input.value = input.value.replace(/\D/g, '').slice(-1);
                sync();
                if (input.value && digits[index + 1]) {
                    digits[index + 1].focus();
                }
            });

            input.addEventListener('keydown', function (event) {
                if (event.key === 'Backspace' && input.value === '' && digits[index - 1]) {
                    digits[index - 1].focus();
                    digits[index - 1].value = '';
                    sync();
                    event.preventDefault();
                }
                if (event.key === 'ArrowLeft' && digits[index - 1]) {
                    digits[index - 1].focus();
                }
                if (event.key === 'ArrowRight' && digits[index + 1]) {
                    digits[index + 1].focus();
                }
            });

            input.addEventListener('paste', function (event) {
                event.preventDefault();
                fill(event.clipboardData?.getData('text') || '');
            });
        });

        form?.addEventListener('submit', function (event) {
            sync();
            if (hidden.value.length !== 6) {
                event.preventDefault();
                digits.find(function (input) { return input.value === ''; })?.focus();
            }
        });
    });
})();
