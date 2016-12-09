<?php
namespace edwardstock\forker\tests\unit\process;

use edwardstock\forker\handler\CallbackTask;
use edwardstock\forker\ProcessManager;
use edwardstock\forker\tests\TestCase;

/**
 * forker. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 */
class ProcessTest extends TestCase
{

    public function setUp()
    {
        parent::setUp();
    }

    public function testNotJoinedProcess()
    {
        $returnValue   = 100;
        $expectedValue = 0;

        $handleFunction = function () use ($returnValue) {
            return $returnValue;
        };

        $futureFunction = function ($result) use (&$expectedValue) {
            $expectedValue = $result;
        };

        $job     = CallbackTask::create($handleFunction, $futureFunction);
        $process = new ProcessManager($this->getTestPIDFile());

        $process->add($job);

        $process->run()->wait();

        $this->assertNotEquals($returnValue, $expectedValue);
        $this->assertEquals(0, $job->getExitCode());
    }

    public function testJoinedProcess()
    {
        $returnValue   = 100;
        $expectedValue = 0;

        $handleFunction = function () use ($returnValue) {
            return $returnValue;
        };

        $futureFunction = function ($result) use (&$expectedValue) {
            $expectedValue = $result;
        };

        $job     = CallbackTask::create($handleFunction, $futureFunction);
        $process = new ProcessManager($this->getTestPIDFile());

        $process->add($job);

        $process->run(true)->wait();

        $this->assertEquals($returnValue, $expectedValue);
        $this->assertEquals($returnValue, $job->getResult());
        $this->assertEquals(0, $job->getExitCode());
    }

    public function testManyJobs()
    {
        $returnValue   = 100;
        $expectedValue = 0;
        $pids          = [];

        $handleFunction = function () use ($returnValue) {
            sleep(4);

            return $returnValue;
        };

        $futureFunction = function ($result, CallbackTask $task) use (&$expectedValue, &$pids) {
            $expectedValue = $result;
            $pids[]        = $task->getPid();
        };

        $job     = CallbackTask::create($handleFunction, $futureFunction);
        $process = new ProcessManager($this->getTestPIDFile());

        for ($i = 0; $i < 10; $i++) {
            $process->add($job);
        }


        $process->run(true)->wait();

        $this->assertEquals($returnValue, $expectedValue);
        $this->assertEquals($returnValue, $job->getResult());
        $this->assertEquals(0, $job->getExitCode());
        $this->assertEquals(10, sizeof($pids));
    }

    public function testStopping()
    {
        $returnValue   = 100;
        $expectedValue = 0;

        $handleFunction = function () use ($returnValue) {
            sleep(4);

            return $returnValue;
        };

        $futureFunction = function ($result) use (&$expectedValue) {
            $expectedValue = $result;
        };

        $job     = CallbackTask::create($handleFunction, $futureFunction);
        $process = new ProcessManager($this->getTestPIDFile());

        $process->add($job);

        $process->run(true);

        $process->stop();

        $this->assertNotEquals($returnValue, $expectedValue);
        $this->assertNotEquals($returnValue, $job->getResult());
        $this->assertEquals(0, $job->getExitCode());
    }


}