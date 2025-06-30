<?php declare(strict_types=1);

namespace Log\Service\Stdlib;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Log\Stdlib\JobState;

/**
 * JobState factory.
 */
class JobStateFactory implements FactoryInterface
{
    /**
     * Create the JobState service.
     *
     * @return JobState
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new JobState(
            $services->get('Omeka\EntityManager')
        );
    }
}
