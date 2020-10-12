<?php declare(strict_types=1);
namespace Log\Service\Processor;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Log\Processor\UserId;

class UserIdFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $user = $services->get('Omeka\AuthenticationService')->getIdentity();
        return new UserId($user);
    }
}
