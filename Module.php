<?php declare(strict_types=1);

/*
 * Copyright Daniel Berthereau, 2017-2026
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/ or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

namespace Log;

// Common may be installed but not registered in autoloader, in particular
// during upgrade. So dynamically register all classes of the module.
if (!defined('COMMON_PSR4_FALLBACK')) {
    foreach ([
        OMEKA_PATH . '/modules/Common/src',
        OMEKA_PATH . '/composer-addons/modules/Common/src',
        dirname(__DIR__) . '/Common/src',
    ] as $commonSrc) {
        if (file_exists($commonSrc . '/TraitModule.php')) {
            define('COMMON_PSR4_FALLBACK', $commonSrc);
            spl_autoload_register(static function ($class): void {
                if (str_starts_with($class, 'Common\\')) {
                    $file = COMMON_PSR4_FALLBACK . '/' . strtr(substr($class, 7), '\\', '/') . '.php';
                    if (file_exists($file)) {
                        require_once $file;
                    }
                }
            });
            break;
        }
    }
}

// Required during migration from Generic to Common.
if (!class_exists(\Common\Log\Formatter\PsrLogAwareTrait::class)) {
    require_once dirname(__DIR__) . '/Common/src/Stdlib/PsrInterpolateInterface.php';
    require_once dirname(__DIR__) . '/Common/src/Stdlib/PsrInterpolateTrait.php';
    require_once dirname(__DIR__) . '/Common/src/Log/Formatter/PsrLogAwareTrait.php';
    require_once dirname(__DIR__) . '/Common/src/Log/Formatter/PsrLogSimple.php';
}

use Common\Stdlib\PsrMessage;
use Common\TraitModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ModuleManager\ModuleEvent;
use Laminas\ModuleManager\ModuleManager;
use Laminas\Mvc\MvcEvent;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Module\AbstractModule;
use Omeka\Permissions\Assertion\OwnsEntityAssertion;

class Module extends AbstractModule
{
    use TraitModule;

    public const NAMESPACE = __NAMESPACE__;

    public function init(ModuleManager $moduleManager): void
    {
        $moduleManager->getEventManager()->attach(
            ModuleEvent::EVENT_MERGE_CONFIG,
            [$this, 'onEventMergeConfig']
        );
    }

    /**
     * Force logger log = true in config, else this module is useless.
     */
    public function onEventMergeConfig(ModuleEvent $event): void
    {
        // At this point, the config is read only, so it is copied and replaced.
        /** @var \Laminas\ModuleManager\Listener\ConfigListener $configListener */
        $configListener = $event->getParam('configListener');
        $config = $configListener->getMergedConfig(false);
        $config['logger']['log'] = empty($config['logger']['disable_log']);
        $configListener->setMergedConfig($config);
    }

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);
        $this->addAclRules();
    }

    protected function preInstall(): void
    {
        /** @var \Laminas\Mvc\I18n\Translator $translator */
        $services = $this->getServiceLocator();
        $translator = $services->get('MvcTranslator');
        $plugins = $services->get('ControllerPluginManager');
        $messenger = $plugins->get('messenger');

        if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.90')) {
            $message = new \Omeka\Stdlib\Message(
                $translator->translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
                'Common', '3.4.90'
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
        }

        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        if (!$this->checkDestinationDir($basePath . '/backup/log', true)) {
            $message = new PsrMessage(
                'The directory "{directory}" is not writeable, so old logs cannot be archived.', // @translate
                ['directory' => $basePath . '/backup/log']
            );
            $messenger->addWarning($message);
        }
    }

    protected function postInstall(): void
    {
        $services = $this->getServiceLocator();
        $translate = $services->get('ControllerPluginManager')->get('translate');
        $messenger = $services->get('ControllerPluginManager')->get('messenger');

        $messenger->addWarning($translate('Logging is not active by default in Omeka. This module overrides option [logger][log].')); // @translate
        $messenger->addWarning($translate('You may need to update the file config/local.config.php, in particular to set the default level of severity.')); // @translate
        $message = new PsrMessage(
            $translate('See examples of config in the {link}readme{link_end}.'), // @translate
            ['link' => '<a href="https://gitlab.com/Daniel-KM/Omeka-S-module-Log/#config" target="_blank" rel="noopener">', 'link_end' => '</a>']
        );
        $message->setEscapeHtml(false);
        $messenger->addNotice($message);
    }

    /**
     * Add ACL role and rules for this module.
     *
     * @todo Keep rights for Annotation only (body and  target are internal classes).
     */
    protected function addAclRules(): void
    {
        /**
         * @var \Omeka\Permissions\Acl $acl
         *
         * @see \Omeka\Service\AclFactory::addRules()
         */
        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');

        // The resources are added automatically by Omeka (@see \Omeka\Service\AclFactory::addResources).

        // Nevertheless, acl should be specified, because log is not a resource.
        $entityManagerFilters = $services->get('Omeka\EntityManager')->getFilters();
        $entityManagerFilters->enable('log_visibility')->setAcl($acl);

        // Public users cannot see own logs.
        // TODO How to make a distinction between public and admin roles? Which new rule?
        $roles = [
            \Omeka\Permissions\Acl::ROLE_RESEARCHER,
            \Omeka\Permissions\Acl::ROLE_AUTHOR,
            \Omeka\Permissions\Acl::ROLE_REVIEWER,
            \Omeka\Permissions\Acl::ROLE_EDITOR,
            \Omeka\Permissions\Acl::ROLE_SITE_ADMIN,
            \Omeka\Permissions\Acl::ROLE_GLOBAL_ADMIN,
        ];
        $baseRoles = [
            \Omeka\Permissions\Acl::ROLE_RESEARCHER,
            \Omeka\Permissions\Acl::ROLE_AUTHOR,
        ];
        $editorRoles = [
            \Omeka\Permissions\Acl::ROLE_REVIEWER,
            \Omeka\Permissions\Acl::ROLE_EDITOR,
        ];
        $adminRoles = [
            \Omeka\Permissions\Acl::ROLE_SITE_ADMIN,
            \Omeka\Permissions\Acl::ROLE_GLOBAL_ADMIN,
        ];

        // TODO Check if not allowed to create log by api?
        // Everybody can create log.
        $acl
            ->allow(
                null,
                [
                    \Log\Api\Adapter\LogAdapter::class,
                    \Log\Entity\Log::class,
                ],
                ['create']
            )

            ->allow(
                $roles,
                [\Log\Controller\Admin\JobController::class],
                ['system-state']
            )

            // Everybody can see own logs, except guest user or visitors.
            ->allow(
                $baseRoles,
                [\Log\Entity\Log::class],
                ['read'],
                new OwnsEntityAssertion
            )
            ->allow(
                $baseRoles,
                [\Log\Api\Adapter\LogAdapter::class],
                ['read', 'search']
            )
            ->allow(
                $baseRoles,
                [\Log\Controller\Admin\LogController::class],
                ['browse', 'search', 'show-details']
            )

            ->allow(
                $editorRoles,
                [\Log\Entity\Log::class],
                ['read', 'view-all']
            )
            ->allow(
                $editorRoles,
                [\Log\Api\Adapter\LogAdapter::class],
                ['read', 'search']
            )
            ->allow(
                $editorRoles,
                [\Log\Controller\Admin\LogController::class],
                ['browse', 'search', 'show-details']
            )

            ->allow(
                $adminRoles,
                [
                    \Log\Entity\Log::class,
                    \Log\Api\Adapter\LogAdapter::class,
                    \Log\Controller\Admin\LogController::class,
                ]
            )
        ;
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        // TODO What is the better event to handle a cron? None: use server cron or systemd timer or webcron.
        $sharedEventManager->attach(
            '*',
            'view.layout',
            [$this, 'handleCron']
        );
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');

        $lastCron = (int) $settings->get('log_cron_last');
        $cronDays = (int) $settings->get('log_cron_days');

        if ($cronDays && $lastCron) {
            $services = $this->getServiceLocator();
            $plugins = $services->get('ControllerPluginManager');
            $messenger = $plugins->get('messenger');
            $message = new PsrMessage(
                'Last cron occurred on {date_time}. Next cron will occur after {date_time_2}.', // @translate
                ['date_time' => date('Y-m-d H:i:s', $lastCron), 'date_time_2' => date('Y-m-d H:i:s', $lastCron + $cronDays * 86400)]
            );
            $messenger->addSuccess($message);
        }

        return $this->getConfigFormAuto($renderer);
    }

    public function handleCron(Event $event): void
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');

        // Check if the job is set.
        $cronDays = (int) $settings->get('log_cron_days');
        if (!$cronDays) {
            return;
        }

        // One check each day.
        $lastCron = (int) $settings->get('log_cron_last');
        $time = time();
        if ($lastCron + $cronDays * 86400 > $time) {
            return;
        }
        $settings->set('log_cron_last', $time);

        // Dispatch the job.
        $dispatcher = $services->get(\Omeka\Job\Dispatcher::class);
        $dispatcher->dispatch(\Log\Job\ArchiveLogsJob::class, [
            'seconds' => $settings->get('log_archive_days', 0) * 86400,
            'severity' => $settings->get('log_archive_severity_max', 0) ?: 0,
            'delete_job_logs' => (bool) $settings->get('log_archive_delete_job_logs', false),
            'references' => $settings->get('log_archive_references') ?: [],

            'store' => (bool) $settings->get('log_archive_store', true),
            'format' => $settings->get('log_archive_format', 'tsv'),
            'compress' => (bool) $settings->get('log_archive_compress', true),
            'include_id' => (bool) $settings->get('log_archive_include_id', false),
            'translate' => (bool) $settings->get('log_archive_translate', true),

            'delete' => (bool) $settings->get('log_archive_delete', true),
        ]);
    }
}
