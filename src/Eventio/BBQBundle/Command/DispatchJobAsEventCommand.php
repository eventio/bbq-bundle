<?php

namespace Eventio\BBQBundle\Command;

use Eventio\BBQBundle\Event\HandleJobEvent;
use Eventio\BBQBundle\Job\Payload\EventPayload;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DispatchJobAsEventCommand extends BBQCommand {

    /**
     * @see Command
     */
    protected function configure() {
        $this
                ->setName('eventio:bbq:dispatch_job_as_event')
                ->addOption('originQueue', null, InputOption::VALUE_REQUIRED, 'Which queue received the message originally')
                ->addOption('stream', null, InputOption::VALUE_REQUIRED, 'Where to receive the payload from')
                ->setDescription('Converts the job to a Symfony2 event and passes it to the dispatcher')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) {

        $stream = $input->getOption('stream');
        if (!$stream) {
            $stream = 'php://stdin';
        }

        $payload = unserialize(file_get_contents($stream));
        $queue = $input->getOption('originQueue');

        $this->log()->debug('New job received from the queue ' . $queue);

        $dispatcher = $this->getContainer()->get('event_dispatcher');

        if ($payload instanceof EventPayload) {
            $this->log()->debug('Dispatching EventPayload. Event name: ' . $payload->getEventName());
            $dispatcher->dispatch($payload->getEventName(), $payload->getEventObject());
        } else {
            $event = new HandleJobEvent($payload, $queue);

            $this->log()->debug('Dispatching as HandleJobEvent.');
            $dispatcher->dispatch('eventio_bbq.handle_job', $event);
            $dispatcher->dispatch('eventio_bbq.handle_job.' . $queue, $event);
        }

        $output->write('OK');
        return;
    }

}
