<?php
namespace edwardstock\forker\tests\unit\system;

use edwardstock\forker\system\PIDManager;
use edwardstock\forker\tests\TestCase;

/**
 * forker. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 */
class PIDHandlerTest extends TestCase
{

    public function tearDown()
    {
        $this->createInstance()->clear();
        parent::tearDown();
    }

    public function testInstance()
    {
        $handler = $this->createInstance();
        $this->assertInstanceOf(PIDManager::class, $handler);
    }

    public function testMultiInstance()
    {
        $h1 = PIDManager::getInstance($this->getTestPIDFile());
        $this->assertInstanceOf(PIDManager::class, $h1);


        $h2 = PIDManager::getInstance($this->getTestPIDFile('another.pid'));
        $this->assertInstanceOf(PIDManager::class, $h2);

        $this->assertNotEquals($h2, $h1);
    }

    public function testAdd()
    {
        $handler = $this->createInstance();
        $pid     = mt_rand(1111, 9999);

        $this->assertTrue($handler->add($pid));
        $this->assertTrue($handler->exists($pid));
    }

    public function testRemove()
    {
        $handler = $this->createInstance();
        $pid     = mt_rand(1111, 9999);

        $this->assertTrue($handler->add($pid));
        $this->assertTrue($handler->exists($pid));

        $this->assertTrue($handler->remove($pid));
        $this->assertFalse($handler->exists($pid));
    }

    public function testAddAll()
    {
        $handler = $this->createInstance();
        $pids    = [];
        for ($i = 0; $i < 10; $i++) {
            $pids[] = mt_rand(1000, 19999);
        }

        $this->assertEquals(sizeof($pids), $handler->addAll($pids));
    }

    public function testRemoveAll()
    {
        $handler = $this->createInstance();
        $pids    = [];
        foreach (range(1000, 19999, mt_rand(10, 50)) AS $pid) {
            $pids[] = $pid;
        }

        $this->assertEquals(sizeof($pids), $handler->addAll($pids));
        $this->assertEquals(sizeof($pids), $handler->removeAll($pids));
        $this->assertTrue($handler->isEmpty());
    }

    public function testGetAll()
    {
        $handler = $this->createInstance();
        $pids    = [];
        for ($i = 0; $i < 10; $i++) {
            $pids[] = mt_rand(1000, 19999);
        }
        $handler->addAll($pids);

        $this->assertEquals(sizeof($pids), sizeof($handler->getAll()));
    }

    public function testGetChildren()
    {
        $handler = $this->createInstance();
        $pids    = [1];
        $handler->add($pids[0], true, true); // adding parent pid
        for ($i = 2; $i < 10; $i++) {
            $pids[] = $i;
        }
        $handler->addAll($pids);

        $expected = sizeof($pids);

        $this->assertEquals($expected, sizeof($handler->getAll(true)));
        $this->assertEquals($expected - 1, sizeof($handler->getChildren(0, true)));
    }

    public function testGetParent()
    {
        $handler = $this->createInstance();
        $parent  = 1;
        $pids    = [$parent];
        $handler->add($pids[0], true, true); // adding parent pid
        for ($i = 2; $i < 10; $i++) {
            $pids[] = $i;
        }
        $handler->addAll($pids);

        $this->assertEquals(sizeof($pids), sizeof($handler->getAll()));
        $this->assertEquals($parent, $handler->getParentPid());
    }

    private function createInstance()
    {
        return PIDManager::getInstance($this->getTestPIDFile());
    }
}