<?php declare(strict_types=1);
namespace Log\Job;

use DateTime;
use Doctrine\ORM\EntityManager;
use Log\Log\Writer\Job as JobWriter;
use Omeka\Entity\Job;
use Omeka\Job\DispatchStrategy\StrategyInterface;

class Dispatcher extends \Omeka\Job\Dispatcher
{
    /**
     * @var bool
     */
    protected $useJobWriter;

    public function send(Job $job, StrategyInterface $strategy): void
    {
        // Keep the default writer if wanted.
        if ($this->useJobWriter) {
            $this->logger->addWriter(new JobWriter($job));
        }

        // Enable the user and job id in the default logger.
        $userJobIdProcessor = new \Log\Processor\UserJobId($job);
        // The priority "0" fixes a precedency issue with the processor UserId.
        $this->logger->addProcessor($userJobIdProcessor, 0);

        // Copy from parent method.

        try {
            $strategy->send($job);
        } catch (\Exception $e) {
            $this->logger->err((string) $e);

            // Account for "inside Doctrine" errors that close the EM
            if ($this->entityManager->isOpen()) {
                $em = $this->entityManager;
            } else {
                $em = $this->getNewEntityManager($this->entityManager);
            }

            // Reload job that may have been updated during process, but keep
            // the logs since the job object itself is up-to-date.
            $em->clear();
            $jobEntity = $em->find(Job::class, $job->getId());
            $jobEntity->setLog($job->getLog());
            $jobEntity->setStatus(Job::STATUS_ERROR);
            $jobEntity->setEnded(new DateTime('now'));
            $em->flush($jobEntity);
        }
    }

    /**
     * Get a new EntityManager sharing the settings of an old one.
     *
     * Internal Doctrine errors "close" the EntityManager and we can never use it again, so we need
     * to create a new one if we want to save anything after one of those kinds of errors.
     *
     * Note: Copied from parent, because the method is private.
     *
     * @param EntityManager $entityManager
     * @return EntityManager
     */
    private function getNewEntityManager(EntityManager $entityManager)
    {
        return EntityManager::create(
            $entityManager->getConnection(),
            $entityManager->getConfiguration(),
            $entityManager->getEventManager()
        );
    }

    public function useJobWriter($useJobWriter): void
    {
        $this->useJobWriter = $useJobWriter;
    }
}
