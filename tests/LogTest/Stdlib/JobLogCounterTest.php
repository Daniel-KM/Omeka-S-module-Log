<?php declare(strict_types=1);

namespace LogTest\Stdlib;

use CommonTest\AbstractHttpControllerTestCase;
use Log\Stdlib\JobLogCounter;
use LogTest\LogTestTrait;

/**
 * Count the logs, the errors and the warnings of the jobs.
 */
class JobLogCounterTest extends AbstractHttpControllerTestCase
{
    use LogTestTrait;

    /**
     * @var int[]
     */
    protected $jobIds = [];

    public function setUp(): void
    {
        parent::setUp();
        // The logs of the jobs are filtered by the visibility of the user.
        $this->loginAdmin();
    }

    public function tearDown(): void
    {
        if ($this->jobIds) {
            $ids = implode(',', $this->jobIds);
            $this->connection()->executeStatement("DELETE FROM `log` WHERE `job_id` IN ($ids)");
            $this->connection()->executeStatement("DELETE FROM `job` WHERE `id` IN ($ids)");
        }
        parent::tearDown();
    }

    public function testCountsFromTheTableOfTheLogs(): void
    {
        $failed = $this->insertJob(null, [6, 3, 2, 4]);
        $warned = $this->insertJob(null, [4, 6]);
        $clean = $this->insertJob(null, [5]);
        $empty = $this->insertJob(null);

        $this->assertSame([
            $failed => ['total' => 4, JobLogCounter::ERRORS => 2, JobLogCounter::WARNINGS => 1],
            $warned => ['total' => 2, JobLogCounter::ERRORS => 0, JobLogCounter::WARNINGS => 1],
            $clean => ['total' => 1, JobLogCounter::ERRORS => 0, JobLogCounter::WARNINGS => 0],
        ], $this->counter()->count([$failed, $warned, $clean, $empty, 0]));
    }

    /**
     * The jobs logged before the module have their log in the job only.
     */
    public function testCountsFromTheLogOfTheJobWithoutLogsInTheTable(): void
    {
        $old = $this->insertJob("2026 INFO (6): Start\n2026 ERR (3): First\n2026 WARN (4): Second\n");
        $logged = $this->insertJob("2026 ERR (3): Ignored, since the table is read\n", [6]);

        $this->assertSame([
            $old => ['total' => 0, JobLogCounter::ERRORS => 1, JobLogCounter::WARNINGS => 1],
            $logged => ['total' => 1, JobLogCounter::ERRORS => 0, JobLogCounter::WARNINGS => 0],
        ], $this->counter()->count([$old, $logged]));
    }

    public function testFilterOfTheSearchOfJobs(): void
    {
        $failed = $this->insertJob(null, [3]);
        $warned = $this->insertJob(null, [4]);
        $old = $this->insertJob("2026 CRIT (2): Old failure\n");
        $this->insertJob(null, [6]);

        $this->assertSame([$failed, $old], $this->searchIds(JobLogCounter::ERRORS));
        $this->assertSame([$warned], $this->searchIds(JobLogCounter::WARNINGS));
    }

    protected function counter(): JobLogCounter
    {
        return new JobLogCounter($this->connection());
    }

    protected function connection(): \Doctrine\DBAL\Connection
    {
        return $this->getApplication()->getServiceManager()->get('Omeka\Connection');
    }

    /**
     * @param int[] $severities Severities of the logs to add in the table.
     */
    protected function insertJob(?string $log, array $severities = []): int
    {
        $connection = $this->connection();
        $connection->executeStatement(
            'INSERT INTO `job` (`status`, `class`, `log`, `started`) VALUES (?, ?, ?, NOW())',
            ['completed', 'LogTest\Job', $log]
        );
        $id = (int) $connection->lastInsertId();
        $this->jobIds[] = $id;
        foreach ($severities as $severity) {
            $connection->executeStatement(
                'INSERT INTO `log` (`job_id`, `reference`, `severity`, `message`, `context`, `created`) VALUES (?, ?, ?, ?, ?, NOW())',
                [$id, 'test', $severity, 'Test', '[]']
            );
        }
        return $id;
    }

    protected function searchIds(string $level): array
    {
        $qb = $this->getApplication()->getServiceManager()->get('Omeka\EntityManager')
            ->createQueryBuilder()
            ->select('omeka_root.id')
            ->from(\Omeka\Entity\Job::class, 'omeka_root')
            ->where('omeka_root.id IN (:ids)')
            ->setParameter('ids', $this->jobIds)
            ->orderBy('omeka_root.id', 'ASC');
        $this->counter()->addFilter($qb, 'omeka_root', $level);
        return array_map('intval', array_column($qb->getQuery()->getScalarResult(), 'id'));
    }
}
