<?php
namespace edwardstock\forker\log;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;

/**
 * atlas. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 */
class Logger implements LoggerInterface
{
    use LoggerTrait;

    const NONE      = 0;
    const EMERGENCY = (1 << 0);
    const ALERT     = (1 << 1);
    const CRITICAL  = (1 << 2);
    const ERROR     = (1 << 3);
    const WARNING   = (1 << 4);
    const NOTICE    = (1 << 5);
    const INFO      = (1 << 6);
    const DEBUG     = (1 << 7);
    const PROFILE   = (1 << 8);

    const V_NONE    = self::NONE;
    const V_NORMAL  = (self::EMERGENCY | self::ALERT | self::CRITICAL | self::ERROR);
    const V_VERBOSE = (self::V_NORMAL | self::INFO | self::NOTICE);
    const V_DEBUG   = (self::V_VERBOSE | self::DEBUG | self::PROFILE);
    const V_ALL     =
        (
            self::EMERGENCY | self::ALERT | self::CRITICAL | self::ERROR |
            self::WARNING | self::NOTICE | self::INFO | self::DEBUG | self::PROFILE
        );

    /**
     * Logging levels from syslog protocol defined in RFC 5424
     *
     * @var array $levels Logging levels
     */
    protected static $levels = [
        self::NONE      => 'none',
        self::EMERGENCY => LogLevel::EMERGENCY,
        self::ALERT     => LogLevel::ALERT,
        self::CRITICAL  => LogLevel::CRITICAL,
        self::ERROR     => LogLevel::ERROR,
        self::WARNING   => LogLevel::WARNING,
        self::NOTICE    => LogLevel::NOTICE,
        self::INFO      => LogLevel::INFO,
        self::DEBUG     => LogLevel::DEBUG,
        self::PROFILE   => 'profile',
    ];

    /**
     * @var int
     */
    private static $globalLevel = self::V_NORMAL;

    /**
     * @var array
     */
    private $profiling = [];

    /**
     * @var bool
     */
    private $redefinedStdout = false;

    /**
     * @var bool
     */
    private $redefinedStderr = false;

    /**
     * @var array
     */
    private $levelColors = [
        'profile'           => 'light_green',
        LogLevel::DEBUG     => null,
        LogLevel::INFO      => 'green',
        LogLevel::NOTICE    => 'light_green',
        LogLevel::ALERT     => 'light_purple',
        LogLevel::EMERGENCY => 'red',
        LogLevel::WARNING   => 'yellow',
        LogLevel::ERROR     => 'light_red',
        LogLevel::CRITICAL  => 'red',
    ];

    private $fgColors = [
        'black'        => '0;30',
        'dark_gray'    => '1;30',
        'blue'         => '0;34',
        'light_blue'   => '1;34',
        'green'        => '0;32',
        'light_green'  => '1;32',
        'cyan'         => '0;36',
        'light_cyan'   => '1;36',
        'red'          => '0;31',
        'light_red'    => '1;31',
        'purple'       => '0;35',
        'light_purple' => '1;35',
        'brown'        => '0;33',
        'yellow'       => '1;33',
        'light_gray'   => '0;37',
        'white'        => '1;37',
    ];

    /**
     * @var null
     */
    private $name = null;

    /**
     * @return int
     */
    public static function & getLevel(): int
    {
        return static::$globalLevel;
    }

    /**
     * @param int $level
     */
    public static function setLevel(int $level)
    {
        static::$globalLevel = $level;
    }

    public function __construct($name = null)
    {
        $this->name = $name;
    }

    /**
     * @param string $prefix
     */
    public function setPrefix(string $prefix)
    {
        if ($this->name === null) {
            $this->name = $this->escapePrefix($prefix);
        } else {
            $this->name = $this->escapePrefix($this->name);
            $this->name .= $this->escapePrefix($prefix);
        }
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param string $level
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function log($level, $message, array $context = [])
    {
        $flipped  = array_flip(static::$levels);
        $intLevel = $flipped[$level];

        if (!$this->isHandling($intLevel)) {
            return;
        }

        $format = '';
        $format .= "[" . date('Y-m-d H:i:s') . "][{$level}]";
        if ($this->name !== null) {
            $format .= $this->escapePrefix($this->name);
        }
        $format .= ' ';

        if (!empty($context)) {
            $message .= "\n" . var_export($context, true);
        }

        if ($this->levelColors[$level] === null) {
            $format .= $message;
        } else {
            $color  = $this->levelColors[$level];
            $format = $this->getColoredString($format . $message, $color);
        }

        $format .= PHP_EOL;

        fwrite($this->getOutputStream(), $format);
    }

    /**
     * Alias for error()
     *
     * @param       $message
     * @param array $context
     *
     * @return mixed|void
     */
    public function err($message, array $context = [])
    {
        $this->error($message, $context);
    }

    /**
     * @param \Throwable|string $message
     * @param array             $context
     *
     * @return mixed|void
     */
    public function error($message, array $context = [])
    {
        if ($message instanceof \Throwable) {
            $m = "[{$message->getCode()}] " . $message->getMessage();
            $m .= "\n";
            $m .= $message->getTraceAsString();
            $this->error($m, $context); // calling back this method with string argument
            return;
        }

        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * @param string $key
     */
    public function beginProfile(string $key)
    {
        $this->profiling[$key] = [
            'time' => microtime(true),
            'mem'  => memory_get_usage(),
        ];
    }

    /**
     * @param string      $key
     * @param string|null $description
     * @param array       $context
     */
    public function endProfile(string $key, string $description = null, array $context = [])
    {
        if (!isset($this->profiling[$key])) {
            return;
        }

        $startTime = $this->profiling[$key]['time'];
        $startMem  = $this->profiling[$key]['mem'];

        $timeResult = microtime(true) - $startTime;
        $memResult  = memory_get_usage() - $startMem;
        $memUse     = sprintf('%.3f MB', $memResult / 1048576);

        unset($this->profiling[$key]);

        $msg = $description ?? "{$key}";
        $msg .= ' ' . ($timeResult * 1000) . " ms; memory usage: {$memUse}";

        $this->log('profile', $msg, $context);

    }

    public function __destruct()
    {
        if ($this->redefinedStdout) {
            fclose(STDOUT);
        }

        if ($this->redefinedStderr) {
            fclose(STDERR);
        }
    }

    private function escapePrefix($s)
    {
        if (strpos($s, '[') === false || strpos($s, ']') === false) {
            return "[{$s}]";
        }

        return $s;
    }

    /**
     * @return resource
     */
    private function getOutputStream()
    {
        if (!defined('STDOUT') || feof(STDOUT)) {
            define('STDOUT', fopen('php://stdout', 'w'));
            $this->redefinedStdout = true;
        }

        return STDOUT;
    }

    /**
     * @return resource
     */
    private function getErrorStream()
    {
        if (!defined('STDERR')) {
            define('STDERR', fopen('php://stderr', 'w'));
            $this->redefinedStderr = true;
        }

        return STDERR;
    }

    /**
     * Returns colored string
     *
     * @param             $string
     * @param string|null $fgColor
     *
     * @return string
     */
    private function getColoredString($string, $fgColor = null)
    {
        $colored = "";

        // Check if given foreground color found
        if (isset($this->fgColors[$fgColor])) {
            $colored .= "\033[" . $this->fgColors[$fgColor] . "m";
        }

        // Add string and end coloring
        $colored .= $string . "\033[0m";

        return $colored;
    }

    private function isHandling(int $intLevel): bool
    {
        return (static::$globalLevel & $intLevel) === $intLevel;
    }
}