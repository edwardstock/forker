<?php
namespace edwardstock\forker\system;

use Ds\PriorityQueue;
use edwardstock\forker\handler\AsyncTask;
use edwardstock\forker\handler\BatchTask;
use edwardstock\forker\log\Logger;

/**
 * forker. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 */
class BatchGroupRunner extends GroupRunner
{
    /**
     * @var BatchTask
     */
    protected $batchTask;

    /**
     * BatchGroupRunner constructor.
     *
     * @param PIDManager    $pidManager
     * @param PriorityQueue $jobs
     * @param BatchTask     $batchTask
     */
    public function __construct(PIDManager $pidManager, PriorityQueue $jobs, BatchTask $batchTask)
    {
        parent::__construct($pidManager, $jobs);
        $this->logger    = new Logger('[batch-runner][group:' . $this->getId() . ']');
        $this->batchTask = $batchTask;
    }

    /**
     *
     */
    public function wait()
    {
        $complete = 0;
        $failed   = 0;
        $stopped  = 0;

        $results = [];

        foreach ($this->runPids AS $pid) {
            if ($this->status === self::COMPLETE) {
                return;
            } else if ($this->status === self::STOPPED) {
                $stopped++;
                continue;
            }

            pcntl_waitpid($pid, $status, WUNTRACED);
            pcntl_signal_dispatch();

            $exitCode = pcntl_wexitstatus($status);
            if ($exitCode === 0) {
                $complete++;
            } else {
                $failed++;
            }

            if ($this->shm->exists($pid)) {
                $jobOffset = 0;
                $result    = $this->shm->read($pid, $jobOffset);
                if ($this->batchTask->isEachResultCall()) {
                    try {
                        $this->batchTask->onPostExecute($result, 0);
                    } catch (\Throwable $e) {
                        $this->batchTask->onError($e);
                    }

                } else {
                    $results[$jobOffset] = $result;
                }
            } else {
                $this->logger->warning("Return data for {$pid} didn't found");
            }
        }

        if (!$this->batchTask->isEachResultCall()) {
            try {
                $this->batchTask->onPostExecute($results, 0);
            } catch (\Throwable $e) {
                $this->batchTask->onError($e);
            }
        }

        $this->status = self::COMPLETE;

        $this->logger->info("Batch: {$complete} job(s) complete; {$failed} failed; stopped: {$stopped}");
    }

    public function run()
    {
        $id = 0;
        foreach ($this->jobs AS $job) {
            /** @var AsyncTask $job */
            $pid = pcntl_fork();
            if ($pid === 0) { // child
                $job->onPreExecute();
                $this->pidManager->add(posix_getpid());
                $job->getLogger()->debug("Setting process priority {$job->getPriority()}");
                $this->setPriority($job->getPriority());

                $this->setProcessTitle($job->getProcessTitle());

                try {
                    $job->getLogger()->debug("Executing worker");
                    $result = $job->doInBackground();

                    $job->getLogger()->debug("Writing result to shm");
                    $this->shm->write($job->getPid(), $id, $result);
                } catch (\Throwable $e) {
                    $job->onError($e);

                    exit(1);
                }

                exit(0);
            } else { // parent
                $this->runPids[] = $pid;
            }

            $id++;
        }
    }
}