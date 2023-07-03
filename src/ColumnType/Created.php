<?php declare(strict_types=1);

namespace Log\ColumnType;

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
}
