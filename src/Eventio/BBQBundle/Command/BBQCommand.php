<?php

namespace Eventio\BBQBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base class for BBQ Commands, providing logging interface and signal
 * handling
 */
abstract class BBQCommand extends ContainerAwareCommand {

    protected $exitRequestPending = false;

    public function receiveSIGTERM() {
        $this->log()->info('Terminate signal received');
        $this->exitRequestPending = true;
    }

    protected $verbosityLevel = 0;
    protected $logOutput;

    protected function verboseWrite($level, $message) {
        if ($level <= $this->verbosityLevel) {
            $this->logOutput->writeln($message);
        }
    }

    protected function setVerbosityOutput(OutputInterface $output) {
        $this->logOutput = $output;
    }

    protected function setVerbosityLevel($level = 0) {
        $this->verbosityLevel = $level;
    }

    protected function ticks() {
        declare(ticks = 1);
        pcntl_signal(SIGINT, array($this, 'receiveSIGTERM'));
        pcntl_signal(SIGTERM, array($this, 'receiveSIGTERM'));
    }

    protected function shouldExist() {
        return $this->exitRequestPending;
    }

    /**
     * @return \Psr\Log\LoggerInterface
     */
    protected function log() {
        $logger = $this->getContainer()->get('logger');
        if ($logger) {
            return $logger;
        }
        return new \Psr\Log\NullLogger();
    }

}
