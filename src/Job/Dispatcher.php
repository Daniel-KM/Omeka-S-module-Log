<?php
namespace Log\Job;

use DateTime;
use Omeka\Job\DispatchStrategy\StrategyInterface;
use Omeka\Entity\Job;

class Dispatcher extends \Omeka\Job\Dispatcher
{
    public function send(Job $job, StrategyInterface $strategy)
    {
        // Disable the default writer.
        // $this->logger->addWriter(new JobWriter($job));
        // Enable the user and job id in the default logger.
        $userJobIdProcessor = new \Log\Processor\UserJobId($job);
        // The priority "0" fixes a precedency issue with the processor UserId.
        $this->logger->addProcessor($userJobIdProcessor, 0);

        // Copy from parent method.

        try {
            $strategy->send($job);
        } catch (\Exception $e) {
            $this->logger->err((string) $e);
            $job->setStatus(Job::STATUS_ERROR);
            $job->setEnded(new DateTime('now'));

            // Account for "inside Doctrine" errors that close the EM
            if ($this->entityManager->isOpen()) {
                $entityManager = $this->entityManager;
            } else {
                $entityManager = $this->getNewEntityManager($this->entityManager);
            }

            $entityManager->clear();
            $entityManager->merge($job);
            $entityManager->flush();
        }
    }
}
