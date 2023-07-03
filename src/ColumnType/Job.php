<?php declare(strict_types=1);

namespace Log\ColumnType;

use Laminas\View\Renderer\PhpRenderer;
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
        /** @var \Log\Api\Representation\LogRepresentation $log */
        $log = $resource;
        $job = $log->job();
        if (!$job) {
            return null;
        }

        $plugins = $view->getHelperPluginManager();
        $url = $plugins->get('url');
        $translate = $plugins->get('translate');
        $hyperlink = $plugins->get('hyperlink');

        $linkStatus = $hyperlink($translate($job->statusLabel()), $url(null, [], ['query' => ['job_id' => $job->id()]], true));
        $linkParams = $hyperlink($translate('Parameters'), $url('admin/id', ['controller' => 'job', 'action' => 'show', 'id' => $job->id()]));
        $linkJobLog = $job->log()
            ? $hyperlink($translate('Log'), $url('admin/id', ['controller' => 'job', 'action' => 'log', 'id' => $job->id()]), ['target' => '_blank'])
            : null;
        $html = <<<HTML
<div>
    <span class="log-job-status">$linkStatus</span>
    <span class="log-job-param">$linkParams</span>
    <span class="log-job-log">$linkJobLog</span>
</div>
HTML;
        return $html;
    }
}
