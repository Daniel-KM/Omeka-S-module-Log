<?php
namespace Log\Job\DispatchStrategy;

use Doctrine\ORM\EntityManager;
use Omeka\Entity\Job;
use Zend\Log\Logger;

class Synchronous extends \Omeka\Job\DispatchStrategy\Synchronous
{
    /**
     * Copy of parent method, but with psr message and full logging.
     * Logger may be null in order to manage various versions of core.
     *
     * @inheritdoc
     */
    public function handleFatalError(Job $job, EntityManager $entityManager, Logger $logger = null)
    {
        $lastError = error_get_last();
        if ($lastError) {
            if (is_null($logger)) {
                $logger = $this->serviceLocator->get('Omeka\Logger');
            }

            $errors = [E_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];
            if (in_array($lastError['type'], $errors)) {
                $logger->err(
                    'Fatal error: {message}\nin {file} on line {line}', // @translate
                    [
                        'message' => $lastError['message'],
                        'file' => $lastError['file'],
                        'line' => $lastError['line'],
                    ]
                );

                $job->setStatus(Job::STATUS_ERROR);

                // Make sure we only flush this Job and nothing else
                $entityManager->clear();
                $entityManager->merge($job);
                $entityManager->flush();
            }
            // Log other errors according to the config for severity.
            else {
                $logger->warn(
                    'Warning: {message}\nin {file} on line {line}', // @translate
                    [
                        'message' => $lastError['message'],
                        'file' => $lastError['file'],
                        'line' => $lastError['line'],
                    ]
                );
            }
        }
    }
}
