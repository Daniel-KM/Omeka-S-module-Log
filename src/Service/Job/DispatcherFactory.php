<?php
namespace Log\Service\Job;

use Interop\Container\ContainerInterface;
use Log\Job\Dispatcher;
use Zend\ServiceManager\Factory\FactoryInterface;

class DispatcherFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new Dispatcher(
            $services->get('Omeka\Job\DispatchStrategy'),
            $services->get('Omeka\EntityManager'),
            $services->get('Omeka\Logger'),
            $services->get('Omeka\AuthenticationService')
        );
    }
}
