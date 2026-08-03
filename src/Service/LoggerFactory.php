<?php declare(strict_types=1);

namespace Log\Service;

use Common\Log\Formatter\PsrLogSimple;
use Doctrine\DBAL\Connection;
use Laminas\Http\PhpEnvironment\Request as HttpRequest;
use Laminas\Log\Exception;
use Laminas\Log\Filter\Priority;
use Laminas\Log\Logger;
use Laminas\Log\Writer\Noop;
use Laminas\Log\Writer\Stream;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Log\Log\Processor\HttpRequest as HttpRequestProcessor;
use Log\Log\Processor\UserId;
use Log\Log\Writer\Doctrine as DoctrineWriter;
use Log\Service\Log\Processor\UserIdFactory;
use Psr\Container\ContainerInterface;

/**
 * Logger factory.
 */
class LoggerFactory implements FactoryInterface
{
    /**
     * Create the logger service.
     *
     * @return Logger
     */
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        $config = $services->get('Config');

        // Create logger instance.
        $logger = new Logger();

        if (empty($config['logger']['log'])) {
            return $logger->addWriter(new Noop());
        }

        $enabledWriters = array_filter($config['logger']['writers']);
        $writers = array_intersect_key($config['logger']['options']['writers'], $enabledWriters);

        if (empty($writers)) {
            $logger->addWriter(new Noop());
            return $logger;
        }

        // Handle Omeka default stream writer (for compatibility).
        if (!empty($writers['stream'])) {
            $streamWriter = $this->createStreamWriter($config, $writers['stream']);
            if ($streamWriter) {
                $logger->addWriter($streamWriter);
            }
            unset($writers['stream']);
        }

        // Handle database writer with Doctrine.
        if (!empty($writers['doctrine'])) {
            $connection = $this->getDoctrineConnection($services);
            if ($connection) {
                $doctrineWriter = $this->createDoctrineWriter($connection, $writers['doctrine']);
                $logger->addWriter($doctrineWriter);
            } else {
                error_log('[Omeka S] Database logging disabled: connection unavailable.'); // @translate
            }
            unset($writers['doctrine']);
        }

        // Add user id processor if configured.
        // This adds userId to the extra array for all writers.
        if (!empty($config['logger']['options']['processors']['userid']['name'])) {
            $logger->addProcessor($this->addUserIdProcessor($services));
        }

        // Add HTTP request processor if configured.
        // This adds url, ip, referer and user agent to the context for all HTTP
        // log entries. Skipped in cli jobs.
        if (!empty($config['logger']['options']['processors']['httprequest']['name'])) {
            try {
                $logger->addProcessor($this->addHttpRequestProcessor($services));
            } catch (\Throwable $e) {
                error_log('[Omeka S] HTTP request processor unavailable: ' . $e->getMessage());
            }
        }

        // Processors are handled manually above, so remove them to avoid
        // double-instantiation via InvokableFactory when preparing remaining
        // writers.
        unset(
            $config['logger']['options']['processors']['userid'],
            $config['logger']['options']['processors']['httprequest']
        );

        // Handle any remaining writers from config (like Sentry writer in
        // module LogSentry).
        if (!empty($writers)) {
            // Apply the psr-3 formatter to writers that dont set any.
            $psrFormatter = new PsrLogSimple('%timestamp% %priorityName% (%priority%): %message% %extra%');
            foreach ($writers as &$writerConfig) {
                if (empty($writerConfig['options']['formatter'])) {
                    $writerConfig['options']['formatter'] = $psrFormatter;
                }
            }
            unset($writerConfig);

            $config['logger']['options']['writers'] = $writers;
            $tempLogger = new Logger($config['logger']['options']);
            foreach ($tempLogger->getWriters() as $writer) {
                $logger->addWriter($writer);
            }
        }

        // If no writers were added, add Noop.
        if (!count($logger->getWriters())) {
            $logger->addWriter(new Noop());
        }

        return $logger;
    }

    /**
     * Create stream writer with Omeka-compatible formatting and filtering.
     */
    protected function createStreamWriter(array $config, array $writerConfig): ?Stream
    {
        // Determine stream path.
        $stream = $writerConfig['options']['stream']
            ?? $config['logger']['path']
            ?? null;

        if (!$stream) {
            return null;
        }

        // Override with logger path if set (Omeka compatibility).
        if (isset($config['logger']['path'])) {
            $stream = $config['logger']['path'];
        }

        // Check if stream is writeable (for file streams).
        if (is_file($stream) && !is_writeable($stream)) {
            error_log('[Omeka S] File logging disabled: not writeable.'); // @translate
            return null;
        }

        try {
            $writer = new Stream($stream);
            $writer->setFormatter(new PsrLogSimple('%timestamp% %priorityName% (%priority%): %message% %extra%'));
            $priority = $writerConfig['options']['filters']
                ?? $config['logger']['priority']
                ?? Logger::WARN;
            $filter = new Priority($priority);
            $writer->addFilter($filter);
        } catch (Exception\RuntimeException $e) {
            error_log('Omeka S log initialization failed: ' . $e->getMessage());
            return null;
        }

        return $writer;
    }

    /**
     * Create a Doctrine writer from configuration.
     */
    protected function createDoctrineWriter(Connection $connection, array $writerConfig): ?DoctrineWriter
    {
        $tableName = $writerConfig['options']['table'] ?? 'log';
        $columnMap = $writerConfig['options']['column'] ?? null;
        $separator = $writerConfig['options']['separator'] ?? null;

        // Create writer with connection, table, and column mapping.
        $doctrineWriter = new DoctrineWriter($connection, $tableName, $columnMap, $separator);

        // Set formatter if specified.
        if (!empty($writerConfig['options']['formatter'])) {
            $formatterClass = $writerConfig['options']['formatter'];
            $formatter = new $formatterClass();
            $doctrineWriter->setFormatter($formatter);
        }

        // Add filters if specified.
        if (!empty($writerConfig['options']['filters'])) {
            $filters = $writerConfig['options']['filters'];
            if (!is_array($filters)) {
                $filters = [$filters];
            }
            foreach ($filters as $filter) {
                $doctrineWriter->addFilter($filter);
            }
        }

        return $doctrineWriter;
    }

    /**
     * Get Doctrine DBAL connection.
     *
     * Supports external database via config/database-log.ini.
     * If no external config exists, uses Omeka's main database connection.
     *
     * To disable the database, set `"db" => false` in the module config.
     *
     * For performance, flexibility and stability reasons, the write process
     * uses Doctrine DBAL. The read/delete process in api or ui uses the
     * default doctrine entity manager.
     */
    protected function getDoctrineConnection(ContainerInterface $services): ?Connection
    {
        $iniConfigPath = OMEKA_PATH . '/config/database-log.ini';

        // Check for external database configuration.
        if (file_exists($iniConfigPath) && is_readable($iniConfigPath)) {
            try {
                $reader = new \Laminas\Config\Reader\Ini;
                $iniConfig = $reader->fromFile($iniConfigPath);
                $iniConfig = array_filter($iniConfig);

                if (!empty($iniConfig)) {
                    return $this->createConnectionFromConfig($iniConfig);
                }
            } catch (\Throwable $e) {
                error_log('[Omeka S] Failed to read database-log.ini: ' . $e->getMessage());
            }
        }

        // Use Omeka's main database connection.
        try {
            return $services->get('Omeka\Connection');
        } catch (\Throwable $e) {
            error_log('[Omeka S] Failed to get Doctrine connection: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create a Doctrine DBAL connection from INI configuration.
     *
     * @param array $config Configuration array from database-log.ini.
     * @return \Doctrine\DBAL\Connection
     * @throws \Doctrine\DBAL\Exception
     */
    protected function createConnectionFromConfig(array $config)
    {
        // Map INI config to Doctrine DBAL parameters.
        $params = [
            'dbname' => $config['database'] ?? $config['dbname'] ?? null,
            'user' => $config['username'] ?? $config['user'] ?? null,
            'password' => $config['password'] ?? null,
            'host' => $config['host'] ?? 'localhost',
            'driver' => 'pdo_mysql',
        ];

        // Add port if specified.
        if (!empty($config['port'])) {
            $params['port'] = (int) $config['port'];
        }

        // Add unix socket if specified.
        if (!empty($config['unix_socket'])) {
            $params['unix_socket'] = $config['unix_socket'];
            unset($params['host']); // Unix socket takes precedence over host.
        }

        // Add charset if specified.
        if (!empty($config['charset'])) {
            $params['charset'] = $config['charset'];
        }

        // Add driver options (e.g., SSL certificates).
        if (!empty($config['driverOptions']) || !empty($config['driver_options'])) {
            $params['driverOptions'] = $config['driverOptions'] ?? $config['driver_options'];
        }

        // Create connection using Doctrine DBAL.
        return \Doctrine\DBAL\DriverManager::getConnection($params);
    }

    /**
     * Get the database params, or the Omeka database params (deprecated).
     *
     * @deprecated No longer used. Kept for backwards compatibility.
     * To disable the database, set `"db" => false` in the module config.
     *
     * For performance, flexibility and stability reasons, the write process now
     * uses Doctrine DBAL instead of a specific Laminas Db adapter.
     * The read/delete process in api or ui uses the default doctrine entity manager.
     *
     * @param ContainerInterface $services
     * @return \Doctrine\DBAL\Connection|null
     */
    protected function getDbAdapter(ContainerInterface $services)
    {
        // Return Doctrine connection for backwards compatibility.
        return $this->getDoctrineConnection($services);
    }

    /**
     * Add the log processor to add the current user id.
     *
     * @todo Load the user id log processor via log_processors.
     * @param ContainerInterface $services
     * @return UserId
     */
    protected function addUserIdProcessor(ContainerInterface $services)
    {
        $userIdFactory = new UserIdFactory();
        return $userIdFactory($services, '');
    }

    /**
     * Add the log processor for HTTP request context.
     *
     * @param ContainerInterface $services
     * @return HttpRequestProcessor
     */
    protected function addHttpRequestProcessor(ContainerInterface $services)
    {
        $request = $services->get('Request');
        return new HttpRequestProcessor(
            $request instanceof HttpRequest ? $request : null
        );
    }
}
