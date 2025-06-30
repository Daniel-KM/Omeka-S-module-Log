'use strict';

(function ($) {

    $(document).ready(function() {

        const dialogMessage = function (message, nl2br = false) {
            // Use a dialog to display a message, that should be escaped.
            var dialog = document.querySelector('dialog.popup-message');
            if (!dialog) {
                dialog = `
    <dialog class="popup popup-dialog dialog-message popup-message" data-is-dynamic="1">
        <div class="dialog-background">
            <div class="dialog-panel">
                <div class="dialog-header">
                    <button type="button" class="dialog-header-close-button" title="Close" autofocus="autofocus">
                        <span class="dialog-close">🗙</span>
                    </button>
                </div>
                <div class="dialog-contents">
                    {{ message }}
                </div>
            </div>
        </div>
    </dialog>`;
                $('body').append(dialog);
                dialog = document.querySelector('dialog.dialog-message');
            }
            if (nl2br) {
                message = message.replace(/(?:\r\n|\r|\n)/g, '<br/>');
            }
            dialog.innerHTML = dialog.innerHTML.replace('{{ message }}', message);
            dialog.showModal();
            $(dialog).trigger('o:dialog-opened');
        };

        /**
         * Search sidebar.
         */
        $('#content').on('click', '.quick-search', function(ev) {
            ev.preventDefault();
            const sidebar = $('#sidebar-search');
            if (sidebar.hasClass('active')) {
                Omeka.closeSidebar(sidebar);
                return;
            }

            Omeka.openSidebar(sidebar);

            // Auto-close if other sidebar opened
            $('body').one('o:sidebar-opened', '.sidebar', function () {
                if (!sidebar.is(this)) {
                    Omeka.closeSidebar(sidebar);
                }
            });
        });

        /**
         * Better display of big logs.
         */
        $('#content').on('click', 'a.popover', function() {
            const message = $(this).closest('.log-popover-parent').find('.log-popover-current').text();
            dialogMessage(message, true);
        });

        $(document).on('click', '.dialog-header-close-button', function() {
            const dialog = this.closest('dialog.popup');
            if (dialog) {
                dialog.close();
                if (dialog.hasAttribute('data-is-dynamic') && dialog.getAttribute('data-is-dynamic')) {
                    dialog.remove();
                }
            } else {
                $(this).closest('.popup').addClass('hidden').hide();
            }
        });

        // Complete the batch delete form after confirmation.
        // TODO Check if this is still needed.
        $('#confirm-delete-selected, #confirm-delete-all').on('submit', function() {
            const confirmForm = $(this);
            if ('confirm-delete-all' === this.id) {
                confirmForm.append($('.batch-query').clone());
            } else {
                $('#batch-form').find('input[name="resource_ids[]"]:checked:not(:disabled)').each(function() {
                    confirmForm.append($(this).clone().prop('disabled', false).attr('type', 'hidden'));
                });
            }
        });
        $('.delete-all').on('click', function() {
            Omeka.closeSidebar($('#sidebar-delete-selected'));
        });
        $('.delete-selected').on('click', function() {
            Omeka.closeSidebar($('#sidebar-delete-all'));
            const inputs = $('input[name="resource_ids[]"]');
            $('#delete-selected-count').text(inputs.filter(':checked').length);
        });
        $('#sidebar-delete-all').on('click', 'input[name="confirm-delete-all-check"]', function() {
            $('#confirm-delete-all input[type="submit"]').prop('disabled', this.checked ? false : true);
        });

    });

})(jQuery);
