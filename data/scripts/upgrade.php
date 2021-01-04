<?php declare(strict_types=1);
namespace Log;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $serviceLocator
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Doctrine\DBAL\Connection $connection
 */
$services = $serviceLocator;
$connection = $services->get('Omeka\Connection');

if (version_compare($oldVersion, '3.2.1', '<')) {
    $sql = <<<'SQL'
ALTER TABLE log DROP FOREIGN KEY FK_8F3F68C5A76ED395;
DROP INDEX user_idx ON log;
ALTER TABLE `log` CHANGE `user_id` `owner_id` int(11) NULL AFTER `id`;
ALTER TABLE log ADD CONSTRAINT FK_8F3F68C57E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id) ON DELETE SET NULL;
CREATE INDEX owner_idx ON log (owner_id);
SQL;
    $connection->exec($sql);
}

if (version_compare($oldVersion, '3.3.12.6', '<')) {
    // @link https://www.doctrine-project.org/projects/doctrine-dbal/en/2.6/reference/types.html#array-types
    $sql = <<<'SQL'
ALTER TABLE `log`
CHANGE `context` `context` LONGTEXT NOT NULL COMMENT '(DC2Type:json)';
SQL;
    $connection->exec($sql);
}
