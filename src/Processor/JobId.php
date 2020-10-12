<?php declare(strict_types=1);

namespace Log\Processor;

use Laminas\Log\Processor\ProcessorInterface;
use Omeka\Entity\Job;

class JobId implements ProcessorInterface
{
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
    }

    /**
     * Adds the job id to the log, even if it has already been set manually, in
     * order to keep consistency in database.
     *
     * @param array $event event data
     * @return array event data
     */
    public function process(array $event)
    {
        if (!isset($event['extra'])) {
            $event['extra'] = [];
        }

        $event['extra']['jobId'] = $this->jobId;

        return $event;
    }
}
