<?php declare(strict_types=1);

namespace Log;

use Omeka\Stdlib\Message;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Omeka\Api\Manager $api
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$settings = $services->get('Omeka\Settings');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$entityManager = $services->get('Omeka\EntityManager');

if (version_compare($oldVersion, '3.2.1', '<')) {
    $sqls = <<<'SQL'
ALTER TABLE log DROP FOREIGN KEY FK_8F3F68C5A76ED395;
DROP INDEX user_idx ON log;
ALTER TABLE `log` CHANGE `user_id` `owner_id` int(11) NULL AFTER `id`;
ALTER TABLE log ADD CONSTRAINT FK_8F3F68C57E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id) ON DELETE SET NULL;
CREATE INDEX owner_idx ON log (owner_id);
SQL;
    foreach (explode(";\n", $sqls) as $sql) {
        try {
            $connection->executeQuery($sql);
        } catch (\Exception $e) {
        }
    }
}

if (version_compare($oldVersion, '3.3.12.6', '<')) {
    // @link https://www.doctrine-project.org/projects/doctrine-dbal/en/2.6/reference/types.html#array-types
    $sql = <<<'SQL'
ALTER TABLE `log`
CHANGE `context` `context` LONGTEXT NOT NULL COMMENT '(DC2Type:json)';
SQL;
    try {
        $connection->executeQuery($sql);
    } catch (\Exception $e) {
    }
}
