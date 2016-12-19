<?php
namespace edwardstock\forker;

declare(ticks = 1);
use Ds\PriorityQueue;
use edwardstock\forker\event\SignalDispatcher;
use edwardstock\forker\exceptions\WaitTimeoutException;
use edwardstock\forker\handler\AsyncTask;
use edwardstock\forker\handler\BatchTask;
use edwardstock\forker\handler\CallbackTask;
use edwardstock\forker\handler\IHandler;
use edwardstock\forker\helpers\ProcessHelper;
use edwardstock\forker\log\Loggable;
use edwardstock\forker\log\Logger;
use edwardstock\forker\system\BatchGroupRunner;
use edwardstock\forker\system\GroupRunner;
use edwardstock\forker\system\PIDManager;
use Psr\Log\LoggerInterface;

define('PID_FILE', realpath(__DIR__ . '/../runtime') . '/forker.pid') or defined('PID_FILE');

/**
 * atlas. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 */
class ProcessManager
{
    use Loggable;
    use SignalDispatcher;
    use ProcessHelper;

    const P_JOIN      = 0x02;
    const P_DETACH    = 0x04;
    const P_DAEMONIZE = 0x08;

    /**
     * @var PIDManager
     */
    private $pidHandler;

    /**
     * @var PriorityQueue|AsyncTask[]
     */
    private $jobs;

    /**
     * @var PriorityQueue|BatchTask[]
     */
    private $batches;

    /**
     * @var GroupRunner[]
     */
    private $runGroups = [];

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var int
     */
    private $poolSize = -1;

    /**
     * @var bool
     */
    private $forceJoin = false;

    /**
     * Process constructor.
     *
     * @param string|null $pidFile
     */
    public function __construct(string $pidFile = null)
    {
        $this->logger = new Logger('main');

        $this->pidHandler = PIDManager::getInstance($pidFile ?? PID_FILE);
        $this->pidHandler->add(posix_getpid(), true, true);

        chdir('/'); //prevent un-mounting cwd
        umask(0); // mask fs rights to current user
    }

    /**
     * @param int $level
     */
    public function setLogLevel(int $level)
    {
        Logger::setLevel($level);
    }

    /**
     * @param int $group
     *
     * @throws \InvalidArgumentException
     */
    public function wait(int $group = 0)
    {
        if ($group > 0 && !array_key_exists($group, $this->runGroups)) {
            throw new \InvalidArgumentException("Group with id {$group} does not exists or run");
        }

        foreach ($this->runGroups AS $id => $runner) {
            if ($group > 0 && $group !== $id) {
                continue;
            }

            $runner->wait();
        }

        foreach ($this->runGroups AS $gid => $group) {
            if ($group->getStatus() != GroupRunner::INIT) {
                unset($this->runGroups[$gid]);
            }
        }
    }


    /**
     * @param bool  $join
     * @param int[] $groupId Ids of running groups
     *
     * @return ProcessManager
     */
    public function run(bool $join = false, &$groupId = null): ProcessManager
    {
        foreach ($this->runGroups AS $gid => $group) {
            if ($group->getStatus() != GroupRunner::INIT) {
                unset($this->runGroups[$gid]);
            }
        }


        $this->dispatchSignals();

        $gids = [];

        if ($this->batches !== null && !$this->batches->isEmpty()) {
            foreach ($this->batches AS $batchTask) {
                $batchJobs = new PriorityQueue();
                foreach ($batchTask->getData() AS $key => $value) {
                    $batchJobs->push(CallbackTask::createFromBatch($batchTask, $key, $value), 0);
                }

                $batchRunner                            = new BatchGroupRunner($this->pidHandler, $batchJobs,
                    $batchTask);
                $this->runGroups[$batchRunner->getId()] = $batchRunner;
                $gids[]                                 = $batchRunner->getId();
            }
        }


        if ($this->jobs !== null && !$this->jobs->isEmpty()) {
            $runner = new GroupRunner($this->pidHandler, $this->jobs);
            $runner->setFlags($join ? self::P_JOIN : self::P_DETACH);
            $this->runGroups[$runner->getId()] = $runner;
            $gids[]                            = $runner->getId();
        }

        $groupId = $gids;

        foreach ($this->runGroups AS $group) {
            $group->run();
        }

        return $this;
    }

    /**
     * @param int  $size
     * @param bool $join
     *
     * @return $this
     */
    public function pool(int $size, bool $join = false)
    {
        $this->poolSize  = $size;
        $this->forceJoin = $join;

        return $this;
    }

    /**
     * @param int $signal
     * @param int $groupId
     */
    public function stop(int $signal = SIGTERM, int $groupId = 0)
    {
        foreach ($this->runGroups AS $group) {
            if ($groupId > 0 && $group->getId() !== $groupId) {
                continue;
            }
            $this->logger->debug("Stopping group {$groupId}");
            $group->stop($signal);
        }
    }

    /**
     * @param AsyncTask $job
     * @param int       $priority
     *
     * @return $this
     */
    public function add(AsyncTask $job, int $priority = 0)
    {
        if ($job instanceof BatchTask) {
            if ($this->batches === null) {
                $this->batches = new PriorityQueue();
            }

            $this->batches->push($job, $priority);
        } else {
            if ($this->jobs === null) {
                $this->jobs = new PriorityQueue();
            }

            $this->performPool();
            $this->jobs->push($job, $priority);
            $this->performPool();
        }

        return $this;
    }

    /**
     * @return Logger|LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @return array
     * @throws WaitTimeoutException
     */
    public function getWorkingPids(): array
    {
        $out = [];
        foreach ($this->runGroups AS $group) {
            foreach ($group->getPids() AS $pid) {
                $out[] = $pid;
            }
        }

        return $out;
    }

    private function performPool()
    {
        if ($this->poolSize < 1) {
            return;
        }

        if ($this->jobs !== null && $this->jobs->count() === $this->poolSize) {
            $this->run($this->forceJoin)->wait();
        } else if ($this->batches !== null && $this->batches->count() === $this->poolSize) {
            $this->run($this->forceJoin)->wait();
        }
    }


}