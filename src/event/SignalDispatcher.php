<?php
namespace edwardstock\forker\event;

use edwardstock\forker\log\Loggable;

/**
 * This trait helps you to handle system signals
 *
 * forker. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 */
trait SignalDispatcher
{
    use Loggable;

    /**
     * Default very usefully signals that will handled
     * @var array
     */
    private static $defaultSignals = [
        // killing process via terminal Ctrl^C
        SIGINT,
        // detaching process from terminal, usefully if you run process via terminal and terminal was closed or disconnected
        SIGHUP,
        // see linux man
        SIGABRT,
        // normal process terminating
        SIGTERM,

        // also you can attach SIGUSR1 or SIGUSR2 signal handlers to do some what you want
    ];

    /**
     * @var bool
     */
    private $isTerminated = false;

    /**
     * @var callable[]
     */
    private $handlers = [];

    /**
     * @var callable[]
     */
    private $commonHandlers = [];

    /**
     * @return \callable[]
     */
    public function getSignalHandlers()
    {
        return $this->handlers;
    }

    /**
     * @return \callable[]
     */
    public function getSignalCommonHandlers()
    {
        return $this->commonHandlers;
    }

    /**
     * Attach handler, that will called at any signal
     *
     * @param callable $handler
     */
    public function attachSignalCommonHandler(callable $handler)
    {
        $this->commonHandlers[] = $handler;
    }

    /**
     * Attach handler, that will called at specified signal
     * Note: default process behavior will not be overwritten, process still can be SIGTERM'ed or SIGKILL'ed
     *
     * @param int      $signal
     * @param callable $handler
     * @param bool     $replace
     */
    public function attachSignalHandler(int $signal, callable $handler, bool $replace = false)
    {
        if ($replace) {
            $this->handlers[$signal] = [$handler];
        } else {
            $this->handlers[$signal][] = $handler;
        }
    }

    /**
     * Remove all additional signal handlers by specified signal
     *
     * @param int $signal
     */
    public function detachSignalHandlers(int $signal)
    {
        if (!isset($this->handlers[$signal])) {
            return;
        }

        unset($this->handlers[$signal]);
    }

    /**
     * Attach handler, that will called at specified signals
     *
     * @param array    $signals
     * @param callable $handler
     */
    public function attachSignalsHandler(array $signals, callable $handler)
    {
        foreach ($signals AS $signal) {
            $this->handlers[$signal][] = $handler;
        }
    }

    /**
     * Dispatches signal stack and check the process is terminated
     * Use this method in infinite loop of your worker, otherwise you have to control signals by yourself
     * @return bool
     */
    public function isTerminated(): bool
    {
        $memUse = sprintf('%.3f MB', memory_get_peak_usage() / 1048576);
        $this->getLogger()->debug("Memory usage: {$memUse}");
        pcntl_signal_dispatch();

        return $this->isTerminated;
    }

    /**
     * Registering of signal handlers
     */
    public function dispatchSignals()
    {
        $sigHandler = function (int $signo) {
            $this->getLogger()->warning("Signal {$signo} caught to " . posix_getpid());
            $this->isTerminated = true;

            foreach ($this->handlers AS $signal => $handlers) {
                if ($signo !== $signal) {
                    continue;
                }

                foreach ($handlers AS $handler) {
                    try { // prevent stopping handler while exception occurred, we must stop process when signal has come
                        $handler($signo);
                    } catch (\Throwable $e) {
                        $this->getLogger()->error($e);
                    }
                }
            }

            foreach ($this->commonHandlers AS $handler) {
                try { // prevent stopping handler while exception occurred, we must stop process when signal has come
                    $handler($signo);
                } catch (\Throwable $e) {
                    $this->getLogger()->error($e);
                }
            }

            exit(0);
        };
        foreach (self::$defaultSignals AS $signal) {
            $this->getLogger()->debug("Registering signal {$signal}");
            pcntl_signal($signal, $sigHandler);
        }


        $customSignals = [];
        foreach ($this->handlers AS $signal => $handlers) {
            if ($signal === 0) {
                continue;
            }

            if (!in_array($signal, self::$defaultSignals)) {
                $customSignals[$signal] = function ($signo) use ($handlers) {
                    foreach ($handlers AS $handler) {
                        $handler($signo);
                    }
                };
            }
        }

        foreach ($customSignals AS $signal => $handler) {
            $this->getLogger()->debug("Registering custom signal {$signal}");
            pcntl_signal($signal, $handler);
        }
    }
}