<?php declare(strict_types=1);

namespace Log\ColumnType;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractEntityRepresentation;

class Created extends \Omeka\ColumnType\Created

{
    public function getLabel(): string
    {
        return 'Created'; // @translate
    }

    public function getResourceTypes(): array
    {
        return [
            'logs',
        ];
    }

    public function renderContent(PhpRenderer $view, AbstractEntityRepresentation $resource, array $data) : ?string
    {
        return $view->i18n()->dateFormat($resource->created(), 'medium', 'medium');
    }
}
