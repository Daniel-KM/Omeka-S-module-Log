<?php declare(strict_types=1);
namespace Log\Service\Job\DispatchStrategy;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Log\Job\DispatchStrategy\Synchronous;

class SynchronousFactory implements FactoryInterface
{
    /**
     * Create the PhpCli strategy service.
     *
     * @return Synchronous
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new Synchronous($services);
    }
}
