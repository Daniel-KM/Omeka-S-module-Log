<?php declare(strict_types=1);

namespace Log\Controller\Admin;

use Common\Mvc\Controller\Plugin\JSend;
use Common\Stdlib\PsrMessage;
use Laminas\Mvc\Controller\AbstractActionController;
use Log\Stdlib\JobState;

class JobController extends AbstractActionController
{
    public function systemStateAction()
    {
        /** @var \Omeka\Mvc\Controller\Plugin\Api $api */
        $api = $this->api();
        try {
            /** @var \Omeka\Entity\Job $job*/
            $job = $api->read('jobs', $this->params('id'), [], ['responseContent' => 'resource'])->getContent();
        } catch (\Exception $e) {
            return $this->jSend(JSend::FAIL, [
                'job' => (new PsrMessage('Not found'))->setTranslator($this->translator), // @translate
            ]);
        }

        $status = $job->getStatus();

        $state = $this->jobState($job);
        if (!$state) {
            return $this->jSend(JSend::SUCCESS, [
                'job' => [
                    'o:id' => $job->getId(),
                    'o:status' => $status,
                    'o:status_label' => $this->translate($status),
                    'o:system_state' => null,
                ],
            ]);
        }

        $stateData = JobState::STATES[$state];
        $stateData['label'] = $this->translate($stateData['label']);
        return $this->jSend(JSend::SUCCESS, [
            'job' => [
                'o:id' => $job->getId(),
                'o:status' => $status,
                'o:status_label' => $this->translate($status),
                'o:system_state' => $stateData,
            ],
        ]);
    }
}
