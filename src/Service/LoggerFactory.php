<?php
namespace Log\Service;

use Interop\Container\ContainerInterface;
use Log\Processor\UserId;
use Log\Service\Processor\UserIdFactory;
use Zend\Log\Logger;
use Zend\Log\Writer\Noop;
use Zend\ServiceManager\Factory\FactoryInterface;

/**
 * Logger factory.
 */
class LoggerFactory implements FactoryInterface
{
    /**
     * Create the logger service.
     *
     * @return Logger
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $config = $services->get('Config');

        if (empty($config['logger']['log'])) {
            return (new Logger)->addWriter(new Noop);
        }

        $enabledWriters = array_filter($config['logger']['writers']);
        $writers = array_intersect_key($config['logger']['options']['writers'], $enabledWriters);
        if (empty($writers)) {
            return (new Logger)->addWriter(new Noop);
        }

        if (!empty($writers['stream'])) {
            if (isset($config['logger']['priority'])) {
                $writers['stream']['options']['filters'] = $config['logger']['priority'];
            }
            if (isset($config['logger']['path'])) {
                $writers['stream']['options']['stream'] = $config['logger']['path'];
            }
        }

        if (!empty($writers['db']) && empty($writers['db']['options']['db'])) {
            $dbAdapter = $this->getDbAdapter($services);
            if ($dbAdapter) {
                $writers['db']['options']['db'] = $dbAdapter;
            } else {
                trigger_error(
                    'Database logging disabled: wrong config.',
                    E_USER_WARNING
                );
                unset($writers['db']);
                if (empty($writers)) {
                    return (new Logger)->addWriter(new Noop);
                }
            }
        }

        // TODO The facile sentry sender should be autoloaded automagically.
        if (isset($writers['sentry'])) {
            if (empty($writers['sentry']['options']['sender'])
                || $writers['sentry']['options']['sender'] === \Facile\Sentry\Common\Sender\SenderInterface::class
            ) {
                $writers['sentry']['options']['sender'] = $services->get(\Facile\Sentry\Common\Sender\SenderInterface::class);
            }
        }

        $config['logger']['options']['writers'] = $writers;
        if (!empty($config['logger']['options']['processors']['userid']['name'])) {
            $config['logger']['options']['processors']['userid']['name'] = $this->addUserIdProcessor($services);
        }

        // Checks are managed via the constructor.
        return new Logger($config['logger']['options']);
    }

    /**
     * Get the database param.
     *
     * For performance, flexibility and stability reasons, the write process
     * uses a specific Zend Db adapter. The read/delete process in api or ui
     * uses the default doctrine entity manager.
     * @todo Use a second entity manager to manage logs in a isolated database.
     *
     * @param ContainerInterface $services
     * @return  \Zend\Db\Adapter\AdapterInterface
     */
    protected function getDbAdapter(ContainerInterface $services)
    {
        $iniConfigPath = OMEKA_PATH . '/config/database-log.ini';
        if (file_exists($iniConfigPath) && is_readable($iniConfigPath)) {
            $reader = new \Zend\Config\Reader\Ini;
            $iniConfig = $reader->fromFile($iniConfigPath);
        } else {
            $iniConfig = $services->get('Omeka\Connection')->getParams();
        }

        $dbConfig = [
            'driver' => 'Mysqli',
            'database' => $iniConfig['dbname'],
            'username' => $iniConfig['user'],
            'password' => $iniConfig['password'],
        ];
        if (!empty($iniConfig['unix_socket'])) {
            $dbConfig['unix_socket'] = $iniConfig['unix_socket'];
        } else {
            $dbConfig['host'] = $iniConfig['host'];
            if (!empty($dbConfig['port'])) {
                $dbConfig['port'] = $iniConfig['port'];
            }
        }

        return new \Zend\Db\Adapter\Adapter($dbConfig);
    }

    /**
     * Add the log processor to add the current user id.
     *
     * @todo Load the user id log processor via log_processors.
     * @param ContainerInterface $services
     * @return UserId
     */
    protected function addUserIdProcessor(ContainerInterface $services)
    {
        $userIdFactory = new UserIdFactory();
        return $userIdFactory($services, '');
    }
}
