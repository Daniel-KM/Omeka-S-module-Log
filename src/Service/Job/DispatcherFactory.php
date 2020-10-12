<?php declare(strict_types=1);
namespace Log\Service\Job;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Log\Job\Dispatcher;

class DispatcherFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $dispatcher = new Dispatcher(
            $services->get('Omeka\Job\DispatchStrategy'),
            $services->get('Omeka\EntityManager'),
            $services->get('Omeka\Logger'),
            $services->get('Omeka\AuthenticationService')
        );
        $dispatcher->useJobWriter($services->get('Config')['logger']['writers']['job']);
        return $dispatcher;
    }
}
