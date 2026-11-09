<?php declare(strict_types=1);

namespace LogTest\Job;

use CommonTest\AbstractHttpControllerTestCase;
use Common\Job\Dispatcher;
use LogTest\LogTestTrait;
use Omeka\Entity\Job;
use Omeka\Job\DispatchStrategy\StrategyInterface;

/**
 * Tests of the job dispatcher of Common with the listener of the module.
 */
class DispatcherTest extends AbstractHttpControllerTestCase
{
    use LogTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
    }

    /**
     * The job returned by the dispatcher is managed by the main entity manager,
     * so a module can store it in a relation and flush, like modules Exports
     * and Osii do.
     *
     * @see https://github.com/Daniel-KM/Omeka-S-module-Log/issues/8
     * @see https://github.com/Daniel-KM/Omeka-S-module-Log/issues/10
     */
    public function testDispatchedJobIsManagedByTheEntityManager(): void
    {
        $services = $this->getServiceLocator();
        $dispatcher = $services->get(\Omeka\Job\Dispatcher::class);
        $this->assertInstanceOf(Dispatcher::class, $dispatcher);

        $strategy = new class implements StrategyInterface {
            public function send(Job $job): void
            {
            }
        };

        $entityManager = $services->get('Omeka\EntityManager');
        $job = $dispatcher->dispatch(\Omeka\Job\BatchDelete::class, ['resource' => 'items', 'ids' => []], $strategy);
        try {
            $this->assertTrue($entityManager->contains($job));
            $this->assertSame(Job::STATUS_STARTING, $job->getStatus());

            // A change on the job is flushed without the doctrine exception
            // "A new entity was found through the relationship".
            $job->setStatus(Job::STATUS_COMPLETED);
            $entityManager->flush();
            $entityManager->clear();
            $this->assertSame(Job::STATUS_COMPLETED, $entityManager->find(Job::class, $job->getId())->getStatus());
        } finally {
            $entityManager->getConnection()->executeStatement('DELETE FROM job WHERE id = ?', [$job->getId()]);
        }
    }

    /**
     * The logs of a job are stored with the job id and the user id, and the job
     * writer is disabled by default.
     */
    public function testLogsOfTheJobHaveTheJobAndTheUser(): void
    {
        $services = $this->getServiceLocator();
        $logger = $services->get('Omeka\Logger');
        $writer = new \Laminas\Log\Writer\Mock();
        $logger->addWriter($writer);

        $strategy = new class($logger) implements StrategyInterface {
            protected $logger;

            public function __construct($logger)
            {
                $this->logger = $logger;
            }

            public function send(Job $job): void
            {
                $this->logger->notice('Message of the job.'); // @translate
            }
        };

        $job = $services->get(\Omeka\Job\Dispatcher::class)
            ->dispatch(\Omeka\Job\BatchDelete::class, ['resource' => 'items', 'ids' => []], $strategy);
        try {
            $event = end($writer->events);
            $this->assertSame($job->getId(), $event['extra']['jobId']);
            $this->assertSame($job->getOwner()->getId(), $event['extra']['userId']);
            $this->assertEmpty($job->getLog());
        } finally {
            $services->get('Omeka\EntityManager')->getConnection()
                ->executeStatement('DELETE FROM job WHERE id = ?', [$job->getId()]);
        }
    }

}
