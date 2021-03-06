<?php

namespace Eventio\BBQBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Symfony2 command eventio:bbq:handle polls continuously the configured
 * queues for new jobs. When a new job is 
 */
class HandleQueueCommand extends BBQCommand {

    protected function configure() {
        $this
                ->setName('eventio:bbq:handle')
                ->addOption('queue', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Which queue(s) should we handle')
                ->addOption('skip-queue', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Which queue(s) we should skip')
                ->addOption('verbosity', null, InputOption::VALUE_OPTIONAL, 'Verbosity level (0 = nothing, 9 = max)', 0)
                ->addOption('max-wait', null, InputOption::VALUE_OPTIONAL, 'Maximum wait time (seconds) for a new job. If not specified, will wait forever.', null)
                ->addOption('max-loops', null, InputOption::VALUE_OPTIONAL, 'How many loops we will run before exiting (infinite)', null)
                ->addOption('max-jobs', null, InputOption::VALUE_OPTIONAL, 'How many jobs we will handle before exiting (infinite)', null)
                ->addOption('sleep', null, InputOption::VALUE_OPTIONAL, 'Sleep (in seconds) between loops', 1)
                ->addOption('exponential-sleep-until', null, InputOption::VALUE_OPTIONAL, 'If no jobs is handled between loops, exponentially increase the sleep time but max to this time', null)
                ->addOption('failing-job-quarantine', null, InputOption::VALUE_REQUIRED, 'Directory where failed jobs should be persisted. If empty, failed jobs are returned back to the queue', null)
                ->addOption('quit-on-job-failure', null, InputOption::VALUE_NONE, 'If a job fails, should the whole handling process quit')
                ->setDescription('Poll the specified queues and pass tasks to a worker.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->ticks();
        $this->setVerbosityOutput($output);
        $this->setVerbosityLevel($input->getOption('verbosity'));

        $jobQuarantine = $input->getOption('failing-job-quarantine');
        if ($jobQuarantine) {
            if (false === is_writable($jobQuarantine)) {
                throw new \InvalidArgumentException(sprintf('failing-job-quarantine "%s" is not writable.', $jobQuarantine));
            }
        }

        $bbq = $this->getContainer()->get('eventio_bbq');

        $queuesToQuery = array();
        if ($input->getOption('queue')) {
            foreach ($input->getOption('queue') as $queueName) {
                $queue = $bbq->getQueue($queueName);
                $queuesToQuery[$queue->getId()] = $queue;
            }
        } else {
            $queuesToQuery = $bbq->getQueues();
        }

        if ($input->getOption('skip-queue')) {
            foreach ($input->getOption('skip-queue') as $skipQueue) {
                unset($queuesToQuery[$skipQueue]);
            }
        }

        $waitTime = $input->getOption('max-wait');
        $jobHandlerCommand = 'eventio:bbq:dispatch_job_as_event';
        $kernel = $this->getContainer()->get('kernel');

        $loopCount = 0;
        $jobCount = 0;

        $sleepMin = (int) $input->getOption('sleep');
        $sleepTime = $sleepMin;
        $sleepMax = $input->getOption('exponential-sleep-until');

        while (true) {
            $this->verboseWrite(7, 'Starting new loop...');
            if ($this->shouldExist()) {
                break;
            }

            $jobsInLoop = 0;
            foreach ($queuesToQuery as $queue) {
                if ($this->shouldExist()) {
                    break 2;
                }

                $this->verboseWrite(9, 'Fetching job from ' . $queue->getId());
                $job = $queue->fetchJob($waitTime);
                if ($this->shouldExist()) {
                    if ($job) {
                        $queue->releaseJob($job);
                    }
                    break 2;
                }

                // Passing the job to the handler
                // Now, it's always another console command
                if ($job) {
                    $jobsInLoop++;
                    $this->verboseWrite(5, 'Job received from ' . $queue->getId());

                    $jobPayload = serialize($job->getPayload());

                    $processCommand = PHP_BINARY . ' ' . $kernel->getRootDir() . '/console --env=' . $kernel->getEnvironment() . ' ' . $jobHandlerCommand . ' --originQueue=' . $queue->getId();
                    $this->verboseWrite(9, 'Starting new process (' . $processCommand . '), payload is ' . $jobPayload . '.');

                    $process = new Process($processCommand, null, null, $jobPayload);
                    $process->setTimeout(3600);
                    $process->run();

                    // Process should end with a proper exit code if everything goes well
                    if (false === $process->isSuccessful()) {
                        $this->verboseWrite(2, 'Process (' . $processCommand . ') execution failed.');
                        $this->log()->err(sprintf("Job execution failed. Queue '%s', job payload '%s'.", $queue->getId(), $jobPayload));
                        $this->log()->err(sprintf("Error output: %s", $process->getErrorOutput()));
                        $this->verboseWrite(6, 'Process error output: ' . $process->getErrorOutput());

                        if ($input->getOption('failing-job-quarantine')) {
                            $jobFile = sprintf("%s/bbq-failed-job.%s.%s.%s", $input->getOption('failing-job-quarantine'), getmypid(), gethostname(), time());
                            $this->verboseWrite(9, 'failing-job-quarantine is set, will store the failing job to ' . $jobFile);
                            
                            $wrote = file_put_contents($jobFile . '.payload', $jobPayload);
                            if ($wrote) {
                                $this->log()->err(sprintf("Failed job payload was saved to %s", $jobFile));
                            } else {
                                $this->log()->crit(sprintf("Could not write the failed job payload to %s", $jobFile));
                                
                                throw new \Exception('Failed job could not be stored to '. $jobFile);
                            }
                            
                            $jobMeta = sprintf('Origin queue: %s', $queue->getId()) . "\n" .
                                       sprintf('Command: %s', $processCommand) . "\n" .
                                       sprintf('STDERR: %s', $process->getErrorOutput()) . "\n" .
                                       sprintf('STDOUT: %s', $process->getOutput()) . "\n";

                            $wrote = file_put_contents($jobFile . '.meta', $jobMeta);
                            if ($wrote) {
                                $this->log()->info(sprintf("Failed job meta was saved to %s", $jobFile));
                            } else {
                                $this->log()->crit(sprintf("Could not write the failed job meta to %s", $jobFile));
                            }
                            
                            // Quarantined jobs will be removed from the original queue
                            $queue->finalizeJob($job);
                        }
                        
                        if ($input->getOption('quit-on-job-failure')) {
                            $this->verboseWrite(9, 'quit-on-job-failure is set, will exit.');
                            $this->exitRequestPending = true;
                        }
                    } else {
                        $output = $process->getOutput();
                        $this->verboseWrite(8, 'Process output: ' . $output);

                        if ($output === 'OK') {
                            $queue->finalizeJob($job);
                        } elseif ($output === 'NOT_HANDLED') {
                            $queue->releaseJob($job);
                        }
                    }

                    if ($input->getOption('max-jobs')) {
                        $jobCount++;

                        $this->verboseWrite(8, 'Now handled ' . $jobCount . ' jobs (max. ' . $input->getOption('max-jobs') . ')');

                        if ($jobCount >= $input->getOption('max-jobs')) {
                            break 2;
                        }
                    }
                }

                if ($this->shouldExist()) {
                    break 2;
                }
            }

            if ($input->getOption('max-loops')) {
                $loopCount++;

                $this->verboseWrite(8, 'Now looped ' . $loopCount . ' times (max. ' . $input->getOption('max-loops') . ')');

                if ($loopCount >= $input->getOption('max-loops')) {
                    break;
                }
            }

            if ($jobsInLoop) {
                $sleepTime = $sleepMin;
            }

            $this->verboseWrite(9, 'Sleeping ' . $sleepTime . ' seconds.');
            sleep($sleepTime);

            if (!$jobsInLoop && $sleepMax && $sleepTime < $sleepMax) {
                $this->verboseWrite(9, 'No jobs run, doubling sleepTime for the next loop.');
                $sleepTime = $sleepTime * 2;
                if ($sleepTime >= $sleepMax) {
                    $sleepTime = $sleepMax;
                    $this->verboseWrite(9, 'Sleep time maximum, setting to time to ' . $sleepTime);
                }
            }
        }

        $this->verboseWrite(9, 'Exiting...');
        return;
    }

}
