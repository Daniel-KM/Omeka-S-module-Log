<?php declare(strict_types=1);

namespace Log\Mvc\Controller\Plugin;

use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

class JobState extends AbstractPlugin
{
    /**
     * @var \Log\Stdlib\JobState
     */
    protected $jobState;

    public function __construct(\Log\Stdlib\JobState $jobState)
    {
        $this->jobState = $jobState;
    }

    /**
     * Get the state of a running or stopping job.
     *
     * Windows is not supported (neither in omeka job anyway).
     *
     * Linux states are:
     * - R: Running
     * - S: Interruptible Sleep (Sleep, waiting for event from software)
     * - D: Uninterruptible Sleep (Dead, waiting for signal from hardware)
     * - T: Stopped (Traced)
     * - Z: Zombie
     *
     * Warning: in some cases, the state is not reliable, because it may be the
     * one of another process.
     *
     * @param \Omeka\Api\Representation\JobRepresentation|\Omeka\Entity\Job|null $job
     * @return string|null Letter of the state of the process or null.
     * Full state can be retrieved from the constant STATES.
     *
     * @uses \Log\Stdlib\JobState
     */
    public function __invoke($job): ?string
    {
        return $this->jobState->__invoke($job);
    }
}
