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

    public function testJobArguments()
    {
        $customArg   = 'test';
        $expectedArg = null; // $customArg should be after future() function

        $job = CallbackTask::create(function (CallbackTask $task, $arg1) {
            return $arg1;
        })
            ->addArgument($customArg)
            ->future(function ($result) use (&$expectedArg) {
                $expectedArg = $result;
            });

        $pm = new ProcessManager();
        $pm->add($job)->run(true)->wait();

        $this->assertEquals($expectedArg, $customArg);
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


    public function testPooling()
    {
        $totalJobs      = 8;
        $poolSize       = 2;
        $totallyHandled = 0;
        $data           = [];
        for ($i = 0; $i < $totalJobs; $i++) {
            $data[$i] = mt_rand(1000, 9999);
        }

        $pm = new ProcessManager();
        $pm->pool($poolSize, true);

        $i = 0;
        while (sizeof($data) > 0) {
            $response = $this->poolFakeRemoteFetch($data);
            $job      = CallbackTask::create(function () use ($i, $response) {
                $handlingData = $response;
                fwrite(STDOUT, "Handling {$i} job: {$handlingData}\n");
            }, function () use (&$totallyHandled) {
                $totallyHandled++;
            });

            $pm->add($job);
            $i++;
        }

        $this->assertEquals($totalJobs, $totallyHandled);

    }

    private function poolFakeRemoteFetch(array &$data)
    {
        sleep(2);

        return array_pop($data);
    }


}