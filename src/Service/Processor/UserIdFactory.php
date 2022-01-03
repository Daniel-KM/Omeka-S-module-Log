<?php declare(strict_types=1);

namespace Log\Service\Processor;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Log\Processor\UserId;

class UserIdFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        // When an external authentication is used, the user cannot be stored,
        // because an infinite loop is created between authentication that
        // requires logging, and logging, that requires a user.
        // FIXME Make compatible logging with external authentication.
        $modules = [
            'Shibboleth',
            'CAS',
            'Ldap',
        ];
        /** @var \Omeka\Module\Manager $moduleManager */
        $moduleManager = $services->get('Omeka\ModuleManager');
        $hasExternalAuthentication = false;
        foreach ($modules as $module) {
            $module = $moduleManager->getModule($module);
            if ($module && $module->getState() === \Omeka\Module\Manager::STATE_ACTIVE) {
                $hasExternalAuthentication = true;
                break;
            }
        }
        return new UserId(
            $hasExternalAuthentication
                ? null
                : $services->get('Omeka\AuthenticationService')->getIdentity()
        );
    }
}
