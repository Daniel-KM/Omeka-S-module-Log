<?php declare(strict_types=1);
namespace Log\Service\ControllerPlugin;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Omeka\Mvc\Controller\Plugin\Logger;

class LoggerDbFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new Logger($services->get('Log\LoggerDb'));
    }
}
