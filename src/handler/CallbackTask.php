<?php
namespace edwardstock\forker\handler;

/**
 * forker. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 */
class CallbackTask extends AsyncTask
{
    /**
     * @var callable
     */
    protected $handler;

    /**
     * @var callable
     */
    protected $future = null;

    /**
     * @var callable
     */
    protected $errorHandler = null;

    /**
     * @var array
     */
    private $argument = null;

    /**
     * @var BatchTask|null
     */
    private $batch = null;

    /**
     * @param BatchTask $task
     * @param mixed     $data
     *
     * @return CallbackTask
     */
    public static function createFromBatch(BatchTask $task, $key, $value)
    {
        $cTask = new static([$task, 'doInBackground']);
        $cTask->future([$task, 'onPostExecute']);
        $cTask->error([$task, 'onError']);
        $cTask->argument = [$key, $value];
        $cTask->batch    = $task;

        return $cTask;
    }

    /**
     * @param callable $job
     *
     * @return CallbackTask
     */
    public static function create(callable $job, callable $future = null): CallbackTask
    {
        return new static($job, $future);
    }

    /**
     * CallbackHandler constructor.
     *
     * @param callable      $handler
     * @param callable|null $future
     */
    protected function __construct(callable $handler, callable $future = null)
    {
        $this->handler = $handler;
        $this->future  = $future;
    }

    /**
     * @param callable $future ($result, int $exitCode)
     *
     * @return $this
     */
    public function future(callable $future)
    {
        $this->future = $future;

        return $this;
    }

    /**
     * @param callable $errorHandler (\Throwable $exception, CallbackHandler $handler)
     *
     * @return $this
     */
    public function error(callable $errorHandler)
    {
        $this->errorHandler = $errorHandler;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function doInBackground(...$arguments)
    {
        $handler = $this->handler;

        if ($this->batch !== null) {
            return $handler($this->argument[0], $this->argument[1], $this, ...$arguments);
        }

        return $handler($this, ...$arguments);
    }

    /**
     * @inheritdoc
     */
    public function onPostExecute($result, int $exitCode)
    {
        if (is_callable($this->future)) {
            $handler = $this->future;
            $handler($result, $this);
        }
        parent::onPostExecute($result, $exitCode);
    }

    /**
     * @inheritdoc
     */
    public function onError(\Throwable $exception)
    {
        if (is_callable($this->errorHandler)) {
            $handler = $this->errorHandler;
            $handler($exception, $this);
        } else {
            throw $exception;
        }
    }
}