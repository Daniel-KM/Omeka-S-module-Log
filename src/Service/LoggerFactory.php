<?php declare(strict_types=1);

namespace Log\Service;

use Interop\Container\ContainerInterface;
use Laminas\Log\Logger;
use Laminas\Log\Writer\Noop;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Log\Log\Processor\UserId;
use Log\Service\Log\Processor\UserIdFactory;

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

        // For compatibility with Omeka default config, that may be customized.
        if (!empty($writers['stream'])) {
            if (isset($config['logger']['priority'])) {
                $writers['stream']['options']['filters'] = $config['logger']['priority'];
            }
            if (isset($config['logger']['path'])) {
                $writers['stream']['options']['stream'] = $config['logger']['path'];
            }

            if (!is_file($writers['stream']['options']['stream'])
                || !is_writeable($writers['stream']['options']['stream'])
            ) {
                unset($writers['stream']);
                error_log('[Omeka S] File logging disabled: not writeable.'); // @translate
            }
        }

        if (!empty($writers['db']) && empty($writers['db']['options']['db'])) {
            $dbAdapter = $this->getDbAdapter($services);
            if ($dbAdapter) {
                $writers['db']['options']['db'] = $dbAdapter;
            } else {
                unset($writers['db']);
                error_log('[Omeka S] Database logging disabled: wrong config.'); // @translate
            }
        }

        if (empty($writers)) {
            return (new Logger)->addWriter(new Noop);
        }

        $config['logger']['options']['writers'] = $writers;
        if (!empty($config['logger']['options']['processors']['userid']['name'])) {
            $config['logger']['options']['processors']['userid']['name'] = $this->addUserIdProcessor($services);
        }

        // Checks are managed via the constructor.
        return new Logger($config['logger']['options']);
    }

    /**
     * Get the database params, or the Omeka database params.
     *
     * To disable the database, set `"db" => false` in the module config.
     *
     * For performance, flexibility and stability reasons, the write process
     * uses a specific Laminas Db adapter. The read/delete process in api or ui
     * uses the default doctrine entity manager.
     * @todo Use a second entity manager to manage the database and save logs in real time.
     *
     * @param ContainerInterface $services
     * @return  \Laminas\Db\Adapter\AdapterInterface
     */
    protected function getDbAdapter(ContainerInterface $services)
    {
        $iniConfigPath = OMEKA_PATH . '/config/database-log.ini';
        if (file_exists($iniConfigPath) && is_readable($iniConfigPath)) {
            $reader = new \Laminas\Config\Reader\Ini;
            $iniConfig = $reader->fromFile($iniConfigPath);
            $iniConfig = array_filter($iniConfig);
        }

        // TODO Check 'hostname'.

        if (empty($iniConfig)) {
            $iniConfig = $services->get('Omeka\Connection')->getParams();
            $iniConfig['database'] = $iniConfig['dbname'];
            $iniConfig['username'] = $iniConfig['user'];
            unset($iniConfig['dbname']);
            unset($iniConfig['user']);
            $iniConfig['driver'] = 'Pdo_Mysql';
            $iniConfig['platform'] = 'Mysql';
            // Driver options allow to pass specific options, in particular the
            // ssl certificate. Doctrine use camel case and Laminas snake case.
            // The list of driver options are the values of the constants PDO::MYSQL_ATTR_*.
            if (isset($iniConfig['driverOptions'])) {
                $iniConfig['driver_options'] = $iniConfig['driverOptions'];
            }
        }
        // Avoid an issue on common config.
        elseif (empty($iniConfig['driver'])) {
            $iniConfig['driver'] = 'Pdo_Mysql';
            $iniConfig['platform'] = 'Mysql';
            if (isset($iniConfig['driverOptions'])) {
                $iniConfig['driver_options'] = $iniConfig['driverOptions'];
            }
        }

        return new \Laminas\Db\Adapter\Adapter($iniConfig);
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
