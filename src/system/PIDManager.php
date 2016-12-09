<?php
declare(strict_types = 1);
namespace edwardstock\forker\system;

use edwardstock\forker\exceptions\FsPermissionException;
use edwardstock\forker\log\Logger;
use Psr\Log\LoggerInterface;

/**
 * PID manager? Yes, this class writes pid file to manipulate all running processes
 * Also this class may working with multiple .pid files, just create instance with you own
 * @see    PIDManager::getInstance()
 *
 * atlas. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 */
class PIDManager
{
    /**
     * @var PIDManager
     */
    private static $instances = [];
    /**
     * @var string
     */
    private $pidFile;
    /**
     * @var int[]
     */
    private $current = [];

    /**
     * @var int
     */
    private $parentPid = 0;

    /**
     * @var LoggerInterface|Logger
     */
    private $log = null;

    /**
     * Can create multiple instance for each pid file
     *
     * @param string|null     $filepath
     *
     * @param LoggerInterface $logger
     *
     * @return PIDManager
     */
    public static function getInstance(string $filepath, LoggerInterface $logger = null): PIDManager
    {
        $hash = static::getPathHash($filepath);

        if (!isset(self::$instances[$hash])) {
            self::$instances[$hash] = new PIDManager($filepath, $logger);
        }

        return self::$instances[$hash];
    }

    /**
     * @param string $filepath
     *
     * @return int
     */
    private static function getPathHash(string $filepath): int
    {
        return crc32($filepath);
    }

    /**
     * PIDHandler constructor.
     *
     * @param string          $filepath
     * @param LoggerInterface $logger
     *
     * @throws FSPermissionException
     */
    private function __construct(string $filepath, LoggerInterface $logger = null)
    {
        if ($filepath !== null) {
            $this->pidFile = $filepath;
        }

        if (file_exists($filepath)) {
            @unlink($filepath);
        }

        $dir = dirname($this->pidFile);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0644, true)) {
                throw new FsPermissionException($dir, 0644);
            }
        }

        if ($logger === null) {
            $this->log = new Logger();
        } else {
            $this->log = $logger;
        }

        $this->load();
    }

    /**
     * @return string
     */
    public function getPidPath(): string
    {
        return dirname($this->pidFile);
    }

    /**
     * All pids excluding parent pid (if it was set or auto-detected)
     *
     * @param int  $parentPid 0 - auto detect, otherwise exclude specified pid
     * @param bool $reload    Fetch from file all pids
     *
     * @return array
     */
    public function getChildren(int $parentPid = 0, bool $reload = false): array
    {
        if ($parentPid === 0) {
            $parentPid = $this->parentPid;
        }

        if ($parentPid === 0) {
            return $this->getAll($reload);
        }

        $out = [];
        foreach ($this->getAll($reload) AS $pid) {
            if ($parentPid === $pid) {
                continue;
            }

            $out[] = $pid;
        }

        return $out;
    }

    /**
     * Get all pids stored in pidfile
     *
     * @param bool $reload
     *
     * @return array|\int[]
     */
    public function getAll(bool $reload = false): array
    {
        if ($reload) {
            $this->load();
        }

        return $this->current;
    }

    /**
     * @return int Parent pid
     */
    public function getParentPid(): int
    {
        return $this->parentPid;
    }

    /**
     * Check for pids is empty, and can try to reload all from file
     *
     * @param bool $reload
     *
     * @return bool
     */
    public function isEmpty(bool $reload = false): bool
    {
        if ($reload) {
            $this->load();
        }

        return sizeof($this->current) === 0;
    }

    /**
     * @param bool $flush  Flush local data to pidfile
     * @param bool $reload Very usefully replace for locks
     *
     * @return int
     */
    public function pop(bool $flush = true, bool $reload = false): int
    {
        if ($reload) {
            $this->load();
        }
        $retval = (int)array_shift($this->current);
        if ($flush) {
            $this->flush();
        }

        return $retval;
    }

    /**
     * @return int
     * @throws FsPermissionException
     */
    public function flush(): int
    {
        // children - first, parent - second
        rsort($this->current);
        $encoded = json_encode($this->current, JSON_NUMERIC_CHECK);

        if (($written = file_put_contents($this->pidFile, $encoded, LOCK_EX)) === false) {
            throw new FsPermissionException($this->pidFile);
        }

        return $written;
    }

    /**
     * @param array $pids
     *
     * @return int
     */
    public function addAll(array $pids): int
    {
        $added = 0;
        foreach ($pids AS $pid) {
            $added += $this->add($pid, false) ? 1 : 0;
        }

        if ($added > 0) {
            $this->flush();
        }

        return $added;
    }

    /**
     * @param int  $pid
     * @param bool $flush
     *
     * @param bool $isParent
     *
     * @return bool
     */
    public function add(int $pid, bool $flush = true, bool $isParent = false): bool
    {
        if (posix_getppid() === $pid || $isParent) {
            $this->parentPid = $pid;
        }

        if ($this->exists($pid)) {
            return false;
        }

        $this->current[] = $pid;

        if ($flush) {
            $this->flush();
        }

        return true;
    }

    /**
     * @param int $pid
     *
     * @return bool
     */
    public function exists(int $pid): bool
    {
        return in_array($pid, $this->current, true);
    }

    /**
     * @param array $pids
     *
     * @return int
     */
    public function removeAll(array $pids): int
    {
        $removed = 0;
        foreach ($pids AS $pid) {
            $removed += $this->remove($pid, false) ? 1 : 0;
        }

        if ($removed > 0) {
            $this->flush();
        }

        return $removed;
    }

    /**
     * @param int  $pid
     * @param bool $flush
     *
     * @return bool
     */
    public function remove(int $pid, bool $flush = true): bool
    {
        if (!$this->exists($pid)) {
            return false;
        }

        foreach ($this->current AS $k => $ePid) {
            if ($ePid === $pid) {
                unset($this->current[$k]);
            }
        }

        if ($flush) {
            $this->flush();
        }

        return true;
    }

    /**
     * @return int
     */
    public function clear(): int
    {
        $this->current = [];

        return $this->flush();
    }

    public function __destruct()
    {
        unset($this->current);
        if (file_exists($this->pidFile)) {
            @unlink($this->pidFile);
        }
    }


    private function load()
    {
        if (file_exists($this->pidFile) && is_writable($this->pidFile) && is_readable($this->pidFile)) {
            $encoded = file_get_contents($this->pidFile);
            if (strlen($encoded) === 0) {
                $this->current = [];

                return;
            }
            $this->current = json_decode($encoded, true, 2, JSON_NUMERIC_CHECK);
        } else {
            $this->current = [];
        }
    }
}