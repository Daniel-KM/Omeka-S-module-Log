<?php declare(strict_types=1);

namespace Log\ColumnType;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\ColumnType\ColumnTypeInterface;

class Severity implements ColumnTypeInterface
{
    public function getLabel(): string
    {
        return 'Severity'; // @translate
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
        return 'severity';
    }

    public function renderHeader(PhpRenderer $view, array $data): string
    {
        return $this->getLabel();
    }

    public function renderContent(PhpRenderer $view, AbstractEntityRepresentation $resource, array $data): ?string
    {
        /** @var \Log\Api\Representation\LogRepresentation $log */
        $log = $resource;

        $plugins = $view->getHelperPluginManager();
        $url = $plugins->get('url');
        $translate = $plugins->get('translate');
        $hyperlink = $plugins->get('hyperlink');

        return $hyperlink($translate($log->severityLabel()), $url(null, [], ['query' => ['page' => null, 'severity' => $log->severity()] + $view->params()->fromQuery()], true));
    }
}
