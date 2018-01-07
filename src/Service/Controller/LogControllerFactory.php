<?php
namespace Log\Service\Controller;

use Interop\Container\ContainerInterface;
use Log\Controller\LogController;
use Zend\ServiceManager\Factory\FactoryInterface;

class LogControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new LogController(
            $services->get('Omeka\Job\Dispatcher')
        );
    }
}
