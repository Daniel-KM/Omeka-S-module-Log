<?php declare(strict_types=1);

namespace Log\ColumnType;

class Owner extends \Omeka\ColumnType\Owner
{
    public function getResourceTypes(): array
    {
        return [
            'logs',
        ];
    }

    public function getSortBy(array $data): ?string
    {
        // TODO Sort by owner name.
        return 'owner_id';
    }
}
