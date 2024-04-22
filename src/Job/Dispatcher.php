<?php declare(strict_types=1);

namespace Log\Job;

use DateTime;
use Doctrine\ORM\EntityManager;
use Omeka\Entity\Job;
use Omeka\Job\DispatchStrategy\StrategyInterface;
use Omeka\Job\Exception;
use Omeka\Log\Writer\Job as JobWriter;

class Dispatcher extends \Omeka\Job\Dispatcher
{
    /**
     * @var bool
     */
    protected $useJobWriter;

    /**
     * Refresh owner before flush.
     *
     * {@inheritDoc}
     * @see \Omeka\Job\Dispatcher::dispatch()
     */
    public function dispatch($class, $args = null, StrategyInterface $strategy = null)
    {
        if (!class_exists($class)) {
            throw new Exception\InvalidArgumentException(sprintf('The job class "%s" does not exist.', $class));
        }
        if (!is_subclass_of($class, 'Omeka\Job\JobInterface')) {
            throw new Exception\InvalidArgumentException(sprintf('The job class "%s" does not implement Omeka\Job\JobInterface.', $class));
        }

        // Refresh owner and use reference only to avoid doctrine issue when a
        // job dispatches another job. Use a new entity manager too.
        $this->entityManager = $this->getNewEntityManager($this->entityManager);

        $owner = $this->auth->getIdentity();
        $owner = $owner
            ? $this->entityManager->getReference(\Omeka\Entity\User::class, $owner->getId())
            : null;

        $job = new Job;
        $job->setStatus(Job::STATUS_STARTING);
        $job->setClass($class);
        $job->setArgs($args);
        $job->setOwner($owner);
        $this->entityManager->persist($job);
        $this->entityManager->flush();

        if (!$strategy) {
            $strategy = $this->getDispatchStrategy();
        }

        $this->send($job, $strategy);
        return $job;
    }

    /**
     * Better management of job during process.
     *
     * {@inheritDoc}
     * @see \Omeka\Job\Dispatcher::send()
     */
    public function send(Job $job, StrategyInterface $strategy): void
    {
        // Keep the default writer if wanted.
        if ($this->useJobWriter) {
            $writer = new JobWriter($job);
            $writer->setFormatter(new \Common\Log\Formatter\PsrLogSimple);
            $this->logger->addWriter($writer);
        }

        // Enable the user and job id in the default logger.
        $userJobIdProcessor = new \Log\Log\Processor\UserJobId($job);
        // The priority "0" fixes a precedency issue with the processor UserId.
        $this->logger->addProcessor($userJobIdProcessor, 0);

        // Adapted from parent method.

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
            try {
                $jobEntity = $em->find(Job::class, $job->getId());
            } catch (\Exception $e) {
                return;
            }

            $jobEntity->setLog($job->getLog());
            $jobEntity->setStatus(Job::STATUS_ERROR);
            $jobEntity->setEnded(new DateTime('now'));
            $em->persist($jobEntity);
            $em->flush();
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
