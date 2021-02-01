<?php declare(strict_types=1);
namespace Log\Api\Representation;

use Log\Stdlib\PsrMessage;
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
        $psrMessage->setTranslator($translator);
        return $psrMessage;
    }

    /**
     * Return translatable message with context (resource links).
     *
     * @return PsrMessage
     */
    public function text()
    {
        $services = $this->getServiceLocator();
        $translator = $services->get('MvcTranslator');
        $escapeHtml = true;

        $message = $this->resource->getMessage();
        $context = $this->resource->getContext() ?: [];
        if ($context) {
            $hyperlink = $this->getViewHelper('hyperlink');
            $url = $this->getViewHelper('url');
            foreach ($context as $key => $value) {
                $cleanKey = preg_replace('~[^a-z]~', '', strtolower((string) $key));
                switch ($cleanKey) {
                    case 'itemid':
                    case 'itemsetid':
                    case 'mediaid':
                    case 'userid':
                    case 'ownerid':
                    case 'jobid':
                    case 'annotationid':
                        $resourceTypes = [
                            'itemid' => 'item',
                            'itemsetid' => 'item-set',
                            'mediaid' => 'media',
                            'userid' => 'user',
                            'ownerid' => 'user',
                            'jobid' => 'job',
                            'annotationid' => 'annotation',
                        ];
                        $resourceType = $resourceTypes[$cleanKey];
                        $context[$key] = $hyperlink($value, $url('admin/id', ['controller' => $resourceType, 'id' => $value]));
                        $escapeHtml = false;
                        break;
                    case 'resourceid':
                    case 'id':
                        $resourceType = $context['resource'] ?? $context['resource_type'] ?? $context['resource_name'] ?? null;
                        if ($resourceType) {
                            $resourceTypes = [
                                'item' => 'item',
                                'items' => 'item',
                                'itemset' => 'item-set',
                                'itemsets' => 'item-set',
                                'media' => 'media',
                                'user' => 'user',
                                'users' => 'user',
                                'annotation' => 'annotation',
                                'annotations' => 'annotation',
                            ];
                            $resourceType = preg_replace('~[^a-z]~', '', strtolower($resourceType));
                            if (isset($resourceTypes[$resourceType])) {
                                $resourceType = $resourceTypes[$resourceType];
                                $context[$key] = $hyperlink($value, $url('admin/id', ['controller' => $resourceType, 'id' => $value]));
                                $escapeHtml = false;
                                if (isset($context['resource'])) {
                                    $context['resource'] = $translator->translate($context['resource']);
                                }
                                if (isset($context['resource_type'])) {
                                    $context['resource_type'] = $translator->translate($context['resource_type']);
                                }
                                if (isset($context['resource_name'])) {
                                    $context['resource_name'] = $translator->translate($context['resource_name']);
                                }
                            }
                        }
                        break;
                    case 'url':
                        $context[$key] = $hyperlink($value, $value);
                        $escapeHtml = false;
                        break;
                    case 'link':
                        $escapeHtml = false;
                        break;
                }
            }
        }

        $psrMessage = new PsrMessage($message, $context);
        $psrMessage->setTranslator($translator);
        $psrMessage->setEscapeHtml($escapeHtml);
        return $psrMessage;
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
