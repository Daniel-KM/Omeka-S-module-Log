<?php declare(strict_types=1);

namespace Log;

return [
    // Important: don't modify this file, but copy the keys you want in config/local.config.php at the root of Omeka.

    'logger' => [
        // The default config in Omeka is false, but this module is designed to log.
        'log' => true,

        // Path and priority are used by Omeka default config. Anyway, the local
        // config override it, at least for priority. To set null avoid a check.
        // Note: these options override the ones in the standard config:
        // - path: config[logger][options][writers][stream][options][stream]
        // - priority: config[logger][options][writers][stream][options][filters]
        'path' => null,
        // This is the default level in the standard config. Should not be null
        // when upgrade (so check the file config/local.config.php).
        // It is used only for the stream logger.
        'priority' => null,

        // Enable/disable the output writers.
        'writers' => [
            // The database used by this module. The database can be the main
            // one or an another one: set it "config/database-log.ini" or in
            // this file as config[logger][options][writers][db][options][db]
            // Warning: Omeka use "dbname" for "database" and "user" for "username".
            // Furthermore, you have to define the table and columns to use
            // in the options below: config[logger][options][writers][db][options].
            // Note: even disabled, the database may be used via loggerDb().
            'db' => true,
            // This is the default log file of Omeka (logs/application.log).
            'stream' => true,
            // Log for Omeka jobs (useless with this module).
            // This is a standard Laminas writer, but there is no more parameters.
            // Note: the default log of job is a big text field (4GB), so it may
            // prevent to restore a database if they a row is too big (bigger than
            // the param "max_allowed_packet" in the config of mariadb/mysql).
            'job' => false,
            // This is the default log for php. On a web server, it may be a log inside /var/log
            // like /var/log/nginx/ssl-vhost1.error.log, /var/log/apache2/error.log, /var/log/lastlog, or
            // /tmp/systemd-private-xxx-apache2.service-xxx/tmp/php_errors.log, etc.
            'syslog' => false,
            // Config for sentry, an error monitoring service (https://sentry.io).
            // The module LogSentry is required.
            'sentry' => false,
            // Note: External logs (db, sentry, etc.) are not fully checked, so their
            // config should be checked separately.
        ],

        // The logger uses the Laminas Log configuration.
        // @see https://docs.laminas.dev/laminas-log
        // @see https://docs.laminas.dev/laminas-log/service-manager
        'options' => [
            'writers' => [
                'db' => [
                    'name' => 'db',
                    'options' => [
                        'filters' => \Laminas\Log\Logger::DEBUG,
                        'formatter' => Log\Formatter\PsrLogDb::class,
                        'db' => null,
                        // 'db' => new \Laminas\Db\Adapter\Adapter([
                        //     'driver' => 'mysqli',
                        //     'database' =>null,
                        //     'username' => null,
                        //     'password' => null,
                        //     'unix_socket' => null,
                        //     'host' => null,
                        //     'port' => null,
                        //     'log_path' => null,
                        // ]),
                        'table' => 'log',
                        'column' => [
                            'priority' => 'severity',
                            'message' => 'message',
                            'timestamp' => 'created',
                            'extra' => [
                                'context' => 'context',
                                'referenceId' => 'reference',
                                'userId' => 'owner_id',
                                'jobId' => 'job_id',
                            ],
                        ],
                    ],
                ],
                'stream' => [
                    'name' => 'stream',
                    'options' => [
                        // This is the default level in the standard config.
                        // It may be bypassed by the shortcut key set in ['logger']['priority'] when not null.
                        'filters' => \Laminas\Log\Logger::NOTICE,
                        'formatter' => \Common\Log\Formatter\PsrLogSimple::class,
                        'stream' => OMEKA_PATH . '/logs/application.log',
                    ],
                ],
                'syslog' => [
                    'name' => 'syslog',
                    'options' => [
                        'filters' => \Laminas\Log\Logger::ERR,
                        'formatter' => \Common\Log\Formatter\PsrLogSimple::class,
                        'application' => 'omeka-s',
                        'facility' => LOG_USER,
                    ],
                ],
            ],
            'processors' => [
                'userid' => [
                    'name' => Log\Processor\UserId::class,
                ],
            ],
            // Special options for exceptions, errors and fatal errors, disabled by Laminas by default.
            // Note that it may disable the default error logging of php and debug tools.
            // 'exceptionhandler' => true,
            // 'errorhandler' => true,
            // 'fatal_error_shutdownfunction' => true,
        ],
    ],
    'api_adapters' => [
        'invokables' => [
            'logs' => Api\Adapter\LogAdapter::class,
        ],
    ],
    'entity_manager' => [
        'mapping_classes_paths' => [
            dirname(__DIR__) . '/src/Entity',
        ],
        'proxy_paths' => [
            dirname(__DIR__) . '/data/doctrine-proxies',
        ],
        'filters' => [
            'log_visibility' => Db\Filter\LogVisibilityFilter::class,
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'logSearchFilters' => View\Helper\LogSearchFilters::class,
        ],
    ],
    'form_elements' => [
        'factories' => [
            Form\QuickSearchForm::class => Service\Form\QuickSearchFormFactory::class,
        ],
    ],
    'controllers' => [
        'factories' => [
            Controller\Admin\LogController::class => Service\Controller\Admin\LogControllerFactory::class,
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'loggerDb' => Service\ControllerPlugin\LoggerDbFactory::class,
        ],
    ],
    'service_manager' => [
        'factories' => [
            'Log\LoggerDb' => Service\LoggerDbFactory::class,
            'Omeka\Job\Dispatcher' => Service\Job\DispatcherFactory::class,
            'Omeka\Job\DispatchStrategy\Synchronous' => Service\Job\DispatchStrategy\SynchronousFactory::class,
            'Omeka\Logger' => Service\LoggerFactory::class,
        ],
    ],
    'log_processors' => [
        'invokables' => [
            Log\Processor\JobId::class => Log\Processor\JobId::class,
        ],
        'factories' => [
            Log\Processor\UserId::class => Service\Log\Processor\UserIdFactory::class,
        ],
        'aliases' => [
            'jobid' => Log\Processor\JobId::class,
            'userid' => Log\Processor\UserId::class,
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'log' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/log',
                            'defaults' => [
                                '__NAMESPACE__' => 'Log\Controller\Admin',
                                'controller' => Controller\Admin\LogController::class,
                                'action' => 'browse',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'default' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:action',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                    'defaults' => [
                                        'action' => 'browse',
                                    ],
                                ],
                            ],
                            'id' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:id[/:action]',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                        'id' => '\d+',
                                    ],
                                    'defaults' => [
                                        'action' => 'show',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'column_types' => [
        'invokables' => [
            'log_id' => ColumnType\Id::class,
            'log_owner' => ColumnType\Owner::class,
            'log_job' => ColumnType\Job::class,
            'log_reference' => ColumnType\Reference::class,
            'log_severity' => ColumnType\Severity::class,
            'log_message' => ColumnType\Message::class,
            'log_created' => ColumnType\Created::class,
        ],
    ],
    'column_defaults' => [
        'admin' => [
            'logs' => [
                // ['type' => 'log_message'],
                ['type' => 'log_created'],
                ['type' => 'log_severity'],
                ['type' => 'log_reference'],
                ['type' => 'log_job'],
            ],
        ],
    ],
    'browse_defaults' => [
        'admin' => [
            'logs' => [
                'sort_by' => 'id',
                'sort_order' => 'desc',
            ],
        ],
    ],
    'sort_defaults' => [
        'admin' => [
            'logs' => [
                'id' => 'Id', // @translate
                'owner_id' => 'Owner', // @translate
                'job_id' => 'Job', // @translate
                'reference' => 'Reference', // @translate
                'severity' => 'Severity', // @translate
                'message' => 'Message', // @translate
                // 'context' => 'Context', // @translate,
                'created' => 'Created', // @translate
            ],
        ],
    ],
    'navigation' => [
        'AdminGlobal' => [
            [
                'label' => 'Logs', // @translate
                'class' => 'fa-list',
                'route' => 'admin/log',
                'resource' => Controller\Admin\LogController::class,
                'privilege' => 'browse',
            ],
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
];
