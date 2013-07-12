<?php

namespace Eventio\BBQBundle\Job\Payload;

use Eventio\BBQ\Job\Payload\JsonSerializedPayload;

/**
 * This payload wraps a Symfony2 event name and object, and
 * feeds it to the dispatcher.
 *
 * @author Ville Mattila <ville@eventio.fi>
 */
class EventPayload extends JsonSerializedPayload
{
    protected $eventName;
    
    protected $eventObject;
    
    public function __construct($eventName, $eventObject = null) {
        $this->eventName = $eventName;
        $this->eventObject = $eventObject;
    }
    
    public function getEventName() {
        return $this->eventName;
    }

    public function setEventName($eventName) {
        $this->eventName = $eventName;
    }

    public function getEventObject() {
        return $this->eventObject;
    }

    public function setEventObject($eventObject) {
        $this->eventObject = $eventObject;
    }
}
