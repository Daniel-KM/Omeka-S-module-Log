<?php declare(strict_types=1);

namespace Log\ColumnType;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\ColumnType\ColumnTypeInterface;
use Log\Stdlib\JobState;

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
        // Avoid to recall job states when multiple logs has the same job.
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
        $state = null;
        if (isset($jobStates[$jobId]['state'])) {
            $replace['__STATE__'] = $jobStates[$jobId]['state'];
        } elseif ($state = $jobState($job)) {
            $escape = $plugins->get('escapeHtmlAttr');
            $escapeAttr = $plugins->get('escapeHtmlAttr');
            if (JobState::STATES[$state]['processing']) {
                $jobStateUrl = $url('admin/job-state', ['id' => $jobId]);
                $jobStateUrlAttr = 'data-job-state-url="' . $jobStateUrl . '"';
            } else {
                $jobStateUrlAttr = '';
            }
            $stateWarning = $escapeAttr($translate('Warning: The system state may not be reliable on some servers.'));
            $stateWarning = sprintf(' title="%1$s" aria-label="%1$s"', $stateWarning);
            $stateIcon = JobState::STATES[$state]['icon'];
            $stateLabel = $translate(JobState::STATES[$state]['label']);
            $stateLabelEsc = $escape($stateLabel);
            $stateLabelEscAttr = $escapeAttr($stateLabel);
            $replace['__STATE__'] = <<<HTML
                <span class="job-state" $jobStateUrlAttr data-job-id="$jobId"$stateWarning>
                    <span class="system-state-label">$stateLabelEsc</span>
                    <span class="system-state-icon $stateIcon" title="$stateLabelEscAttr" aria-label="$stateLabelEscAttr"></span>
                </span>
                HTML;
            $jobStates[$jobId]['state'] = $replace['__STATE__'];
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
            <div class="log-job job-status">
                <span class="log-job-status job-status-label">$linkStatus</span>
                __STATE__
                <span class="log-job-param">$linkParams</span>
                __LINK_LOG__
            </div>
            HTML;

        return strtr($html, $replace);
    }
}
