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
                    <button type="button" class="dialog-header-close-button">
                        <span class="dialog-close" aria-hidden="true">🗙</span>
                        <span class="dialog-close-label">${Omeka.jsTranslate('Close')}</span>
                    </button>
                    <button type="button" class="o-icon- far fa-copy log-copy-dialog" title="${Omeka.jsTranslate('Copy')}" aria-label="${Omeka.jsTranslate('Copy')}"></button>
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
         * Better display of big logs.
         */
        $('#content').on('click', 'button.popover', function(ev) {
            const message = $(this).closest('.log-popover-parent').find('.log-popover-current').text();
            dialogMessage(message, true);
        });

        $(document).on('click', '.log-copy-dialog', function(ev) {
            var btn = $(this);
            var text = btn.closest('.dialog-panel').find('.dialog-contents').text().trim();
            var copied = function() {
                btn.removeClass('far fa-copy').addClass('fa fa-check log-copied').attr('title', Omeka.jsTranslate('Message copied'));
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(copied);
            } else {
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                copied();
            }
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

        /**
         * Copy log message to clipboard.
         */
        $('#content').on('click', 'button.log-copy', function(ev) {
            const row = $(this).closest('.log-popover-parent');
            const full = row.find('.log-message-full');
            const text = full.length ? full.text() : row.find('.log-message').text();
            var btn = $(this);
            var copied = function() {
                $('.log-copy').removeClass('fa fa-check log-copied').addClass('far fa-copy').attr('title', Omeka.jsTranslate('Copy'));
                btn.removeClass('far fa-copy').addClass('fa fa-check log-copied').attr('title', Omeka.jsTranslate('Message copied'));
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text.trim()).then(copied);
            } else {
                var ta = document.createElement('textarea');
                ta.value = text.trim();
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                copied();
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

        /**
         * Opt-in auto-refresh of the log list, useful to follow a running job.
         *
         * Only the table body is replaced, so batch controls, delegated row
         * actions (popover, copy) and the sidebar are preserved. The button is
         * rendered only on the first page. The preference is kept per browser.
         */
        const autoRefreshBtn = document.getElementById('log-auto-refresh');
        if (autoRefreshBtn) {
            const storageKey = 'logAutoRefresh';
            const interval = 5000;
            let timer = null;

            const isEnabled = () => localStorage.getItem(storageKey) === '1';

            const refreshList = function () {
                if (document.hidden) return;
                fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(response => response.text())
                    .then(html => {
                        const fresh = new DOMParser().parseFromString(html, 'text/html')
                            .querySelector('#batch-form table tbody');
                        const current = document.querySelector('#batch-form table tbody');
                        if (fresh && current) {
                            current.innerHTML = fresh.innerHTML;
                        }
                    })
                    .catch(() => {});
            };

            const render = function () {
                const on = isEnabled();
                autoRefreshBtn.classList.toggle('active', on);
                autoRefreshBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
                autoRefreshBtn.textContent = on ? autoRefreshBtn.dataset.labelOn : autoRefreshBtn.dataset.labelOff;
                if (on && !timer) {
                    timer = setInterval(refreshList, interval);
                } else if (!on && timer) {
                    clearInterval(timer);
                    timer = null;
                }
            };

            autoRefreshBtn.addEventListener('click', function () {
                localStorage.setItem(storageKey, isEnabled() ? '0' : '1');
                render();
            });

            render();
        }

    });

})(jQuery);
