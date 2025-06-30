<?php declare(strict_types=1);

namespace Log\Api\Representation;

use Common\Stdlib\PsrMessage;
use Log\Stdlib\JobState;
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
         * @var \Omeka\Api\Manager $api
         * @var \Omeka\View\Helper\Url $url
         * @var \Omeka\View\Helper\Hyperlink $hyperlink
         * @var \Omeka\I18n\Translator $translator
         */
        $services = $this->getServiceLocator();
        $url = $this->getViewHelper('url');
        $api = $this->getViewHelper('api');
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
            'oaiid' => 'oai-pmh',
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
        $jobArgs = ($job = $this->resource->getJob()) ? $job->getArgs() : [];

        // Messages that are more than 1000 characters are generally an
        // exception with an sql and long parameters, in particular the text
        // used for the full text search.
        $tooMuchLong = strlen($message) > 10000
            || strlen(json_encode($context)) > 10000;
        if ($tooMuchLong) {
            foreach ($context ?? [] as &$v) {
                if (is_string($v) && mb_strlen($v) > 10000) {
                    $v = mb_substr($v, 0, 10000);
                }
            }
            unset($v);
            $message = new PsrMessage(mb_substr($message, 0, 10000) . '…', $context);
            return $message
                ->setTranslator($translator);
        }

        if ($context) {
            $shouldEscapes = [];
            $prevSiteSlug = null;
            foreach ($context as $key => $value) {
                $shouldEscapes[$key] = true;
                $value = trim((string) $value);
                $lowerKey = strtolower((string) $key);
                $cleanKey = preg_replace('~[^a-z]~', '', $lowerKey);
                switch ($cleanKey) {
                    // Single id.
                    case 'itemid':
                    case 'itemsetid':
                    case 'jobid':
                    case 'mediaid':
                    case 'resourcetemplateid':
                    case 'templateid':
                    case 'ownerid':
                    case 'userid':
                    case 'annotationid':
                    // Multiple ids.
                    case 'itemids':
                    case 'itemsetids':
                    case 'jobids':
                    case 'mediaids':
                    case 'resourcetemplateids':
                    case 'templateids':
                    case 'ownerids':
                    case 'userids':
                    case 'annotationids':
                        $controller = $resourcesToControllers[$cleanKey]
                            ?? $resourcesToControllers[substr($cleanKey, 0, -1)];
                        $values = array_values(array_filter(explode(' ', preg_replace('~[^0-9]~', ' ', $value))));
                        if ($values) {
                            $link = $hyperlink('__ID__', "$baseUrl/$controller/__ID__");
                            $context[$key] = '';
                            foreach ($values as $val) {
                                $context[$key] .= ', ' . str_replace('__ID__', $val, $link);
                            }
                            $context[$key] = trim($context[$key], ', ');
                            $shouldEscapes[$key] = false;
                        }
                        break;

                    case 'assetid':
                    case 'assetids':
                        $values = array_values(array_filter(explode(' ', preg_replace('~[^0-9]~', ' ', $value))));
                        if ($values) {
                            $link = $hyperlink('__ID__', "$baseUrl/asset?id=__ID__");
                            $context[$key] = '';
                            foreach ($values as $val) {
                                $context[$key] .= ', ' . str_replace('__ID__', $val, $link);
                            }
                            $context[$key] = trim($context[$key], ', ');
                            $shouldEscapes[$key] = false;
                        }
                        break;

                    case 'id':
                    case 'resourceid':
                    case 'ids':
                    case 'resourceids':
                        $values = array_values(array_filter(explode(' ', preg_replace('~[^0-9]~', ' ', $value))));
                        $resourceType = $context['resource']
                            ?? $context['resource_name']
                            ?? $context['resource_type']
                            ?? $context['entity_name']
                            ?? $jobArgs['resource_name']
                            ?? $jobArgs['resource_type']
                            ?? $jobArgs['entity_name']
                            ?? null;
                        if ($values && $resourceType) {
                            $resourceType = preg_replace('~[^a-z]~', '', strtolower($resourceType));
                            if (isset($resourcesToControllers[$resourceType])) {
                                $controller = $resourcesToControllers[$resourceType];
                                $link = $controller === 'asset'
                                    ? $hyperlink('__ID__', "$baseUrl/asset?id=__ID__}}")
                                    : $hyperlink('__ID__', "$baseUrl/$controller/__ID__");
                                $context[$key] = '';
                                foreach ($values as $val) {
                                    $context[$key] .= ', ' . str_replace('__ID__', $val, $link);
                                }
                                $context[$key] = trim($context[$key], ', ');
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
                        } elseif (count($values) === 1 && in_array($cleanKey, ['resourceid', 'resourceids'])) {
                            try {
                                /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource */
                                $controller = $api->read('resources', $value)->getContent()->getControllerName();
                                $context[$key] = $hyperlink($value, "$baseUrl/$controller/$value");
                                $shouldEscapes[$key] = false;
                            } catch (\Exception $e) {
                            }
                        }
                        // TODO Else link with "?id[]=xxx".
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

                    case 'oaiurl':
                    case 'urloai':
                    case 'oaiendpoint':
                        $context[$key] = $hyperlink(basename($value), $value . '?verb=Identify', ['target' => '_blank']);
                        $shouldEscapes[$key] = false;
                        break;

                    case 'url':
                    // Start or end with "url".
                    case 'siteurl':
                    case 'pageurl':
                    case strpos($lowerKey, 'url') === 0:
                    case mb_substr($lowerKey, -3) === 'url':
                        $context[$key] = $hyperlink(basename($value), $value, ['target' => '_blank']);
                        $shouldEscapes[$key] = false;
                        break;
                    case 'href':
                    case 'link':
                        $shouldEscapes[$key] = false;
                        break;

                    case 'oaiid':
                        $oaiEndpoint = $context['oai_endpoint']
                            ?? $context['oai_url']
                            ?? $context['url_oai']
                            ?? $jobArgs['oai_endpoint']
                            ?? $jobArgs['endpoint']
                            ?? null;
                        if ($oaiEndpoint) {
                            $context[$key] = $hyperlink($value, "$oaiEndpoint?verb=GetRecord&metadataPrefix=oai_dc&identifier=" . rawurlencode($value), ['target' => '_blank']);
                            $shouldEscapes[$key] = false;
                        }
                        break;

                    case 'thesaurusid':
                        $context[$key] = $hyperlink($value, "$baseUrl/thesaurus/$value/structure");
                        $shouldEscapes[$key] = false;
                        break;

                    case 'json':
                        $value = json_decode($value, true);
                        $context[$key] = $value ? json_encode($value, 448) : $value;
                        break;

                    default:
                        // In many places, an array is stored as json, that is
                        // the default transformation, so display it cleanerly.
                        if ($value
                            && mb_substr($value, 0, 1) === '{'
                            && mb_substr($value, -1) === '}'
                            && is_array($v = json_decode($value, true))
                        ) {
                            $context[$key] = json_encode($v, 448);
                        }
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
                        if (is_scalar($value)) {
                            $context[$key] = $escape($value);
                        } else {
                            $v = $value;
                            array_walk_recursive($v, $escape);
                            $context[$key] = $v;
                        }
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

    /**
     * Check if the job associated to the log is in a living state.
     *
     * Windows is not supported (neither in omeka job anyway).
     *
     * Warning: in some cases, the state is not reliable, because it may be the
     * one of another process.
     *
     * @return bool|null Null if no job, else the pid status of the job.
     */
    public function isJobLiving(): ?bool
    {
        $state = $this->jobState();
        return $state
            ? JobState::STATES[$state]['processing']
            : null;
    }

    /**
     * Get the state of the living job (running or stopping) associated to log.
     *
     * Only pid with a job in progress or stopping can be checked.
     * Windows is not supported (neither in omeka job anyway).
     *
     * Linux states are:
     * - R: Running
     * - S: Interruptible Sleep (Sleep, waiting for event from software)
     * - D: Uninterruptible Sleep (Dead, waiting for signal from hardware)
     * - T: Stopped (Traced)
     * - Z: Zombie
     *
     * Warning: in some cases, the state is not reliable, because it may be the
     * one of another process.
     *
     * @uses \Log\Stdlib\JobState
     *
     * @return string|null Letter of the state of the process or null.
     * Full state name can be retrieved from the constant JobState::STATES.
     */
    public function jobState(): ?string
    {
        // The job representation cannot access to the pid, so use entity.
        $job = $this->resource->getJob();
        if (!$job) {
            return null;
        }
        $jobState = $this->getServiceLocator()->get('Log\JobState');
        return $jobState($job);
    }
}
