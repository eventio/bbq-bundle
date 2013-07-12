<?php

namespace Eventio\BBQBundle\Event;

use Symfony\Component\EventDispatcher\Event;
use Eventio\BBQ\Job\Payload\JobPayloadInterface;

class HandleJobEvent extends Event {

    /**
     * @var JobPayloadInterface 
     */
    protected $jobPayload;
    
    /**
     * @var string
     */
    protected $originQueueId;

    public function __construct(JobPayloadInterface $jobPayload, $originQueueId = null) {
        $this->originQueueId = $originQueueId;
        $this->jobPayload = $jobPayload;
    }
    
    public function getJobPayload() {
        return $this->jobPayload;
    }

    public function setJobPayload($jobPayload) {
        $this->jobPayload = $jobPayload;
    }

    public function getOriginQueueId() {
        return $this->originQueueId;
    }

    public function setOriginQueueId($originQueueId) {
        $this->originQueueId = $originQueueId;
    }

}