<?php declare(strict_types=1);

namespace Log\Api\Representation;

use Common\Stdlib\PsrMessage;
use Omeka\Api\Representation\AbstractEntityRepresentation;

class LogRepresentation extends AbstractEntityRepresentation
{
    public function getControllerName()
    {
        return 'log';
    }

    public function getJsonLdType()
    {
        return 'o:Log';
    }

    public function getJsonLd()
    {
        $owner = $this->owner();
        if ($owner) {
            $owner = $owner->getReference();
        }

        $job = $this->job();
        if ($job) {
            $job = $job->getReference();
        }

        // TODO Find the schema for log severity. See https://tools.ietf.org/html/rfc3164.

        $created = [
            '@value' => $this->getDateTime($this->created()),
            '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime',
        ];

        return [
            'o:reference' => $this->reference(),
            'o:severity' => $this->severity(),
            'o:message' => $this->message(),
            'o:context' => $this->context(),
            'o:created' => $created,
            'o:owner' => $owner,
            'o:job' => $job,
        ];
    }

    public function reference()
    {
        return $this->resource->getReference();
    }

    public function severity()
    {
        return $this->resource->getSeverity();
    }

    public function severityLabel()
    {
        $severities = [
            \Laminas\Log\Logger::EMERG => 'emergency', // @translate
            \Laminas\Log\Logger::ALERT => 'alert', // @translate
            \Laminas\Log\Logger::CRIT => 'critical', // @translate
            \Laminas\Log\Logger::ERR => 'error', // @translate
            \Laminas\Log\Logger::WARN => 'warning', // @translate
            \Laminas\Log\Logger::NOTICE => 'notice', // @translate
            \Laminas\Log\Logger::INFO => 'info', // @translate
            \Laminas\Log\Logger::DEBUG => 'debug', // @translate
        ];
        $severity = $this->severity();
        return $severities[$severity] ?? $severity;
    }

    /**
     * @return PsrMessage
     */
    public function message()
    {
        $translator = $this->getServiceLocator()->get('MvcTranslator');
        $message = $this->resource->getMessage();
        $context = $this->resource->getContext() ?: [];
        $psrMessage = new PsrMessage($message, $context);
        return $psrMessage
            ->setTranslator($translator);
    }

    /**
     * Return translatable message with context (resource links).
     *
     * @return PsrMessage
     */
    public function text()
    {
        /**
         * @var \Omeka\View\Helper\Url $url
         * @var \Omeka\View\Helper\Hyperlink $hyperlink
         * @var \Omeka\I18n\Translator $translator
         */
        $services = $this->getServiceLocator();
        $url = $this->getViewHelper('url');
        $escape = $this->getViewHelper('escapeHtml');
        $hyperlink = $this->getViewHelper('hyperlink');
        $translator = $services->get('MvcTranslator');

        // For speed, use a base url so just append the controller name and the
        // resource id without full url processing.
        // In logs, it is recommended to use the precise context when possible.
        $resourcesToControllers = [
            'asset' => 'asset',
            'assets' => 'asset',
            'item' => 'item',
            'items' => 'item',
            'item set' => 'item-set',
            'itemset' => 'item-set',
            'itemsets' => 'item-set',
            'job' => 'job',
            'jobs' => 'job',
            'media' => 'media',
            'resourcetemplate' => 'resource-template',
            'template' => 'resource-template',
            'user' => 'user',
            'users' => 'user',
            'annotation' => 'annotation',
            'annotations' => 'annotation',
            // For context.
            'itemid' => 'item',
            'itemsetid' => 'item-set',
            'jobid' => 'job',
            'mediaid' => 'media',
            'ownerid' => 'user',
            'userid' => 'user',
            'resourcetemplateid' => 'resource-template',
            'templateid' => 'resource-template',
            'annotationid' => 'annotation',
        ];
        $baseUrl = str_replace('/replace', '', $url('admin/default', ['controller' => 'replace']));

        $escapeHtml = true;
        $message = $this->resource->getMessage();
        $context = $this->resource->getContext() ?: [];

        if ($context) {
            $shouldEscapes = [];
            $prevSiteSlug = null;
            foreach ($context as $key => $value) {
                $shouldEscapes[$key] = true;
                $value = trim((string) $value);
                $lowerKey = strtolower((string) $key);
                $cleanKey = preg_replace('~[^a-z]~', '', $lowerKey);
                switch ($cleanKey) {
                    case 'itemid':
                    case 'itemsetid':
                    case 'jobid':
                    case 'mediaid':
                    case 'resourcetemplateid':
                    case 'templateid':
                    case 'ownerid':
                    case 'userid':
                    case 'annotationid':
                        $controller = $resourcesToControllers[$cleanKey];
                        $context[$key] = $hyperlink($value, "$baseUrl/$controller/$value");
                        $shouldEscapes[$key] = false;
                        break;
                    case 'assetid':
                        $context[$key] = $hyperlink($value, "$baseUrl/asset?id=$value");
                        $shouldEscapes[$key] = false;
                        break;
                    case 'resourceid':
                    case 'id':
                        $resourceType = $context['resource'] ?? $context['resource_name'] ?? $context['resource_type'] ?? null;
                        if ($resourceType) {
                            $resourceType = preg_replace('~[^a-z]~', '', strtolower($resourceType));
                            if (isset($resourcesToControllers[$resourceType])) {
                                $controller = $resourcesToControllers[$resourceType];
                                $context[$key] = $controller === 'asset'
                                    ? $hyperlink($value, "$baseUrl/asset?id=$value")
                                    : $hyperlink($value, "$baseUrl/$controller/$value");
                                $shouldEscapes[$key] = false;
                                if (isset($context['resource'])) {
                                    $context['resource'] = $translator->translate($context['resource']);
                                }
                                if (isset($context['resource_name'])) {
                                    $context['resource_name'] = $translator->translate($context['resource_name']);
                                }
                                if (isset($context['resource_type'])) {
                                    $context['resource_type'] = $translator->translate($context['resource_type']);
                                }
                            }
                        }
                        break;
                    case 'siteslug':
                        $context[$key] = $hyperlink($value, "$baseUrl/site/s/$value");
                        $shouldEscapes[$key] = false;
                        $prevSiteSlug = $value;
                        break;
                    case 'pageslug':
                        if ($prevSiteSlug) {
                            $context[$key] = $hyperlink($value, "$baseUrl/site/s/$prevSiteSlug/page/$value");
                            $shouldEscapes[$key] = false;
                        } elseif (isset($context['site_slug'])) {
                            $context[$key] = $hyperlink($value, "$baseUrl/site/s/{$context['site_slug']}/page/$value");
                            $shouldEscapes[$key] = false;
                        }
                        break;
                    case 'siteurl':
                    case 'pageurl':
                    case 'url':
                    // Already managed via the clean key.
                    // case strpos($lowerKey, 'url_') === 0:
                        $context[$key] = $hyperlink(basename($value), $value, ['target' => '_blank']);
                        $shouldEscapes[$key] = false;
                        break;
                    case 'href':
                    case 'link':
                        $shouldEscapes[$key] = false;
                        break;
                    case 'thesaurusid':
                        $context[$key] = $hyperlink($value, "$baseUrl/thesaurus/$value/structure");
                        $shouldEscapes[$key] = false;
                        break;
                    default:
                        break;
                }
            }
            $countKeys = count($context);
            $countShouldEscape = count(array_filter($shouldEscapes));
            $countShouldNotEscape = $countKeys - $countShouldEscape;
            if ($countKeys === $countShouldEscape) {
                $escapeHtml = true;
            } elseif ($countKeys === $countShouldNotEscape) {
                $escapeHtml = false;
            } else {
                // Manual escaping.
                $escapeHtml = false;
                foreach ($context as $key => $value) {
                    if ($shouldEscapes[$key]) {
                        $context[$key] = $escape($value);
                    }
                }
            }
        } else {
            // Manage simple logs.
            // Add resource links for logs with strings like "item #xxx".
            // TODO Manage "resource" (or a route redirect).
            if (mb_strpos($message, '#')) {
                $count = 0;
                $message = preg_replace_callback('~(?<resource>item set|item|job|media|owner|user|annotation) #(?<id>\d+)~i', function ($matches) use ($hyperlink, $baseUrl, $resourcesToControllers) {
                $controller = $resourcesToControllers[strtolower($matches['resource'])];
                return $matches['resource'] . ' #' . ($controller === 'asset'
                    ? $hyperlink($matches['id'], "$baseUrl/asset?id={$matches['id']}")
                    : $hyperlink($matches['id'], "$baseUrl/$controller/{$matches['id']}"));
                }, $message, -1, $count);
                if ($count) {
                    $escapeHtml = false;
                }
            }
        }

        $psrMessage = new PsrMessage($message, $context);
        return $psrMessage
            ->setTranslator($translator)
            // TODO Manage the case where some keys should be escaped and some keys not (may be a security issue when logs are external).
            ->setEscapeHtml($escapeHtml);
    }

    public function created()
    {
        return $this->resource->getCreated();
    }

    public function owner()
    {
        $owner = $this->resource->getOwner();
        return $owner
            ? $this->getAdapter('users')->getRepresentation($owner)
            : null;
    }

    public function job()
    {
        $job = $this->resource->getJob();
        return $job
            ? $this->getAdapter('jobs')->getRepresentation($job)
            : null;
    }
}
