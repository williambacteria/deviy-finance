(function () {
    const trigger = document.querySelector('[data-delete-customer]');
    const dialog = document.getElementById('delete-customer-dialog');
    if (!trigger || !dialog) {
        return;
    }

    const message = dialog.querySelector('[data-delete-message]');
    const submit = dialog.querySelector('[data-delete-submit]');
    const cancel = dialog.querySelector('[data-delete-cancel]');
    const hasServices = trigger.dataset.hasServices === '1';

    trigger.addEventListener('click', function () {
        if (hasServices) {
            message.textContent = '该用户账户上有服务正在运行，请删除后再继续。';
            submit.hidden = true;
            cancel.textContent = '知道了';
        } else {
            message.textContent = '确认删除该客户？删除后将无法登录。';
            submit.hidden = false;
            cancel.textContent = '取消';
        }
        dialog.showModal();
    });

    cancel.addEventListener('click', function () {
        dialog.close();
    });

    dialog.addEventListener('click', function (event) {
        if (event.target === dialog) {
            dialog.close();
        }
    });
})();
