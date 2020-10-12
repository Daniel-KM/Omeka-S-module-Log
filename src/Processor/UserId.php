<?php declare(strict_types=1);

namespace Log\Processor;

use Laminas\Log\Processor\ProcessorInterface;
use Omeka\Entity\User;

class UserId implements ProcessorInterface
{
    /**
     * @var int|null
     */
    protected $userId;

    /**
     * @param int|null $userId
     */
    public function __construct(User $user = null)
    {
        if ($user) {
            $this->userId = $user->getId();
        }
    }

    /**
     * Adds the user id to the log, even if it has already been set manually, in
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

        $event['extra']['userId'] = $this->userId;

        return $event;
    }
}
