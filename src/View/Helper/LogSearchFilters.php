<?php declare(strict_types=1);
namespace Log\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Exception\NotFoundException;

/**
 * View helper for rendering search filters.
 */
class LogSearchFilters extends AbstractHelper
{
    /**
     * The default partial view script.
     */
    public const PARTIAL_NAME = 'common/search-filters';

    /**
     * Render filters from search query.
     *
     * @return array
     */
    public function __invoke($partialName = null)
    {
        $partialName = $partialName ?: self::PARTIAL_NAME;

        $view = $this->getView();
        $translate = $view->plugin('translate');

        $filters = [];
        $api = $view->api();
        $query = $view->params()->fromQuery();

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

        foreach ($query as $key => $value) {
            if (is_null($value) || $value === '') {
                continue;
            }
            switch ($key) {
                case 'created':
                    $filterLabel = $translate('Created'); // @translate
                    $filterValue = $value;
                    $filters[$filterLabel][] = $filterValue;
                    break;

                case 'message':
                    $filterLabel = $translate('Message contains'); // @translate
                    $filterValue = $value;
                    $filters[$filterLabel][] = $filterValue;
                    break;

                case 'reference':
                    $filterLabel = $translate('Reference'); // @translate
                    $filterValue = $value;
                    $filters[$filterLabel][] = $filterValue;
                    break;

                case 'severity':
                    $filterLabel = $translate('Severity'); // @translate
                    $filterValue = $severities[$value] ?? $value;
                    $filters[$filterLabel][] = $filterValue;
                    break;
                case 'severity_min':
                    $filterLabel = $translate('Severity'); // @translate
                    $filterValue = '>=';
                    $filterValue .= $severities[$value] ?? $value;
                    $filters[$filterLabel][] = $filterValue;
                    break;
                case 'severity_max':
                    $filterLabel = $translate('Severity'); // @translate
                    $filterValue = '<=';
                    $filterValue .= $severities[$value] ?? $value;
                    $filters[$filterLabel][] = $filterValue;
                    break;

                case 'owner_id':
                    $filterLabel = $translate('User'); // @translate
                    try {
                        $filterValue = $api->read('users', $value)->getContent()->name();
                    } catch (NotFoundException $e) {
                        $filterValue = $translate('Unknown user'); // @translate
                    }
                    $filters[$filterLabel][] = $filterValue;
                    break;

                case 'job_id':
                    $filterLabel = $translate('Job');
                    try {
                        $filterValue = $api->read('jobs', $value)->getContent()->id();
                    } catch (NotFoundException $e) {
                        $filterValue = $translate('Unknown job'); // @translate
                    }
                    $filters[$filterLabel][] = $filterValue;
                    break;
            }
        }

        $result = $this->getView()->trigger(
            'view.search.filters',
            ['filters' => $filters, 'query' => $query],
            true
        );
        $filters = $result['filters'];

        return $this->getView()->partial(
            $partialName,
            [
                'filters' => $filters,
            ]
        );
    }
}
