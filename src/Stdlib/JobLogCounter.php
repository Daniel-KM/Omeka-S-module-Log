<?php declare(strict_types=1);

namespace Log\Stdlib;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\QueryBuilder;

/**
 * Count the logs, the errors and the warnings of jobs.
 *
 * The jobs logged before the module have their log in the job only.
 */
class JobLogCounter
{
    const ERRORS = 'errors';
    const WARNINGS = 'warnings';

    protected const SEVERITIES = [
        self::ERRORS => 'BETWEEN 0 AND 3',
        self::WARNINGS => '= 4',
    ];

    protected const MARKERS = [
        self::ERRORS => ['EMERG (0): ', 'ALERT (1): ', 'CRIT (2): ', 'ERR (3): '],
        self::WARNINGS => ['WARN (4): '],
    ];

    /**
     * @var Connection
     */
    protected $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param int[] $jobIds
     * @return array Counts by job id, with keys "total", "errors" and
     *   "warnings", without the jobs without logs.
     */
    public function count(array $jobIds): array
    {
        $jobIds = array_values(array_unique(array_filter(array_map('intval', $jobIds))));
        if (!$jobIds) {
            return [];
        }

        $rows = $this->connection->executeQuery(
            'SELECT `job_id` AS `id`, COUNT(`id`) AS `total`,'
                . ' SUM(CASE WHEN `severity` ' . self::SEVERITIES[self::ERRORS] . ' THEN 1 ELSE 0 END) AS `' . self::ERRORS . '`,'
                . ' SUM(CASE WHEN `severity` ' . self::SEVERITIES[self::WARNINGS] . ' THEN 1 ELSE 0 END) AS `' . self::WARNINGS . '`'
                . ' FROM `log` WHERE `job_id` IN (:ids) GROUP BY `job_id`',
            ['ids' => $jobIds],
            ['ids' => Connection::PARAM_INT_ARRAY]
        )->fetchAllAssociative();
        $rows = array_column($rows, null, 'id');

        $jobIds = array_values(array_diff($jobIds, array_keys($rows)));
        if ($jobIds) {
            $selects = [];
            foreach (self::MARKERS as $level => $markers) {
                $parts = [];
                foreach ($markers as $marker) {
                    $quoted = $this->connection->quote($marker);
                    $parts[] = "(CHAR_LENGTH(`log`) - CHAR_LENGTH(REPLACE(`log`, $quoted, ''))) / CHAR_LENGTH($quoted)";
                }
                $selects[] = 'COALESCE(' . implode(' + ', $parts) . ', 0) AS `' . $level . '`';
            }
            $rowsJobs = $this->connection->executeQuery(
                'SELECT `id`, 0 AS `total`, ' . implode(', ', $selects) . ' FROM `job` WHERE `id` IN (:ids)',
                ['ids' => $jobIds],
                ['ids' => Connection::PARAM_INT_ARRAY]
            )->fetchAllAssociative();
            $rows += array_column($rowsJobs, null, 'id');
        }

        $result = [];
        foreach ($rows as $id => $row) {
            $counts = [
                'total' => (int) $row['total'],
                self::ERRORS => (int) $row[self::ERRORS],
                self::WARNINGS => (int) $row[self::WARNINGS],
            ];
            if (array_filter($counts)) {
                $result[(int) $id] = $counts;
            }
        }
        ksort($result);
        return $result;
    }

    /**
     * Limit a search of jobs to the ones with logged errors or warnings.
     *
     * The logs follow the visibility filter, so the user should be identified.
     */
    public function addFilter(QueryBuilder $qb, string $alias, string $level): void
    {
        if (!isset(self::MARKERS[$level])) {
            return;
        }
        $expr = $qb->expr();
        $logAlias = 'log_job_log_' . $level;
        $conditions = [sprintf(
            'EXISTS (SELECT %1$s.id FROM Log\Entity\Log %1$s WHERE %1$s.job = %2$s.id AND %1$s.severity %3$s)',
            $logAlias, $alias, self::SEVERITIES[$level]
        )];
        foreach (self::MARKERS[$level] as $index => $marker) {
            $param = $logAlias . '_' . $index;
            $qb->setParameter($param, '%' . $marker . '%');
            $conditions[] = $expr->like($alias . '.log', ':' . $param);
        }
        $qb->andWhere($expr->orX(...$conditions));
    }
}
