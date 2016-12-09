<?php
namespace edwardstock\forker\tests\unit\system;

use edwardstock\forker\log\Logger;
use edwardstock\forker\system\SharedMemoryManager;
use edwardstock\forker\tests\TestCase;

/**
 * forker. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 */
class SharedMemoryManagerTest extends TestCase
{

    public function testWrite()
    {
        $o       = new \stdClass();
        $o->func = function () {
        };

        $values = [
            'integer'             => 1,
            'float'               => 111.111,
            'object'              => new \stdClass(),
            'array'               => ['k' => 'v'],
            'array_with_closure'  => [
                'k' => function () {
                },
            ],
            'object_with_closure' => $o,
            'bool_true'           => true,
            'bool_false'          => false,
            'null'                => null,
        ];

        foreach ($values AS $testName => $value) {
            $pid    = mt_rand(1, 32700);
            $offset = 0;

            $sh = $this->create();
            $this->getLogger()->debug("Writing {$pid} - {$testName} " . $sh->write($pid, $offset,
                    $value) . " bytes - ok");

            $sh->delete($pid);
        }
    }

    public function testRead()
    {
        $o       = new \stdClass();
        $o->func = function () {
        };

        $values = [
            'integer'             => 1,
            'float'               => 111.111,
            'object'              => new \stdClass(),
            'array'               => ['k' => 'v'],
            'array_with_closure'  => [
                'k' => function () {
                },
            ],
            'object_with_closure' => $o,
            'bool_true'           => true,
            'bool_false'          => false,
            'null'                => null,
        ];

        foreach ($values AS $testName => $value) {
            $pid    = mt_rand(1, 32700);
            $offset = 0;

            $this->getLogger()->debug("Writing {$pid} - {$testName} ");
            $sh      = $this->create();
            $written = $sh->write($pid, $offset, $value);
            $this->getLogger()->debug("{$written} bytes - ok");


            $this->getLogger()->debug("Reading {$pid} - {$testName}");
            $sh        = $this->create();
            $resOffset = 0;
            $result    = $sh->read($pid, $resOffset, true);
            $this->assertEquals($value, $result);
            $this->assertEquals($offset, $resOffset);
        }
    }

    public function testFailedPids()
    {
        $o       = new \stdClass();
        $o->func = function () {
        };

        $pids = [
            28440,
            23613,
            21063,
            6896,
            19688,
            25819,
            11593,
            21121,
            10458,
            27433,
            1869,
        ];

        $values = [
            'integer'             => 1,
            'float'               => 111.111,
            'object'              => new \stdClass(),
            'array'               => ['k' => 'v'],
            'array_with_closure'  => [
                'k' => function () {
                },
            ],
            'object_with_closure' => $o,
            'bool_true'           => true,
            'bool_false'          => false,
            'null'                => null,
        ];

        echo PHP_EOL;
        echo PHP_EOL;

        foreach ($values AS $testName => $value) {

            foreach ($pids AS $pid) {
                $offset = 0;

                $this->getLogger()->debug("Writing {$pid}  - {$testName} ");
                $sh      = $this->create();
                $written = $sh->write($pid, $offset, $value);
                $this->getLogger()->debug("{$written} bytes - ok");


                $this->getLogger()->debug("Reading {$pid} - {$testName}");
                $sh        = $this->create();
                $resOffset = 0;
                $result    = $sh->read($pid, $resOffset, true);
                $this->assertEquals($value, $result);
                $this->assertEquals($offset, $resOffset);

            }
        }
    }

    private function create()
    {
        $shm = new SharedMemoryManager();

        return $shm;
    }

    private function getLogger()
    {
        static $logger;
        if ($logger === null) {
            $logger = new Logger('shared_memory_test');
        }

        return $logger;
    }


}