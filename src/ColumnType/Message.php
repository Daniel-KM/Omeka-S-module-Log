<?php declare(strict_types=1);

namespace Log\ColumnType;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\ColumnType\ColumnTypeInterface;

class Message implements ColumnTypeInterface
{
    public function getLabel(): string
    {
        return 'Message'; // @translate
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
        return 'message';
    }

    public function renderHeader(PhpRenderer $view, array $data): string
    {
        return $this->getLabel();
    }

    public function renderContent(PhpRenderer $view, AbstractEntityRepresentation $resource, array $data): ?string
    {
        /** @var \Log\Api\Representation\LogRepresentation $log */
        $log = $resource;
        return $view->escapeHtml($log->text()->translate());
    }
}
