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

        window.Swal.fire({
            title: 'Delete Record?',
            text: 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Delete',
            cancelButtonText: 'Cancel',
            focusCancel: true,
            allowEscapeKey: true,
            allowOutsideClick: true,
            reverseButtons: true,
        }).then(function (result) {
            if (! result.isConfirmed) {
                return;
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
