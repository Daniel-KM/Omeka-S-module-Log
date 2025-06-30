'use strict';

document.addEventListener('DOMContentLoaded', function () {

    /**
     * Update the state of each job in the log table.
     *
     * An optimisation is possible with one query for all jobs, but useless in most of the cases.
     * So simply cache last states for the same job ids.
     */
    const jobStates = document.querySelectorAll('.job-state[data-job-id]');
    const requestCache = new Map();

    const updateJobStates = () => {
        if (!jobStates) return;

        jobStates.forEach(jobState => {
            const jobId = jobState.getAttribute('data-job-id');
            const jobDataState = jobState.getAttribute('data-job-state');
            const jobStateUrl = jobState.getAttribute('data-job-state-url');
            if (!jobId || !jobStateUrl || !jobDataState || jobDataState === '' || jobDataState === 'Zombie') return;

            const labelElement = jobState.querySelector('.system-state-label');
            const iconElement = jobState.querySelector('.system-state-icon');
            if (!labelElement && !iconElement) return;

            // Check if the jobId is already being fetched.
            if (requestCache.has(jobId)) return;

            // Fetch the job state and cache the promise.
            const fetchPromise = fetch(jobStateUrl)
                .then(response => response.json())
                .then(data => {
                    if (data?.status === 'success') {
                        updateDom(jobId, data);
                    } else {
                        clearDom(jobId);
                    }
                })
                .catch(error => {
                    console.error('Error updating system state:', error);
                    clearDom(jobId);
                })
                .finally(() => {
                    // Remove the jobId from the cache.
                    requestCache.delete(jobId);
                });

            requestCache.set(jobId, fetchPromise);
        });
    };

    const updateDom = (jobId, data) => {
        if (data?.data?.['job']?.['o:system_state']) {
            const state= data.data['job']['o:system_state'].state;
            const label= data.data['job']['o:system_state'].label;
            const icon = data.data['job']['o:system_state'].icon;

            // Find all elements with the same job id.
            const matchingElements = document.querySelectorAll(`.job-state[data-job-id="${jobId}"]`);
            matchingElements.forEach(jobState => {
                const labelEl = jobState.querySelector('.system-state-label');
                const iconEl = jobState.querySelector('.system-state-icon');
                jobState.setAttribute('data-job-state', state);
                if (labelEl) {
                    labelEl.textContent = label;
                }
                if (iconEl) {
                    iconEl.className = `system-state-icon ${icon}`;
                    iconEl.setAttribute('title', label);
                    iconEl.setAttribute('aria-label', label);
                }
            });
        } else {
            clearDom(jobId);
        }

        if (data?.data?.['job']?.['o:status_label']) {
            const statusLabel = data.data['job']['o:status_label'];
            document.querySelectorAll(`.job-status[data-job-id="${jobId}"]`).forEach(jobStatus => {
                const labelEl = jobStatus.querySelector('.job-status-label');
                if (labelEl) {
                    const linkEl = labelEl.querySelector('a');
                    if (linkEl) {
                        linkEl.textContent = statusLabel;
                    } else {
                        labelEl.textContent = statusLabel;
                    }
                }
            });
        }
    };

    const clearDom = (jobId) => {
        // Find all elements with the same job id.
        const matchingElements = document.querySelectorAll(`.job-state[data-job-id="${jobId}"]`);

        matchingElements.forEach(jobState => {
            const labelEl = jobState.querySelector('.system-state-label');
            const iconEl = jobState.querySelector('.system-state-icon');
            jobState.setAttribute('data-job-state', '');
            if (labelEl) {
                labelEl.textContent = '';
            }
            if (iconEl) {
                iconEl.className = 'system-state-icon';
                iconEl.setAttribute('title', '');
                iconEl.setAttribute('aria-label', '');
            }
        });
    };

    const uniqueJobIds = new Set();
    for (const jobState of jobStates) {
        uniqueJobIds.add(jobState.getAttribute('data-job-id'));
    }

    // Update each system state every 1 to 10 seconds.
    setInterval(updateJobStates, uniqueJobIds.size < 5 ? 1000 : 10000);

});
