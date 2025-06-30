<?php declare(strict_types=1);

namespace Log\Stdlib;

use Doctrine\ORM\EntityManager;
use Omeka\Api\Representation\JobRepresentation;
use Omeka\Entity\Job;

class JobState
{
    const STATES = [
        // Processing is one of the three first states.
        'R' => [
            's' => 'R',
            'state' => 'Running',
            'label' => 'Running',
            'icon' => 'fas fa-sync fa-spin',
            'processing' => true,
        ],
        'S' => [
            's' => 'S',
            'state' => 'Interruptible Sleep',
            'label' => 'Sleeping',
            'icon' => 'fas fa-spinner fa-spin',
            'processing' => true,
        ],
        'D' => [
            's' => 'D',
            'state' => 'Uninterruptible Sleep',
            'label' => 'Waiting',
            'icon' => 'fas fa-hourglass-half',
            'processing' => true,
        ],
        // These states mean the job is not running.
        'T' => [
            's' => 'T',
            'state' => 'Stopped',
            'label' => 'Stopped',
            'icon' => 'far fa-pause-circle',
            'processing' => false,
        ],
        'Z' => [
            's' => 'Z',
            'state' => 'Zombie',
            'label' => 'Ended',
            'icon' => 'fas fa-times',
            'processing' => false,
        ],
    ];

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
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
     * @param JobRepresentation|Job|null $job
     * @return string|null Letter of the state of the process or null.
     * Full state can be retrieved from the constant STATES.
     */
    public function __invoke($job): ?string
    {
        if (!extension_loaded('posix')) {
            return null;
        }

        if (!is_object($job)) {
            return null;
        }

        if ($job instanceof JobRepresentation) {
            // The job representation cannot access to the pid, so get the
            // entity, that is already in the doctrine cache.
            $job = $this->entityManager->find(Job::class, $job->id());
        } elseif (!$job instanceof Job) {
            return null;
        }

        // In omeka, the pid is a string, but is is an integer for system.
        $pid = (int) $job->getPid();
        $status = $job->getStatus();

        // A job without pid that writes log? Maybe a cron task not started.
        if (!$pid || !$status) {
            return null;
        }

        // A job with a status stopped or ended cannot have a pid and the pid
        // may be the one of another process, in particular for old jobs or
        // after a system reboot.
        $livingJobStatuses = [
            // "Starting" is used until the job get a pid and is really run.
            Job::STATUS_STOPPING,
            Job::STATUS_IN_PROGRESS,
        ];

        if (!in_array($status, $livingJobStatuses)) {
            return null;
        }

        // It may be not reliable via a web server. So just check file content.
        /*
        // Check if the process exists.
        if (!posix_kill($pid, 0)) {
            return null;
        }
        */

        // Get the status of the process.
        $statusFile = "/proc/$pid/status";
        if (!file_exists($statusFile)) {
            return null;
        }

        $matches = [];
        $statusContent = file_get_contents($statusFile);
        if (preg_match('~^State:\s+(?<state>[RSDTZ]).*$~m', $statusContent, $matches)) {
            $state = $matches['state'];
            return isset(self::STATES[$state]) ? $state : null;
        }

        // Normally not possible. Maybe check windows.
        return null;
    }
}
