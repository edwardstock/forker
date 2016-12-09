<?php
namespace edwardstock\forker\system;

use edwardstock\forker\helpers\Serializer;
use edwardstock\forker\log\Loggable;
use edwardstock\forker\log\Logger;
use Psr\Log\LoggerInterface;

/**
 * forker. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 */
class SharedMemoryManager
{
    use Loggable;

    const F_SERIALIZED      = (2 << 2);
    const F_PACKED          = (2 << 3);
    const F_IS_INT          = (2 << 4);
    const F_IS_FLOAT        = (2 << 5);
    const F_IS_BOOL         = (2 << 6);
    const F_IS_STRING       = (2 << 7);
    const F_IS_ARRAY        = (2 << 8);
    const F_IS_ARRAY_OBJECT = (2 << 9);
    const F_IS_NULL         = (2 << 10);

    const P_INT   = 'Q';
    const P_FLOAT = 'd';

    /**
     * @see SharedMemoryManager::packHeader()
     */
    const HEADER_SIZE = 16;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * SharedMemoryManager constructor.
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger = null)
    {
        if ($logger === null) {
            $this->logger = new Logger();
        } else {
            $this->logger = $logger;
        }

        $this->logger->setPrefix('[shm]');
    }

    /**
     * @param int   $id
     * @param int   $offset
     * @param mixed $data
     * @param int   $flags
     *
     * @return int
     */
    public function write(int $id, int $offset, $data, int $flags = 0): int
    {
        $this->logger->beginProfile("write_{$id}");

        $storeValue = null;

        $stateFlags = 0;
        if (is_object($data)) {
            $storeValue = Serializer::serialize($data);
            $stateFlags |= self::F_SERIALIZED;
            $this->logger->debug(sprintf("Set F_SERIALIZED 0x%02x to process result value for id %d",
                self::F_SERIALIZED,
                $id));
        } else if (is_int($data)) {
            $storeValue = pack(self::P_INT, $data);
            $stateFlags |= self::F_IS_INT | self::F_PACKED;
            $this->logger->debug(sprintf("Set F_IS_INT | F_PACKED 0x%02x to process result value for id %d",
                self::F_IS_INT | self::F_PACKED, $id));
        } else if (is_float($data)) {
            $storeValue = pack(self::P_FLOAT, $data);
            $stateFlags |= self::F_IS_FLOAT | self::F_PACKED;
            $this->logger->debug(sprintf("Set F_IS_FLOAT | F_PACKED 0x%02x to process result value for id %d",
                self::F_IS_FLOAT | self::F_PACKED, $id));
        } else if (is_array($data)) {
            $storeValue = Serializer::serialize($data);
            $stateFlags |= self::F_IS_ARRAY | self::F_SERIALIZED;
            $this->logger->debug(sprintf("Set F_IS_ARRAY | F_SERIALIZED 0x%02x to process result value for id %d",
                self::F_IS_ARRAY | self::F_SERIALIZED, $id));
        } else if (is_bool($data)) {
            $stateFlags |= self::F_IS_BOOL;
            $this->logger->debug(sprintf("Set F_IS_BOOL 0x%02x to process result value for id %d", self::F_IS_BOOL,
                $id));
            $storeValue = $data;
        } else if (is_null($data)) {
            $stateFlags |= self::F_IS_NULL;
            $this->logger->debug(sprintf("Set F_IS_NULL 0x%02x to process result value for id %d", self::F_IS_NULL,
                $id));
            $storeValue = 0;
        } else if (is_string($data)) {
            $stateFlags |= self::F_IS_STRING;
            $this->logger->debug(sprintf("Set F_IS_STRING 0x%02x to process result value for id %d", self::F_IS_STRING,
                $id));
            $storeValue = $data;
        } else {
            throw new \InvalidArgumentException('Unsupported result type: ' . gettype($data));
        }

        $stateFlags |= $flags;

        $size = $this->sizeof($storeValue);
        $this->logger->debug(sprintf("Size of result value for id %d: 0x%08x", $id, $size));
        $header = $this->packHeader($id, $offset, $size, $stateFlags);

        if ($this->exists($id)) {
            $this->logger->debug("New shared block already exists for id {$id}. Deleting...");
            $this->delete($id);
        }

        try {
            $sh = shmop_open($this->getSharedItemKey($id), 'c', 0644, self::HEADER_SIZE + $size);

            if (!$sh) {
                $this->logger->error(sprintf("Cannot open shared memory block with size 0x%08x for id %d", $size, $id));

                return 0;
            }
        } catch (\Throwable $e) {
            $this->getLogger()->error($e);

            return 0;
        }


        $written = shmop_write($sh, $header, 0);
        $written += shmop_write($sh, $storeValue, self::HEADER_SIZE);
        $this->logger->debug(sprintf("Written memory bytes for id %d: 0x%08x", $id, $written));
        shmop_close($sh);
        $this->logger->endProfile("write_{$id}", "Writing {$id} data");

        return $written;
    }

    public function exists(int $id)
    {
        $this->logger->beginProfile('shm_exists_' . $id);
        try {
            $sh = @shmop_open($this->getSharedItemKey($id), 'a', 0, 0);
        } catch (\Throwable $e) {
            $this->logger->endProfile('exists_' . $id);

            return false;
        }

        if (!$sh) {
            $this->logger->endProfile('exists_' . $id);

            return false;
        }

        $notEmpty = shmop_size($sh) > 0;
        shmop_close($sh);

        $this->logger->endProfile('exists_' . $id);

        return $notEmpty;
    }

    /**
     * @param int $id
     *
     * @return bool
     */
    public function delete(int $id)
    {
        try {
            $sh = @shmop_open($this->getSharedItemKey($id), 'w', 0, 0);
        } catch (\Throwable $e) {
            return false;
        }

        if (!$sh) {
            return false;
        }

        $deleted = shmop_delete($sh);
        shmop_close($sh);

        return $deleted;
    }

    /**
     * @param int  $id
     * @param int  $offset
     * @param bool $cleanup Be carefully! If not clean up memory automatic, you must delete this by yourself
     *
     * @return bool|int|mixed|string
     */
    public function read(int $id, int &$offset = 0, bool $cleanup = true)
    {
        $this->logger->beginProfile('read_' . $id);
        $key    = $this->getSharedItemKey($id);
        $sh     = shmop_open($key, 'a', 0, 0);
        $header = shmop_read($sh, 0, self::HEADER_SIZE);
        list($id, $off, $size, $flags) = $this->unpackHeader($header);
        $this->logger->debug(sprintf('Reading state: (key = 0x%08x, shmid = %d)', $key, $sh));
        $this->logger->debug(sprintf("State header: (id = %d, offset = %d, size = 0x%08x, flags = 0x%03x)", $id,
            $offset,
            $size, $flags));

        $result = shmop_read($sh, self::HEADER_SIZE, $size);
        $this->logger->debug(sprintf('State data: (size = 0x%08x)', mb_strlen($result, 'utf-8')));
        if ($cleanup) {
            shmop_delete($sh);
        }

        shmop_close($sh);

        $result = $this->getResult($result, $flags);
        $offset = $off;

        $this->logger->endProfile('read_' . $id, "Reading {$id} data");

        return $result;
    }

    /**
     * @return Logger|LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param string $value
     *
     * @return string
     */
    private function removeTerminationNull(string $value)
    {
        $i = strpos((string)$value, "\0");
        if ($i === false) {
            return $value;
        }

        $result = substr((string)$value, 0, $i);

        return $result;
    }

    /**
     * @param string $value
     * @param int    $flags
     *
     * @return bool|int|mixed|string|null
     */
    private function getResult(string $value, int $flags)
    {
        if (($flags & self::F_PACKED) !== self::F_PACKED) {
            $value = $this->removeTerminationNull($value);
        }

        $out = null;
        if (($flags & self::F_SERIALIZED) === self::F_SERIALIZED) {
            $out = Serializer::unserialize($value);
        }

        if (($flags & self::F_IS_BOOL) === self::F_IS_BOOL) {
            $out = (bool)$value;
        } else if (($flags & self::F_IS_INT) === self::F_IS_INT) {
            if (($flags & self::F_PACKED) === self::F_PACKED) {
                $res = unpack(self::P_INT . 'num', $value);
                $out = $res['num'];
                unset($res);
            } else {
                $out = (int)$value;
            }

        } else if (($flags & self::F_IS_FLOAT) === self::F_IS_FLOAT) {
            if (($flags & self::F_PACKED) === self::F_PACKED) {
                $res = unpack(self::P_FLOAT . 'num', $value);
                $out = $res['num'];
                unset($res);
            } else {
                $out = (double)$value;
            }
        } else if (($flags & self::F_IS_STRING) === self::F_IS_STRING) {
            $out = (string)$value;
        } else if (($flags & self::F_IS_NULL) === self::F_IS_NULL) {
            $out = null;
        } else if (($flags & self::F_IS_ARRAY) === self::F_IS_ARRAY) {
            if (($flags & self::F_SERIALIZED) === self::F_SERIALIZED) {
                $value = Serializer::unserialize($value);
            }

            if (($flags & self::F_IS_ARRAY_OBJECT) === self::F_IS_ARRAY_OBJECT) {
                $out = (object)$value;
            } else {
                $out = (array)$value;
            }
        }


        return $out;
    }

    /**
     * Blocking method, writes process result state to state file
     *
     * @param int $id
     * @param int $offset
     * @param int $size
     * @param int $flags
     *
     * @return string binary data
     * sizes: 2 (ushort) + 2 (ushort) + 8(ulong long) + 4(uint) bytes, order - machine-dependent
     * @see SharedMemoryManager::F_SERIALIZED and other flags
     */
    private function packHeader(int $id, int $offset, int $size, int $flags = 0): string
    {
        return pack('SSQI', $id, $offset, $size, $flags);
    }

    /**
     * @param string $bin
     *
     * @return array [$id, $offset, $size, $flags]
     */
    private function unpackHeader(string $bin): array
    {
        $data = unpack('Sid/Soffset/Qsize/Iflags', $bin);

        return [
            $data['id'],
            $data['offset'],
            $data['size'],
            $data['flags'],
        ];
    }

    /**
     * maximum size of processes with result see in /proc/sys/kernel/pid_max
     *
     * @param int $id
     *
     * @return int unsigned
     * @throws \RuntimeException
     */
    private function getSharedItemKey(int $id): int
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('ID cannot be less or equals zero');
        }

        $st = @stat(__FILE__);
        if (!$st) {
            return -1;
        }

        //@TODO collisions? need more unique key
        $key = (int)sprintf("%u",
            (
                ($st['ino'] & 0xffff) |
                (($st['dev'] & 0xff) << 16) |
                (($id & 0xff) << 24)
            )
        );

        return $key;
    }

    /**
     * @param $any
     *
     * @todo sizes is very rounded
     * @return int
     */
    private function sizeof($any): int
    {
        if (is_object($any)) {
            $start = memory_get_usage();
            $new   = Serializer::unserialize(Serializer::serialize($any));
            $end   = memory_get_usage() - $start;
        } else if (is_bool($any)) {
            return 2;
        } else if (is_int($any)) {
            $end = PHP_INT_SIZE;
        } else if (is_float($any) || is_double($any)) {
            $end = PHP_INT_SIZE * 3;
        } else {
            $end = mb_strlen($any, '8bit');
        }

        return (int)$end;
    }
}