<?php declare(strict_types=1);

namespace Log\Job;

use Common\Stdlib\PsrMessage;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Types;
use Omeka\Job\AbstractJob;

class ArchiveLogsJob extends AbstractJob
{
    /**
     * Limit for the loop to avoid heavy sql requests.
     *
     * @var int
     */
    const SQL_LIMIT = 1000;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    /**
     * @var \Laminas\Mvc\I18n\Translator
     */
    protected $translator;

    public function perform(): void
    {
        $services = $this->getServiceLocator();

        // The reference id is the job id for now.
        $referencesIdProcessor = new \Laminas\Log\Processor\ReferenceId();
        $referencesIdProcessor->setReferenceId('easy-admin/check/job_' . $this->job->getId());

        $this->logger = $services->get('Omeka\Logger');
        $this->logger->addProcessor($referencesIdProcessor);

        $this->connection = $services->get('Omeka\Connection');
        $this->translator = $services->get('MvcTranslator');

        // Init variables.
        $matches = [];

        // Get job arguments.
        $seconds = $this->getArg('seconds') ?: 0;
        $severity = $this->getArg('severity', 0) ?: 0;
        $references = $this->getArg('references', []) ?: [];

        $store = (bool) $this->getArg('store', true);
        $format = $this->getArg('format', 'tsv') ?: 'tsv';
        $compress = (bool) $this->getArg('compress', true);
        $includeId = (bool) $this->getArg('include_id');
        $translateMessage = (bool) $this->getArg('translate', true);

        $deleteJobLogs = (bool) $this->getArg('delete_job_logs', false);

        $delete = (bool) $this->getArg('delete', true);

        if (!is_numeric($seconds)) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $this->logger->err(
                'The argument "seconds" is not set.' // @translate
            );
            return;
        }

        if (!is_array($references)) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $this->logger->err(
                'The argument "reference" should be an array.' // @translate
            );
            return;
        }

        if (!in_array($format, ['tsv', 'tsv_context', 'sql'])) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $this->logger->err(
                'The argument "format" should be "tsv", "tsv_context" or "sql".' // @translate
            );
            return;
        }

        // Prepare args.

        $qb = $this->connection->createQueryBuilder();
        $qb
            ->from('log');

        if ($includeId) {
            $qb
                ->select(
                    'id',
                    'owner_id',
                    'job_id',
                    'reference',
                    'severity',
                    'message',
                    'context',
                    'created',
                );
        } else {
            $qb
                ->select(
                    'owner_id',
                    'job_id',
                    'reference',
                    'severity',
                    'message',
                    'context',
                    'created',
                );
        }

        // Manage age.
        $seconds = (int) $this->getArg('seconds');
        if ($seconds) {
            $date = new \DateTime();
            $date->modify("-$seconds seconds");
            $timestamp = $date->format('Y-m-d H:i:s');
            $qb
                ->where('created < :date')
                ->setParameter('date', $timestamp);
        }

        // Manage severity.
        if ($severity) {
            $severity = (string) $severity;
            if (preg_match('~^>\s*(\d+)$~', $severity, $matches)) {
                $qb
                    ->andWhere('severity > :severity')
                    ->setParameter('severity', (int) $matches[1], Types::INTEGER);
            } elseif (preg_match('~^>=\s*(\d+)$~', $severity, $matches)) {
                $qb
                    ->andWhere('severity >= :severity')
                    ->setParameter('severity', (int) $matches[1], Types::INTEGER);
            } elseif (preg_match('~^<=\s*(\d+)$~', $severity, $matches)) {
                $qb
                    ->andWhere('severity <= :severity')
                    ->setParameter('severity', (int) $matches[1], Types::INTEGER);
            } elseif (preg_match('~^<\s*(\d+)$~', $severity, $matches)) {
                $qb
                    ->andWhere('severity < :severity')
                    ->setParameter('severity', (int) $matches[1], Types::INTEGER);
            } elseif (is_numeric($severity)) {
                $qb
                    ->andWhere('severity = :severity')
                    ->setParameter('severity', (int) $severity, Types::INTEGER);
            }
        }

        // Manage reference.
        if ($references) {
            // Reference is not nullable, so do not check null.
            $referencesExpr = $qb->expr()->orX();
            $refIndex = 1;
            foreach ($references as $ref) {
                $paramName = 'ref_' . $refIndex;
                if (strpos($ref, '*') !== false) {
                    // Pattern matching with wildcards.
                    if (strpos($ref, '*') === 0) {
                        // Start with wildcard like */process.
                        $pattern = '%' . ltrim($ref, '*');
                    } elseif (strrpos($ref, '*') === strlen($ref) - 1) {
                        // End with wildcard like bulk-check/*, not recommended.
                        $pattern = rtrim($ref, '*') . '%';
                    } else {
                        // Wildcard in the middle, not recommended.
                        $pattern = '%' . str_replace('*', '%', $ref) . '%';
                    }
                    $referencesExpr->add("reference NOT LIKE :$paramName");
                    $qb->setParameter($paramName, $pattern);
                } else {
                    // Exact match, recommended.
                    $referencesExpr->add("reference != :$paramName");
                    $qb->setParameter($paramName, $ref);
                }
                ++$refIndex;
            }
            $qb
                ->andWhere($referencesExpr);
        }

        // Keep logs with jobs unless severity is low (info or debug).
        if (!$deleteJobLogs) {
            $qb
                ->andWhere('(job_id IS NULL OR severity >= :keep_job_severity)')
                ->setParameter('keep_job_severity', \Laminas\Log\Logger::INFO, Types::INTEGER);
        }

        $qb
            ->orderBy('id', 'ASC');

        // Add a message for log.
        // Do it early to get the right count.
        if ($store) {
            if ($seconds) {
                $this->logger->notice(
                    'Archive then delete specified logs older than {date_time} with params: severity = {severity}; reference = {references}.', // @translate
                    ['date_time' => $timestamp, 'severity' => $severity ?: '-', 'references' => $references ? implode(', ', $references) : '-']
                );
            } else {
                $this->logger->notice(
                    'Archive then delete specified logs for all dates with params: severity = {severity}; reference = {references}.', // @translate
                    ['severity' => $severity ?: '-', 'references' => $references ? implode(', ', $references) : '-']
                );
            }
        } else {
            if ($seconds) {
                $this->logger->notice(
                    'Delete specified logs older than {date_time} with params: severity = {severity}; reference = {references}.', // @translate
                    ['date_time' => $timestamp, 'severity' => $severity ?: '-', 'references' => $references ? implode(', ', $references) : '-']
                );
            } else {
                $this->logger->notice(
                    'Delete specified logs for all dates with params: severity = {severity}; reference = {references}.', // @translate
                    ['severity' => $severity ?: '-', 'references' => $references ? implode(', ', $references) : '-']
                );
            }
        }

        // Count logs to archive.
        $countQb = clone $qb;
        $countQb
            ->resetQueryPart('select')
            ->resetQueryPart('orderBy')
            ->select('COUNT(id)');
        $count = (int) $countQb->execute()->fetchOne();

        if ($count === 0) {
            $this->logger->notice(
                'No logs found matching criteria.' // @translate
            );
            return;
        }

        $this->logger->notice(
            'Found {count} logs to process.', // @translate
            ['count' => $count]
        );

        if ($store) {
            $filepath = $this->store($qb, [
                'count' => $count,
                'format' => $format,
                'compress' => $compress,
                'includeId' => $includeId,
                'translateMessage' => $translateMessage,
            ]);
            if (!$filepath) {
                // Message is already set.
                // Do not delete if storing is not possible.
                return;
            }
            // The path between store and filename is the prefix.
            $config = $this->getServiceLocator()->get('Config');
            $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
            $dir = pathinfo($filepath, PATHINFO_DIRNAME);
            $filename = pathinfo($filepath, PATHINFO_FILENAME);
            $extension = pathinfo($filepath, PATHINFO_EXTENSION);
            // Stream the archive through the admin controller (the directory is
            // protected by a .htaccess denying direct web access).
            $url = $services->get('ViewHelperManager')->get('url');
            $fileUrl = $url('admin/log/default', ['action' => 'download'], ['query' => ['file' => basename($filepath)]]);
            $this->logger->notice(
                'The backup is available at {link} (size: {size} bytes).', // @translate
                [
                    'link' => sprintf('<a href="%1$s" download="%2$s" target="_self">%2$s</a>', $fileUrl, basename($filepath)),
                    // Space is a narrow no break space.
                    'size' => number_format((int) filesize($filepath), 0, ',', ' '),
                ]
            );
        }

        if ($delete) {
            $deleted = $this->delete($qb);
            $this->logger->notice(
                'Deleted {count} logs from database.', // @translate
                ['count' => (int) $deleted]
            );
        }
    }

    /**
     * Store specified logs.
     *
     * @param QueryBuilder $qb
     * @param array $options The cleaned options.
     *
     * @return string|null The file path if any.
     */
    protected function store(QueryBuilder $qb, array $options): ?string
    {
        $count = $options['count'];
        $format = $options['format'];
        $compress = $options['compress'];
        $includeId = $options['includeId'];
        $translateMessage = $options['translateMessage'];

        $config = $this->getServiceLocator()->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');

        $logDir = $basePath . '/backup/log/';
        if (!$this->checkDestinationDir($logDir)) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            return null;
        }

        // Generate archive file.
        $timestamp = date('Ymd-His');
        $extensions = [
            'sql' => 'sql',
            'tsv' => 'tsv',
            'tsv_context' => 'context.tsv',
        ];
        $extension = $extensions[$format] ?? 'tsv';
        $filename = "logs.$timestamp.$extension";
        $filepath = $basePath . '/backup/log/' . $filename;

        if ($compress) {
            $filename .= '.gz';
            $filepath .= '.gz';
            $fp = gzopen($filepath, 'wb6');
        } else {
            $fp = fopen($filepath, 'wb');
        }

        if (!$fp) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $this->logger->err(
                'Cannot open file {file} for writing. Process is stopped.', // @translate
                ['file' => $filepath]
            );
            return null;
        }

        $escapes = [
            '\\' => '\\\\',
            "\t" => '\t',
            "\n" => '\n',
            "\r" => '\r',
        ];

        // Prepare header based on format.
        switch ($format) {
            default:
            case 'tsv':
                $columns = [
                    'id' => $this->translator->translate('Id'),
                    'created' => $this->translator->translate('Created'),
                    'owner_id' => $this->translator->translate('Owner'),
                    'job_id' => $this->translator->translate('Job'),
                    'reference' => $this->translator->translate('Reference'),
                    'severity' => $this->translator->translate('Severity'),
                    'message' => $this->translator->translate('Message'),
                ];
                if (!$includeId) {
                    unset($columns['id']);
                }
                $columnsTsv = array_combine(array_keys($columns), array_keys($columns));
                $header = implode("\t", $columns) . "\n";
                break;

            case 'tsv_context':
                $columns = [
                    'id' => $this->translator->translate('Id'),
                    'created' => $this->translator->translate('Created'),
                    'owner_id' => $this->translator->translate('Owner'),
                    'job_id' => $this->translator->translate('Job'),
                    'reference' => $this->translator->translate('Reference'),
                    'severity' => $this->translator->translate('Severity'),
                    'message' => $this->translator->translate('Message'),
                    'context' => $this->translator->translate('Context'), // @translate
                ];
                if (!$includeId) {
                    unset($columns['id']);
                }
                $columnsTsv = array_combine(array_keys($columns), array_keys($columns));
                $header = implode("\t", $columns) . "\n";
                break;

            case 'sql':
                $columns = $this->connection->executeQuery('DESCRIBE `log`')->fetchFirstColumn();
                if (!$includeId) {
                    $columns = array_diff($columns, ['id']);
                }
                $columnSql = '`' . implode('`, `', $columns) . '`';
                $header = "-- Log Archive.\n"
                    . "-- Created: " . date('Y-m-d H:i:s') . ".\n"
                    . "-- Records: $count.\n\n";
                break;
        }

        $compress
            ? gzwrite($fp, $header)
            : fwrite($fp, $header);

        // Fetch and write logs in batches to avoid memory issues.
        $offset = 0;
        $isFormatSql = $format === 'sql';
        $isFormatTsv = $format === 'tsv';

        while ($offset < $count) {
            $batchQb = clone $qb;
            $batchQb
                ->setMaxResults(self::SQL_LIMIT)
                ->setFirstResult($offset);

            $logs = $batchQb->execute()->fetchAllAssociative();
            if (empty($logs)) {
                break;
            }

            // Write data for this batch.
            if ($isFormatSql) {
                foreach ($logs as $log) {
                    $values = [];
                    foreach ($log as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } elseif (is_numeric($value)) {
                            $values[] = $value;
                        } else {
                            $values[] = $this->connection->quote((string) $value);
                        }
                    }
                    $valueList = implode(', ', $values);
                    $line = "INSERT INTO `log` ($columnSql) VALUES ($valueList);\n";
                    $compress
                        ? gzwrite($fp, $line)
                        : fwrite($fp, $line);
                }
            } else {
                foreach ($logs as $log) {
                    $values = [];
                    foreach ($columnsTsv as $column) {
                        $value = $log[$column];

                        if ($isFormatTsv && $column === 'message') {
                            // Merge message and context as psr-3 format.
                            $message = $value ?? '';
                            $context = $log['context'] ?? '';
                            if ($context !== '' && $context !== '[]' && $context !== '{}') {
                                $contextData = json_decode($context, true);
                                if (json_last_error() === JSON_ERROR_NONE && is_array($contextData)) {
                                    $message = new PsrMessage($message, $contextData);
                                    $message = $translateMessage
                                        ? $message->setTranslator($this->translator)->translate()
                                        : (string) $message;
                                }
                            } elseif ($translateMessage) {
                                $message = $this->translator->translate($message);
                            }
                            $value = $message;
                        }

                        if ($value === null) {
                            // Representation of null in tsv for direct sql
                            // import.
                            $values[] = $isFormatTsv ? '' : '\N';
                        } else {
                            $values[] = strtr((string) $value, $escapes);
                        }
                    }
                    $line = implode("\t", $values) . "\n";
                    $compress
                        ? gzwrite($fp, $line)
                        : fwrite($fp, $line);
                }
            }

            $offset += self::SQL_LIMIT;

            // Free memory.
            unset($logs);
        }

        $compress
            ? gzclose($fp)
            : fclose($fp);

        return $filepath;
    }

    /**
     * Delete specified logs from database.
     */
    protected function delete(QueryBuilder $qb): int
    {
        $qb
            ->resetQueryPart('select')
            ->resetQueryPart('orderBy')
            ->delete('log');

        $deleted = $qb->execute();

        return (int) $deleted;
    }

    /**
     * Check or create the destination folder.
     *
     * @param string $dirPath Absolute path of the directory to check.
     * @return string|null The dirpath if valid, else null.
     */
    protected function checkDestinationDir(string $dirPath): ?string
    {
        if (file_exists($dirPath)) {
            if (!is_dir($dirPath) || !is_readable($dirPath) || !is_writeable($dirPath)) {
                $this->logger->err(
                    'The directory "{path}" is not writeable.', // @translate
                    ['path' => $dirPath]
                );
                return null;
            }
            return $dirPath;
        }

        $result = @mkdir($dirPath, 0775, true);
        if (!$result) {
            $this->logger->err(
                'The directory "{path}" is not writeable: {error}.', // @translate
                ['path' => $dirPath, 'error' => error_get_last()['message'] ?? 'unknown error']
            );
            return null;
        }
        return $dirPath;
    }
}
