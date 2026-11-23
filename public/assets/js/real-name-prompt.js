document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('real-name-prompt');
    if (!modal) {
        return;
    }

    const close = () => {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
    };

    modal.querySelectorAll('[data-real-name-prompt-close]').forEach((button) => {
        button.addEventListener('click', close);
    });
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            close();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
        }
    });
});
