<?php
namespace edwardstock\forker\helpers;

use edwardstock\forker\log\Loggable;

/**
 * forker. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 */
trait ProcessHelper
{
    use Loggable;

    /**
     * @param string $title
     *
     * @throws \Throwable
     */
    public function setProcessTitle(string $title = null)
    {
        if ($title === null) {
            return;
        }

        if (strlen($title) === 0) {
            throw new \InvalidArgumentException('Process title cannot be empty');
        }

        // @codeCoverageIgnoreStart
        if (!function_exists('cli_set_process_title')) {
            $this->getLogger()->warning("function [cli_set_process_title] does not exists");

            return;
        }
        // @codeCoverageIgnoreEnd

        try {
            cli_set_process_title($title);
        } catch (\Throwable $e) {
            if ($e->getCode() === 2) {
                $this->getLogger()->warning($e->getMessage() . ". Probably, permission denied to set process title. Try again with privileged user");
            } else {
                throw $e;
            }
        }

    }

    /**
     * @param int $priority
     * @param int $pid
     */
    public function setPriority(int $priority, int $pid = 0)
    {
        if ($priority > 20 || $priority < -20) {
            throw new \InvalidArgumentException('Process priority must be from -20 to 20');
        }
        pcntl_setpriority($priority, $pid > 0 ? $pid : posix_getpid());
    }
}