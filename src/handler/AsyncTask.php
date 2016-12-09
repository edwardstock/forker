<?php
declare(ticks = 1);
namespace edwardstock\forker\handler;

use edwardstock\forker\event\SignalDispatcher;
use edwardstock\forker\log\Logger;

/**
 * forker. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 */
abstract class AsyncTask
{
    use SignalDispatcher;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var null|mixed
     */
    private $result = null;

    /**
     * @var int
     */
    private $exitCode = 0;

    /**
     * Main background job
     *
     * @return mixed
     */
    abstract public function doInBackground(...$arguments);

    /**
     * @return mixed|null
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * @return Logger
     */
    public function getLogger()
    {
        if ($this->logger === null) {
            $this->logger = new Logger();
        }

        return $this->logger;
    }

    /**
     * Current process id (unix pid)
     * @return int
     */
    public function getPid(): int
    {
        return posix_getpid();
    }

    /**
     * Calling parent::onPreExecute() is required if you will override this method
     * and if you want to add signal dispatcher, you must call this after attaching new handlers,
     * otherwise signal handlers will not be called and you will get a uncontrollable process, that you can kill only
     * via kill $PID -9
     * @return mixed|void
     */
    public function onPreExecute()
    {
        // do some before executing
        $class = get_called_class() === CallbackTask::class ? "callback" : get_called_class();
        $this->getLogger()->setPrefix('[' . $class . '][' . $this->getPid() . ']');

        $this->dispatchSignals();
    }

    /**
     * @return int
     */
    public function getExitCode(): int
    {
        return $this->exitCode;
    }

    /**
     * This method calling after doInBackground()
     * Will not called if error occurred, will called onError($exception) instead
     *
     * @param mixed $result
     * @param int   $exitCode
     *
     * @return mixed|void
     */
    public function onPostExecute($result, int $exitCode)
    {
        $this->result   = $result;
        $this->exitCode = $exitCode;
        // do some after executing
    }

    /**
     * If you didn't catch some exceptions in your worker, they will passed to this method
     *
     * @param \Throwable $exception
     *
     * @throws \Throwable
     */
    public function onError(\Throwable $exception)
    {
        $this->getLogger()->error($exception);
    }

    /**
     * @return int System process priority from -20 to 20
     * @see your system's setpriority(2) man page for specific details.
     */
    public function getPriority(): int
    {
        return 0;
    }

    /**
     * Process title
     * @see cli_set_process_title()
     * @return string|null
     */
    public function getProcessTitle()
    {
        return null;
    }
}
