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
            $connection->executeStatement($sql);
        } catch (\Exception $e) {
        }
    }
}

if (version_compare($oldVersion, '3.3.12.6', '<')) {
    // @link https://www.doctrine-project.org/projects/doctrine-dbal/en/2.6/reference/types.html#array-types
    $sql = <<<'SQL'
ALTER TABLE `log` CHANGE `context` `context` LONGTEXT NOT NULL COMMENT '(DC2Type:json)';
SQL;
    try {
        $connection->executeStatement($sql);
    } catch (\Exception $e) {
    }
}

if (version_compare($oldVersion, '3.4.16', '<')) {
    // Create index first to avoid issue on foreign keys.
    $sqls = <<<'SQL'
CREATE INDEX IDX_8F3F68C57E3C61F9 ON `log` (`owner_id`);
CREATE INDEX IDX_8F3F68C5BE04EA9 ON `log` (`job_id`);
CREATE INDEX IDX_8F3F68C5AEA34913 ON `log` (`reference`);
CREATE INDEX IDX_8F3F68C5F660D16B ON `log` (`severity`);

DROP INDEX owner_idx ON `log`;
DROP INDEX job_idx ON `log`;
DROP INDEX reference_idx ON `log`;
DROP INDEX severity_idx ON `log`;
SQL;
    /*
    $sqls = array_filter(explode(";\n", $sqls));
    $connection->transactional(function($connection) use ($sqls) {
        foreach ((array) $sqls as $sql) {
            $connection->executeStatement($sql);
        }
    });
    */
    foreach (array_filter(explode(";\n", $sqls)) as $sql) {
        try {
            $connection->executeStatement($sql);
        } catch (\Exception $e) {
        }
    }
}

if (version_compare($oldVersion, '3.4.18', '<')) {
    $translator = $services->get('MvcTranslator');
    $message = new Message(
        $translator->translate('Support of the third party service Sentry was moved to a separate module, %1$sLog Sentry%2$s.'), // @translate
        '<a href="https://gitlab.com/Daniel-KM/Omeka-S-module-LogSentry" target="_blank" rel="noopener">', '</a>'
    );
    $message->setEscapeHtml(false);
    $messenger->addWarning($message);
}
