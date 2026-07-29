(function () {
    'use strict';

    const deleteFormSelector = 'form[data-delete-confirm]';

    document.addEventListener('submit', function (event) {
        const form = event.target.closest(deleteFormSelector);

        if (!form) {
            return;
        }

        if (form.dataset.deleteConfirmed === 'true') {
            delete form.dataset.deleteConfirmed;

            return;
        }

        event.preventDefault();

        if (typeof window.Swal === 'undefined') {
            console.error('SweetAlert2 is unavailable; the delete request was cancelled.');

            return;
        }

        const requiredWorkspaceName = form.dataset.deleteConfirmName;
        const isWorkspaceDeletion = requiredWorkspaceName !== undefined;
        const dialogOptions = {
            title: 'Delete Record?',
            text: isWorkspaceDeletion
                ? 'This workspace will be deactivated and moved to recovery status.'
                : 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Delete',
            cancelButtonText: 'Cancel',
            focusCancel: true,
            allowEscapeKey: true,
            allowOutsideClick: true,
            reverseButtons: true,
        };

        if (isWorkspaceDeletion) {
            dialogOptions.input = 'text';
            dialogOptions.inputLabel = `Type "${requiredWorkspaceName}" to confirm.`;
            dialogOptions.inputPlaceholder = requiredWorkspaceName;
            dialogOptions.inputAttributes = {
                autocapitalize: 'off',
                autocorrect: 'off',
            };
            dialogOptions.inputValidator = function (value) {
                if (value !== requiredWorkspaceName) {
                    return 'The workspace name must match exactly.';
                }
            };
        }

        window.Swal.fire(dialogOptions).then(function (result) {
            if (! result.isConfirmed) {
                return;
            }

            if (isWorkspaceDeletion) {
                const workspaceNameInput = form.querySelector('[data-delete-confirm-name-input]');

                if (! workspaceNameInput) {
                    console.error('The workspace name confirmation field is unavailable; the delete request was cancelled.');

                    return;
                }

                workspaceNameInput.value = result.value;
            }

            form.dataset.deleteConfirmed = 'true';

            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();

                return;
            }

            HTMLFormElement.prototype.submit.call(form);
        });
    });
})();
