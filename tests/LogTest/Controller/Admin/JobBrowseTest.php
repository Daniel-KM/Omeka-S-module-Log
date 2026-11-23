<?php declare(strict_types=1);

namespace LogTest\Controller\Admin;

use CommonTest\AbstractHttpControllerTestCase;
use LogTest\LogTestTrait;

/**
 * Display the errors and warnings logged by the jobs in their list.
 */
class JobBrowseTest extends AbstractHttpControllerTestCase
{
    use LogTestTrait;

    /**
     * @var int[]
     */
    protected $jobIds = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
    }

    public function tearDown(): void
    {
        if ($this->jobIds) {
            $ids = implode(',', $this->jobIds);
            $connection = $this->getServiceLocator()->get('Omeka\Connection');
            $connection->executeStatement("DELETE FROM `log` WHERE `job_id` IN ($ids)");
            $connection->executeStatement("DELETE FROM `job` WHERE `id` IN ($ids)");
        }
        parent::tearDown();
    }

    public function testCountsAndFiltersAreDisplayed(): void
    {
        $failed = $this->insertJob([3, 3, 4]);
        $clean = $this->insertJob([6]);

        $this->dispatch('/admin/job');
        $this->assertResponseStatusCode(200);
        $this->assertQuery('th select.job-filter option[selected]');
        $this->assertQuery(sprintf('td.job-status[data-job-id="%d"] a.job-log-errors', $failed));
        $this->assertQuery(sprintf('td.job-status[data-job-id="%d"] a.job-log-warnings', $failed));
        $this->assertNotQuery(sprintf('td.job-status[data-job-id="%d"] a.job-log-count', $clean));
    }

    public function testFilterOfTheJobsWithErrors(): void
    {
        $failed = $this->insertJob([3]);
        $clean = $this->insertJob([6]);

        $this->dispatch('/admin/job', 'GET', ['log_level' => 'errors']);
        $this->assertResponseStatusCode(200);
        $this->assertQuery(sprintf('td.job-status[data-job-id="%d"]', $failed));
        $this->assertNotQuery(sprintf('td.job-status[data-job-id="%d"]', $clean));
    }

    /**
     * @param int[] $severities
     */
    protected function insertJob(array $severities): int
    {
        $connection = $this->getServiceLocator()->get('Omeka\Connection');
        $connection->executeStatement(
            'INSERT INTO `job` (`status`, `class`, `started`) VALUES (?, ?, NOW())',
            ['completed', 'LogTest\Job']
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
}
