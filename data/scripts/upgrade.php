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
$translator = $services->get('MvcTranslator');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$siteSettings = $services->get('Omeka\Settings\Site');
$entityManager = $services->get('Omeka\EntityManager');

if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.90')) {
    $message = new \Omeka\Stdlib\Message(
        $translator->translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
        'Common', '3.4.90'
    );
    $messenger->addError($message);
    throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $translator('Missing requirement. Unable to upgrade.')); // @translate
}

if (version_compare($oldVersion, '3.2.1', '<')) {
    $sqls = <<<'SQL'
        ALTER TABLE `log` DROP FOREIGN KEY FK_8F3F68C5A76ED395;
        DROP INDEX user_idx ON `log`;
        ALTER TABLE `log` CHANGE `user_id` `owner_id` int(11) NULL AFTER `id`;
        ALTER TABLE `log` ADD CONSTRAINT FK_8F3F68C57E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id) ON DELETE SET NULL;
        SQL;
    foreach (explode(";\n", $sqls) as $sql) {
        try {
            $connection->executeStatement($sql);
        } catch (\Throwable $e) {
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
    } catch (\Throwable $e) {
        // Already created.
    }
}

if (version_compare($oldVersion, '3.4.18', '<')) {
    $message = new PsrMessage(
        'Support of the third party service Sentry was moved to a separate module, {link}Log Sentry{link_end}.', // @translate
        ['link' => '<a href="https://gitlab.com/Daniel-KM/Omeka-S-module-LogSentry" target="_blank" rel="noopener">', 'link_end' => '</a>']
    );
    $message->setEscapeHtml(false);
    $messenger->addWarning($message);
}

if (version_compare($oldVersion, '3.4.33', '<')) {
    // Cron, store and delete are disabled on upgrade to let the admin chooses.
    // Furthermore, the first process may be intensive.
    $settings->set('log_cron_days', 0);
    $settings->set('log_archive_days', 180);
    $settings->set('log_archive_severity_max', 0);
    $settings->set('log_archive_delete_job_logs', false);
    $settings->set('log_archive_references', []);
    $settings->set('log_archive_store', false);
    $settings->set('log_archive_format', 'tsv');
    $settings->set('log_archive_compress', true);
    $settings->set('log_archive_include_id', false);
    $settings->set('log_archive_translate', true);
    $settings->set('log_archive_delete', false);
    $settings->set('log_cron_last', 0);

    $message = new PsrMessage(
        'Logs can be archived and purged regularly. Go to {link}config form{link_end} for params.', // @translate
        ['link' => sprintf('<a href="%s">', htmlspecialchars($url->fromRoute('admin/default', ['controler' => 'module', 'action' => 'configure'], ['query' => ['id' => 'Log']], true))), 'link_end' => '</a>']
    );
    $message->setEscapeHtml(false);
    $messenger->addWarning($message);

    $message = new PsrMessage(
        'A regular deletion of old logs is recommended to keep omeka fluid.' // @translate
    );
    $messenger->addWarning($message);
}

if (version_compare($oldVersion, '3.4.35', '<')) {
    $settings->set(
        'log_archive_delete_job_logs',
        (bool) $settings->get('log_archive_delete_job_logs', false)
    );
}

/**
 * In all cases, check the directory to store logs.
 */

// Create but not forbid install, because storing is not required.
$config = $this->getServiceLocator()->get('Config');
$basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
if (!$this->checkDestinationDir($basePath . '/backup/log')) {
    $message = new PsrMessage(
        'The directory "{directory}" is not writeable, so old logs cannot be archived.', // @translate
        ['directory' => $basePath . '/backup/log']
    );
    $messenger->addWarning($message);
}

/**
 * In all cases, check indexes and run the job if needed.
 *
 * @see \Common\Module::fixIndexes().
 */

// Check if all indices exists.
$indexColumns = [
    'IDX_8F3F68C57E3C61F9' => 'owner_id',
    'IDX_8F3F68C5BE04EA9' => 'job_id',
    'IDX_8F3F68C5AEA34913' => 'reference',
    'IDX_8F3F68C5F660D16B' => 'severity',
    'IDX_8F3F68C5B23DB7B8' => 'created',
];

$newIndices = [];
foreach ($indexColumns as $index => $column) {
    $stmt = $connection->executeQuery("SHOW INDEX FROM `log` WHERE `column_name` = '$column';");
    $result = $stmt->fetchAssociative();
    if (!$result || $result['Key_name'] !== $index) {
        $newIndices[$index] = $column;
    }
}

$indexOlds = [
    'user_idx' => 'owner_id',
    'owner_idx' => 'owner_id',
    'job_idx' => 'job_id',
    'reference_idx' => 'reference',
    'severity_idx' => 'severity',
];

$indexToRemove = [];
foreach ($indexOlds as $index => $column) {
    $stmt = $connection->executeQuery("SHOW INDEX FROM `log` WHERE `column_name` = '$column';");
    $result = $stmt->fetchAssociative();
    if ($result && $result['Key_name'] === $index) {
        $indexToRemove[$index] = $column;
    }
}

if ($newIndices || $indexToRemove) {
    // Dispatch background job: the log table can be very large. Temporarily
    // mark the module as active so the PHP-CLI process can bootstrap it.
    require_once dirname(__DIR__, 2) . '/src/Job/LogIndexes.php';

    $moduleId = 'Log';
    $moduleRow = $connection->executeQuery(
        'SELECT `is_active` FROM `module` WHERE `id` = ?',
        [$moduleId]
    )->fetchAssociative();
    $wasActive = (bool) ($moduleRow['is_active'] ?? false);

    $connection->executeStatement(
        'UPDATE `module`'
        . ' SET `version` = ?, `is_active` = 1'
        . ' WHERE `id` = ?',
        [$newVersion, $moduleId]
    );

    $dispatcher = $services->get('Omeka\Job\Dispatcher');
    $job = $dispatcher->dispatch(\Log\Job\LogIndexes::class);

    sleep(5);

    $status = $connection->executeQuery(
        'SELECT `status` FROM `job` WHERE `id` = ?',
        [$job->getId()]
    )->fetchOne();
    if ($status === \Omeka\Entity\Job::STATUS_STARTING) {
        $messenger->addWarning(new PsrMessage(
            'The job #{job_id} is still starting. It may need to be relaunched manually.', // @translate
            ['job_id' => $job->getId()]
        ));
    }

    if (!$wasActive) {
        $connection->executeStatement(
            'UPDATE `module` SET `is_active` = 0 WHERE `id` = ?',
            [$moduleId]
        );
    }

    $message = new PsrMessage(
        'A background job has been started to fix database indexes. Missing indexes: {list}.', // @translate
        ['list' => json_encode(array_values($newIndices))]
    );
    $messenger->addWarning($message);
}
