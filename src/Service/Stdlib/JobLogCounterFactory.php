<?php declare(strict_types=1);

namespace Log\Service\Stdlib;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Log\Stdlib\JobLogCounter;
use Psr\Container\ContainerInterface;

class JobLogCounterFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new JobLogCounter($services->get('Omeka\Connection'));
    }
}
