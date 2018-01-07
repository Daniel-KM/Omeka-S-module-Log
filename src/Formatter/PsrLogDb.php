<?php

namespace Log\Formatter;

use \Zend\Log\Formatter\Base;

class PsrLogDb extends Base
{
    use PsrLogAwareTrait;

    /**
     * Formats data to be written by the writer.
     *
     * To be used with the processors UserId and JobId.
     *
     * @param array $event event data
     * @return array
     */
    public function format($event)
    {
        $event = parent::format($event);
        $event = $this->normalizeLogContext($event);

        if (!empty($event['extra']['context']['extra'])) {
            $event['extra']['context']['extra'] = json_encode($event['extra']['context']['extra'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if (!empty($event['extra']['context'])) {
            $event['extra']['context'] = json_encode($event['extra']['context'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return $event;
    }
}
