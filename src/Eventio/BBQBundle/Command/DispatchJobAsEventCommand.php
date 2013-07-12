<?php

namespace Eventio\BBQBundle\Command;

use Eventio\BBQBundle\Event\HandleJobEvent;
use Eventio\BBQBundle\Job\Payload\EventPayload;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DispatchJobAsEventCommand extends ContainerAwareCommand {

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

        $event = new HandleJobEvent($payload, $queue);

        $dispatcher = $this->getContainer()->get('event_dispatcher');
        
        if ($payload instanceof EventPayload) {
            $dispatcher->dispatch($payload->getEventName(), $payload->getEventObject());
        } else {
            $dispatcher->dispatch('eventio_bbq.handle_job', $event);
            $dispatcher->dispatch('eventio_bbq.handle_job.' . $queue, $event);
        }
        
        $output->write('OK');
        return;
    }

    protected function logDebug($message) {
        $logger = $this->getContainer()->get('logger');
        if ($logger) {
            $logger->debug($message);
        }
    }

    protected function logInfo($message) {
        $logger = $this->getContainer()->get('logger');
        if ($logger) {
            $logger->info($message);
        }
    }

    protected function logError($message) {
        $logger = $this->getContainer()->get('logger');
        if ($logger) {
            $logger->err($message);
        }
    }

}
