<?php
namespace edwardstock\forker\system;

use Ds\PriorityQueue;
use edwardstock\forker\handler\AsyncTask;
use edwardstock\forker\helpers\ProcessHelper;
use edwardstock\forker\log\Loggable;
use edwardstock\forker\log\Logger;
use edwardstock\forker\ProcessManager;
use Psr\Log\LoggerInterface;

/**
 * forker. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 * This is internal class, don't use it directly
 */
class GroupRunner
{
    use Loggable;
    use ProcessHelper;

    const INIT     = 0;
    const STARTING = 1;
    const STARTED  = 2;
    const STOPPED  = 3;
    const COMPLETE = 4;

    protected static $idCounter = 0;

    /**
     * @var int
     */
    protected $id;

    /**
     * @var int
     */
    protected $status = self::INIT;

    /**
     * @var int
     */
    protected $flags = 0;

    /**
     * @var AsyncTask[]
     */
    protected $jobs = [];

    /**
     * @var int[]
     */
    protected $runPids = [];

    /**
     * @var Logger|LoggerInterface
     */
    protected $logger;

    /**
     * @var SharedMemoryManager
     */
    protected $shm;

    /**
     * @var PIDManager
     */
    protected $pidManager;

    /**
     * GroupRunner constructor.
     *
     * @param PIDManager    $pidManager
     * @param PriorityQueue $jobs
     */
    public function __construct(PIDManager $pidManager, PriorityQueue $jobs)
    {
        if ($jobs->isEmpty()) {
            throw new \InvalidArgumentException('Cannot run 0 jobs!');
        }

        foreach ($jobs AS $job) {
            $this->jobs[] = $job;
        }

        $this->pidManager = $pidManager;
        $this->shm        = new SharedMemoryManager();

        static::$idCounter++;
        $this->id     = static::$idCounter;
        $this->logger = new Logger('[runner][group:' . $this->getId() . ']');
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     *
     */
    public function wait()
    {
        $complete = 0;
        $failed   = 0;
        $stopped  = 0;

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

            if (($this->flags & ProcessManager::P_JOIN) === ProcessManager::P_JOIN) {
                if ($this->shm->exists($pid)) {
                    $jobOffset = 0;
                    $result    = $this->shm->read($pid, $jobOffset);
                    $this->jobs[$jobOffset]->onPostExecute($result, $exitCode);
                } else {
                    $this->logger->warning("Return data for {$pid} didn't found");
                }
            }
        }

        $this->status = self::COMPLETE;

        $this->logger->info("{$complete} job(s) complete; {$failed} failed; stopped: {$stopped}");
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
                    $result = $job->doInBackground(...$job->getArguments());

                    if (($this->flags & ProcessManager::P_JOIN) === ProcessManager::P_JOIN) {
                        $job->getLogger()->debug("Writing result to shm");
                        $this->shm->write($job->getPid(), $id, $result);
                    } else {
                        // calling after job exited, to prevent double call onPostExecute()
                        $job->onPostExecute($result, 0);
                    }

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

    /**
     * @param int $signal
     *
     * @throws \Exception
     */
    public function stop(int $signal = SIGTERM)
    {
        if ($this->status === self::COMPLETE) {
            throw new \Exception('Jobs already stopped');
        }

        foreach ($this->runPids AS $pid) {
            $this->logger->debug("SIGTERM {$pid}");
            posix_kill($pid, $signal);
        }

        $this->status = self::STOPPED;
    }

    /**
     * @param int $flags
     */
    public function setFlags(int $flags)
    {
        $this->flags = $flags;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    public function getPids()
    {
        return $this->runPids;
    }

    /**
     * @return Logger
     */
    public function getLogger()
    {
        return $this->logger;
    }
}