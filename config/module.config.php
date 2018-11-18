<?php
namespace Log;

return [
    // TODO There seems to be a mix between logger plugin and log services: to be cleaned.
    'logger' => [
        // The default config is false, but this module is designed to log.
        'log' => true,
        // Nullify path and priority for compatibility with Omeka default config.
        'path' => null,
        'priority' => null,
        'writers' => [
            // The database used by this module.
            // Note: even disabled, the database may be used via loggerDb().
            'db' => true,
            // Log for Omeka jobs (useless with this module, but kept for testing purpose.
            // This is a standard Zend writer, but there is no more parameters.
            'job' => true,
            // This is the default log file of Omeka (logs/application.log).
            'stream' => true,
            // Config for sentry, an error tracking service (https://sentry.io).
            // See readme to enable it.
            'sentry' => false,
        ],
        'options' => [
            'writers' => [
                'db' => [
                    'name' => 'db',
                    'options' => [
                        'filters' => \Zend\Log\Logger::INFO,
                        'formatter' => Formatter\PsrLogDb::class,
                        'db' => null,
                        'table' => 'log',
                        'column' => [
                            'priority' => 'severity',
                            'message' => 'message',
                            'timestamp' => 'created',
                            'extra' => [
                                'context' => 'context',
                                'referenceId' => 'reference',
                                'userId' => 'user_id',
                                'jobId' => 'job_id',
                            ],
                        ],
                    ],
                ],
                'stream' => [
                    'name' => 'stream',
                    'options' => [
                        // This is the default level in the standard config.
                        'filters' => \Zend\Log\Logger::NOTICE,
                        'stream' => OMEKA_PATH . '/logs/application.log',
                        'formatter' => Formatter\PsrLogSimple::class,
                    ],
                ],
                // See https://github.com/facile-it/sentry-module#log-writer
                'sentry' => [
                    'name' => \Facile\SentryModule\Log\Writer\Sentry::class,
                    'options' => [
                        'filters' => [
                            [
                                'name' => 'priority',
                                'options' => [
                                    'priority' => \Zend\Log\Logger::ERR,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'processors' => [
                'userid' => [
                    'name' => Processor\UserId::class,
                ],
            ],
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
            Form\SearchForm::class => Service\Form\SearchFormFactory::class,
        ],
    ],
    'controllers' => [
        'invokables' => [
            Controller\Admin\LogController::class => Controller\Admin\LogController::class,
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
            Processor\JobId::class => Processor\JobId::class,
        ],
        'factories' => [
            Processor\UserId::class => Service\Processor\UserIdFactory::class,
        ],
        'aliases' => [
            'jobid' => Processor\JobId::class,
            'userid' => Processor\UserId::class,
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'log' => [
                        'type' => \Zend\Router\Http\Literal::class,
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
                                'type' => \Zend\Router\Http\Segment::class,
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
                                'type' => \Zend\Router\Http\Segment::class,
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
