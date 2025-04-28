<?php declare(strict_types=1);

namespace Log;

use Common\Stdlib\PsrMessage;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Omeka\Api\Manager $api
 * @var \Omeka\View\Helper\Url $url
 * @var \Laminas\Log\Logger $logger
 * @var \Omeka\Settings\Settings $settings
 * @var \Laminas\I18n\View\Helper\Translate $translate
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Laminas\Mvc\I18n\Translator $translator
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Settings\SiteSettings $siteSettings
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$url = $plugins->get('url');
$api = $plugins->get('api');
$logger = $services->get('Omeka\Logger');
$settings = $services->get('Omeka\Settings');
$translate = $plugins->get('translate');
$translator = $services->get('MvcTranslator');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$siteSettings = $services->get('Omeka\Settings\Site');
$entityManager = $services->get('Omeka\EntityManager');

if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.66')) {
    $message = new \Omeka\Stdlib\Message(
        $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
        'Common', '3.4.66'
    );
    throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
}

if (PHP_VERSION_ID >= 80200) {
    $content = file_get_contents(dirname(__DIR__, 2) . '/vendor/laminas/laminas-db/composer.json');
    if (strpos($content, '"php": "^7.3 ||') ) {
        $message = new PsrMessage(
            'The library is not compatible with the version of php on the server. Run "composer upgrade" on the command line or load a version for php ≥ 8.2.' // @translate
        );
        throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message->setTranslator($translator));
    }
}

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
            // Already created.
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
        // Already created.
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
            // Already created.
        }
    }
}

if (version_compare($oldVersion, '3.4.18', '<')) {
    $message = new PsrMessage(
        $translate('Support of the third party service Sentry was moved to a separate module, {link}Log Sentry{link_end}.'), // @translate
        ['link' => '<a href="https://gitlab.com/Daniel-KM/Omeka-S-module-LogSentry" target="_blank" rel="noopener">', 'link_end' => '</a>']
    );
    $message->setEscapeHtml(false);
    $messenger->addWarning($message);
}

if (version_compare($oldVersion, '3.4.28', '<')) {
    // Create index first to avoid issue on foreign keys.
    $sql = <<<'SQL'
        CREATE INDEX IDX_8F3F68C5B23DB7B8 ON `log` (`created`);
        SQL;
    try {
        $connection->executeStatement($sql);
    } catch (\Exception $e) {
        // Already created.
    }
}
