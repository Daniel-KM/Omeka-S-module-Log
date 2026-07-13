<?php declare(strict_types=1);

namespace Log\ColumnType;

use Laminas\View\Renderer\PhpRenderer;
use Log\Stdlib\JobState;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\ColumnType\ColumnTypeInterface;

class Job implements ColumnTypeInterface
{
    public function getLabel(): string
    {
        return 'Job'; // @translate
    }

    public function getResourceTypes(): array
    {
        return [
            'logs',
        ];
    }

    public function getMaxColumns(): ?int
    {
        return 1;
    }

    public function renderDataForm(PhpRenderer $view, array $data): string
    {
        return '';
    }

    public function getSortBy(array $data): ?string
    {
        return 'job_id';
    }

    public function renderHeader(PhpRenderer $view, array $data): string
    {
        return $this->getLabel();
    }

    public function renderContent(PhpRenderer $view, AbstractEntityRepresentation $resource, array $data): ?string
    {
        // Cache states per job: multiple logs may share the same job, and the
        // live spinner of a running job must be rendered only once (see below).
        static $jobStates = [];

        /** @var \Log\Api\Representation\LogRepresentation $log */
        $log = $resource;
        $job = $log->job();
        if (!$job) {
            return null;
        }

        $plugins = $view->getHelperPluginManager();
        $url = $plugins->get('url');
        $jobState = $plugins->get('jobState');
        $translate = $plugins->get('translate');
        $hyperlink = $plugins->get('hyperlink');

        $replace = [
            '__STATE__' => '',
            '__LINK_LOG__' => '',
        ];

        $linkStatus = $hyperlink($translate($job->statusLabel()), $url(null, [], ['query' => ['job_id' => $job->id()]], true));
        $linkParams = $hyperlink($translate('Parameters'), $url('admin/id', ['controller' => 'job', 'action' => 'show', 'id' => $job->id()]));

        $jobId = $job->id();
        if (!array_key_exists($jobId, $jobStates)) {
            $jobStates[$jobId] = ['state' => $jobState($job), 'spinnerDone' => false, 'icon' => null];
        }
        $state = $jobStates[$jobId]['state'];

        $buildState = function () use ($plugins, $url, $translate, $jobId, $state): string {
            $escape = $plugins->get('escapeHtml');
            $escapeAttr = $plugins->get('escapeHtmlAttr');
            $jobStateUrlAttr = JobState::STATES[$state]['processing']
                ? 'data-job-state-url="' . $url('admin/job-state', ['id' => $jobId]) . '"'
                : '';
            $stateWarning = $escapeAttr($translate('Warning: The system state may not be reliable on some servers.'));
            $stateWarning = sprintf(' title="%1$s" aria-label="%1$s"', $stateWarning);
            $stateIcon = JobState::STATES[$state]['icon'];
            $stateLabel = $translate(JobState::STATES[$state]['label']);
            $stateLabelEsc = $escape($stateLabel);
            $stateLabelEscAttr = $escapeAttr($stateLabel);
            return <<<HTML
                <span class="job-state" data-job-id="$jobId" data-job-state="$state" $jobStateUrlAttr $stateWarning>
                    <span class="system-state-label">$stateLabelEsc</span>
                    <span class="system-state-icon $stateIcon" title="$stateLabelEscAttr" aria-label="$stateLabelEscAttr"></span>
                </span>
                HTML;
        };

        if ($state) {
            if (JobState::STATES[$state]['processing']) {
                // A running job with several logs would show one animated
                // spinner per row, looking like several parallel tasks. So the
                // live spinner is rendered only on the first log of the job;
                // the other rows keep the textual status only, which is still
                // updated live by job.js.
                if (!$jobStates[$jobId]['spinnerDone']) {
                    $replace['__STATE__'] = $buildState();
                    $jobStates[$jobId]['spinnerDone'] = true;
                }
            } else {
                // A static status icon (finished job) is not misleading, so it
                // is repeated on every row.
                if ($jobStates[$jobId]['icon'] === null) {
                    $jobStates[$jobId]['icon'] = $buildState();
                }
                $replace['__STATE__'] = $jobStates[$jobId]['icon'];
            }
        }

        if (isset($jobStates[$jobId]['log'])) {
            $replace['__LINK_LOG__'] = $jobStates[$jobId]['log'];
        } elseif ($job->log()) {
            $linkJobLog = $hyperlink($translate('Log'), $url('admin/id', ['controller' => 'job', 'action' => 'log', 'id' => $job->id()]), ['target' => '_blank']);
            $replace['__LINK_LOG__'] = <<<HTML
                <span class="log-job-log">$linkJobLog</span>
                HTML;
            $jobStates[$jobId]['log'] = $replace['__LINK_LOG__'];
        }

        $html = <<<HTML
            <div class="log-job job-status" data-job-id="$jobId">
                <span class="log-job-status job-status-label">$linkStatus</span>
                __STATE__
                <span class="log-job-param">$linkParams</span>
                __LINK_LOG__
            </div>
            HTML;

        return strtr($html, $replace);
    }
}
