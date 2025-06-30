'use strict';

document.addEventListener('DOMContentLoaded', function () {

    /**
     * Update the state of each job in the log table.
     *
     * An optimisation is possible with one query for all jobs, but useless in most of the cases.
     */
    const jobStates = document.querySelectorAll('.job-state[data-job-state-url]');

    const updateJobStates = () => {
        if (!jobStates) return;

        jobStates.forEach(jobState => {
            const jobStateUrl = jobState.getAttribute('data-job-state-url');
            if (!jobStateUrl || !jobStateUrl.length) return;

            const labelElement = jobState.querySelector('.system-state-label');
            const iconElement = jobState.querySelector('.system-state-icon');
            if (!labelElement && !iconElement) return;

            fetch(jobStateUrl)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        if (data.data?.['job']?.['o:system_state']) {
                            const label = data.data['job']['o:system_state'].label;
                            const icon = data.data['job']['o:system_state'].icon;
                            if (labelElement) {
                                labelElement.textContent = label
                            }
                            if (iconElement) {
                                iconElement.className = `system-state-icon ${icon}`;
                                iconElement.setAttribute('title', label);
                                iconElement.setAttribute('aria-label', label);
                            }
                        } else {
                            // No job, old job, or no state, so remove icon.
                            if (labelElement) {
                                labelElement.textContent = '';
                            }
                            if (iconElement) {
                                iconElement.className = 'system-state-icon';
                                iconElement.setAttribute('title', '');
                                iconElement.setAttribute('aria-label', '');
                            }
                        }
                    }
                })
                .catch(error => {
                    console.error('Error updating system state:', data.message || data['job'] || error);
                    if (labelElement) {
                        labelElement.textContent = '';
                    }
                    if (iconElement) {
                        iconElement.className = 'system-state-icon';
                        iconElement.setAttribute('title', '');
                        iconElement.setAttribute('aria-label', '');
                    }
                });
        });
    };

    // Update each system state every 1 to 60 seconds.
    setInterval(updateJobStates, jobStates.length < 3 ? 1000 : (jobStates.length < 50 ? 10000 : 60000));

});

$(document).ready(function() {

    /**
     * Search sidebar.
     */
    $('#content').on('click', '.quick-search', function(e) {
        e.preventDefault();
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
    $('a.popover').webuiPopover('destroy').webuiPopover({
        placement: 'auto-bottom',
        content: function (element) {
            var target = $('[data-target=' + element.id + ']');
            var content = target.closest('.webui-popover-parent').find('.webui-popover-current');
            $(content).removeClass('truncate').show();
            return content;
        },
        title: '',
        arrow: false,
        backdrop: true,
        onShow: function(element) { element.css({left: 0}); }
    });

    $('a.popover').webuiPopover();

    // Complete the batch delete form after confirmation.
    // TODO Check if this is still needed.
    $('#confirm-delete-selected, #confirm-delete-all').on('submit', function(e) {
        var confirmForm = $(this);
        if ('confirm-delete-all' === this.id) {
            confirmForm.append($('.batch-query').clone());
        } else {
            $('#batch-form').find('input[name="resource_ids[]"]:checked:not(:disabled)').each(function() {
                confirmForm.append($(this).clone().prop('disabled', false).attr('type', 'hidden'));
            });
        }
    });
    $('.delete-all').on('click', function(e) {
        Omeka.closeSidebar($('#sidebar-delete-selected'));
    });
    $('.delete-selected').on('click', function(e) {
        Omeka.closeSidebar($('#sidebar-delete-all'));
        var inputs = $('input[name="resource_ids[]"]');
        $('#delete-selected-count').text(inputs.filter(':checked').length);
    });
    $('#sidebar-delete-all').on('click', 'input[name="confirm-delete-all-check"]', function(e) {
        $('#confirm-delete-all input[type="submit"]').prop('disabled', this.checked ? false : true);
    });

});
