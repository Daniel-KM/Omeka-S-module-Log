<?php declare(strict_types=1);

namespace Log\ColumnType;

class Id extends \Omeka\ColumnType\Id

{
    public function getLabel(): string
    {
        return 'Id'; // @translate
    }

    public function getResourceTypes(): array
    {
        return [
            'logs',
        ];
    }
}
