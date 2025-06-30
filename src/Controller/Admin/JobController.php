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
        try {
            /** @var \Omeka\Entity\Job $job*/
            $job = $this->api()->read('jobs', $this->params('id'), ['responseContent' => 'resource'])->getContent();
        } catch (\Exception $e) {
            return $this->jSend(JSend::FAIL, [
                'job' => (new PsrMessage('Not found'))->setTranslator($this->translator), // @translate
            ]);
        }

        $state = $this->jobState($job);
        if (!$state) {
            return $this->jSend(JSend::SUCCESS, [
                'job' => [
                    'o:system_state' => null,
                ],
            ]);
        }

        $stateData = JobState::STATES[$state];
        $stateData['label'] = $this->translate($stateData['label']);
        return $this->jSend(JSend::SUCCESS, [
            'job' => [
                'o:system_state' => $stateData,
            ],
        ]);
    }
}
