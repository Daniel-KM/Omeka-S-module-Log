<?php declare(strict_types=1);

/**
 * Script to archive logs manually or via a cron task.
 *
 * Adapted from omeka perform-job.php.
 */

// Find the root of Omeka. The directory of the module may be a symbolic link,
// for example when modules are shared between instances, and php resolves it in
// __DIR__, so the path used to call the script is checked first, without
// resolving it. The module may be installed in "modules/" or in
// "composer-addons/modules/".
$scriptPath = $_SERVER['SCRIPT_FILENAME'] ?? '';
if ($scriptPath !== '' && $scriptPath[0] !== '/') {
    // The variable PWD of the shell keeps the symbolic links, unlike getcwd().
    $scriptPath = (getenv('PWD') ?: getcwd()) . '/' . $scriptPath;
}
$scriptPath = preg_replace('~/(?:\./)+~', '/', $scriptPath);
$omekaPath = null;
foreach ([
    $scriptPath ? dirname($scriptPath, 4) : null,
    $scriptPath ? dirname($scriptPath, 5) : null,
    dirname(__DIR__, 4),
    dirname(__DIR__, 5),
] as $path) {
    if ($path && is_file($path . '/bootstrap.php') && is_file($path . '/application/Module.php')) {
        $omekaPath = $path;
        break;
    }
}
if (!$omekaPath) {
    fwrite(STDERR, 'The root of Omeka cannot be found from the path of this script.' . PHP_EOL);
    exit(1);
}
unset($scriptPath, $path);
require $omekaPath . '/bootstrap.php';

/**
 * @var \Omeka\Job\Dispatcher $dispatcher
 */

$application = Omeka\Mvc\Application::init(require 'application/config/application.config.php');
$services = $application->getServiceManager();

$logger = $services->get('Omeka\Logger');
$settings = $services->get('Omeka\Settings');

$options = getopt('', ['base-path:', 'server-url:']);
if (!isset($options['server-url'])) {
    $logger->err('No server URL given; use --server-url <serverUrl>');
    exit;
}

// Prepare base path and base url for new logs.
$viewHelperManager = $services->get('ViewHelperManager');
$viewHelperManager->get('BasePath')->setBasePath($options['base-path'] ?? '/');
$services->get('Router')->setBaseUrl($options['base-path'] ?? '/');

$serverUrlParts = parse_url($options['server-url']);
$scheme = $serverUrlParts['scheme'];
$host = $serverUrlParts['host'];
if (isset($serverUrlParts['port'])) {
    $port = $serverUrlParts['port'];
} elseif ($serverUrlParts['scheme'] === 'http') {
    $port = 80;
} elseif ($serverUrlParts['scheme'] === 'https') {
    $port = 443;
} else {
    $port = null;
}
$serverUrlHelper = $viewHelperManager->get('ServerUrl');
$serverUrlHelper->setPort($port);
$serverUrlHelper->setScheme($scheme);
$serverUrlHelper->setHost($host);

// From here all processing is synchronous.
$strategy = $services->get(\Omeka\Job\DispatchStrategy\Synchronous::class);

$dispatcher = $services->get(\Omeka\Job\Dispatcher::class);
$dispatcher
    ->dispatch(
        \Log\Job\ArchiveLogsJob::class,
        [
            'seconds' => $settings->get('log_archive_days', 0) * 86400,
            'severity' => $settings->get('log_archive_severity', 0) ?: 0,
            'references' => $settings->get('log_archive_references') ?: [],

            'store' => (bool) $settings->get('log_archive_store', true),
            'format' => $settings->get('log_archive_format', 'tsv'),
            'compress' => (bool) $settings->get('log_archive_compress', true),
            'include_id' => (bool) $settings->get('log_archive_include_id', false),
            'translate' => (bool) $settings->get('log_archive_translate', true),

            'delete' => (bool) $settings->get('log_archive_delete', true),
        ],
        $strategy
    );
