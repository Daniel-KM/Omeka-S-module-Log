<?php declare(strict_types=1);

namespace Log\Processor;

use Laminas\Log\Processor\ProcessorInterface;
use Omeka\Entity\Job;

class UserJobId implements ProcessorInterface
{
    /**
     * @var int
     */
    protected $userId;

    /**
     * @var int
     */
    protected $jobId;

    /**
     * @param Job $job
     */
    public function __construct(Job $job)
    {
        $this->jobId = $job->getId();
        $owner = $job->getOwner();
        if ($owner) {
            $this->userId = $owner->getId();
        }
    }

    /**
     * Adds the user and the job id to the log, even if it has already been set
     * manually, in order to keep consistency in database.
     *
     * @param array $event event data
     * @return array event data
     */
    public function process(array $event)
    {
        if (!isset($event['extra'])) {
            $event['extra'] = [];
        }

        $event['extra']['userId'] = $this->userId;
        $event['extra']['jobId'] = $this->jobId;

        return $event;
    }
}
