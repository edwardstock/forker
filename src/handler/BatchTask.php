<?php
namespace edwardstock\forker\handler;

/**
 * forker. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 */
class BatchTask extends AsyncTask
{
    /**
     * @var callable
     */
    private $handler;

    /**
     * @var callable
     */
    private $future = null;

    /**
     * @var callable
     */
    private $errorHandler = null;

    /**
     * @var mixed[]
     */
    private $data = [];

    /**
     * @var bool
     */
    private $each = false;

    /**
     * @var bool
     */
    private $preserveKeys = false;

    /**
     * @param array|mixed $data
     * @param callable    $handler
     *
     * @return BatchTask
     */
    public static function create($data, callable $handler)
    {
        $task          = new static();
        $task->handler = $handler;
        if (is_array($data)) {
            $task->data = $data;
        } else {
            $task->data[] = $data;
        }

        return $task;
    }

    /**
     * @param callable $futureFunc
     *
     * @return $this
     */
    public function future(callable $futureFunc)
    {
        $this->future = $futureFunc;

        return $this;
    }

    /**
     * @param callable $errorFunc
     *
     * @return $this
     */
    public function error(callable $errorFunc)
    {
        $this->errorHandler = $errorFunc;

        return $this;
    }

    /**
     * Each result - means that when background function will have a result,
     * future function will called many times, as the data size
     * @see BatchTask::skipNullData()
     *
     * @param bool $eachResulted
     *
     * @return $this
     */
    public function eachResult(bool $eachResulted = true)
    {
        $this->each = $eachResulted;

        return $this;
    }

    /**
     * @return bool
     */
    public function isEachResultCall(): bool
    {
        return $this->each;
    }

    /**
     * @param bool $preserve
     *
     * @return $this
     */
    public function preserveKeys(bool $preserve = true)
    {
        $this->preserveKeys = $preserve;

        return $this;
    }

    /**
     * @return mixed[]
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param mixed[] $data
     *
     * @return $this
     */
    public function setData(array $data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @param $data
     *
     * @return $this
     */
    public function addData($data)
    {
        if (is_array($data)) {
            foreach ($data AS $key => $value) {
                $this->data[] = $value;
            }

            return $this;
        }

        $this->data[] = $data;

        return $this;
    }

    /**
     * Main background job
     *
     * @return mixed
     * @codeCoverageIgnore
     */
    public function doInBackground(...$arguments)
    {
        $handler = $this->handler;

        return $handler(...$arguments);
    }

    public function onPostExecute($result, int $exitCode)
    {
        if (is_callable($this->future)) {
            $handler = $this->future;
            $handler($result, $this);
        }
        parent::onPostExecute($result, $exitCode);
    }

    /**
     * @return callable
     */
    public function getHandler()
    {
        return $this->handler;
    }

    /**
     * @return callable
     */
    public function getFutureHandler()
    {
        return $this->future;
    }

    /**
     * @return callable
     */
    public function getErrorHandler()
    {
        return $this->errorHandler;
    }
}