<?php
namespace Log;

return [
    'logger' => [
        'log' => true,
        // Nullify path and priority for compatibility with Omeka S 1.0 config.
        'path' => null,
        'priority' => null,
        'writers' => [
            // Note: even disabled, the database may be used via loggerDb().
            'db' => true,
            'stream' => true,
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
                        'filters' => \Zend\Log\Logger::NOTICE,
                        'stream' => OMEKA_PATH . '/logs/application.log',
                        'formatter' => Formatter\PsrLogSimple::class,
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
        'invokables' => [
            Form\SearchForm::class => Form\SearchForm::class,
        ],
    ],
    'controllers' => [
        'factories' => [
            Controller\LogController::class => Service\Controller\LogControllerFactory::class,
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
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/log',
                            'defaults' => [
                                '__NAMESPACE__' => 'Log\Controller',
                                'controller' => Controller\LogController::class,
                                'action' => 'browse',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'default' => [
                                'type' => 'Segment',
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
                                'type' => 'Segment',
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
                'label' => 'Logs',
                'class' => 'fa fa-list',
                'route' => 'admin/log',
                'resource' => Controller\LogController::class,
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
