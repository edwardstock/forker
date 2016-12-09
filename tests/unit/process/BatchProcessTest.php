<?php
namespace edwardstock\forker\tests\unit\process;

use edwardstock\forker\handler\BatchTask;
use edwardstock\forker\handler\CallbackTask;
use edwardstock\forker\ProcessManager;
use edwardstock\forker\tests\TestCase;

/**
 * forker. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 */
class BatchProcessTest extends TestCase
{

    public function testDownloadBatch()
    {
        $sites = [
            'http://example.com',
            'http://example.com',
        ];

        $results       = [];
        $callTimes     = 0;
        $expectedCalls = 1;
        $sleepTime     = 4;

        /** @var BatchTask $downloads */
        $downloads = BatchTask::create($sites, function ($key, $value, CallbackTask $task) use ($sleepTime) {
            sleep($sleepTime);

            return @file_get_contents($value);
        })->future(function ($sitesContent, BatchTask $task) use (&$results, &$callTimes) {
            $results = $sitesContent;
            $callTimes++;
        })->preserveKeys();

        $this->assertEquals(sizeof($sites), sizeof($downloads->getData()));
        $downloads->addData($sites);
        $this->assertEquals(sizeof($sites) + sizeof($sites), sizeof($downloads->getData()));

        $downloads->setData($sites);
        $this->assertEquals(sizeof($sites), sizeof($downloads->getData()));

        $this->assertTrue(is_callable($downloads->getHandler()));
        $this->assertTrue(is_callable($downloads->getFutureHandler()));
        $this->assertEquals(null, $downloads->getErrorHandler());

        $downloads->error(function () {
        });
        $this->assertTrue(is_callable($downloads->getErrorHandler()));


        $pm = new ProcessManager();

        $pm->add($downloads);

        $timeStart = microtime(true);
        $pm->run(true)->wait();

        $timeResult = microtime(true) - $timeStart;
        $timeResult *= 1000; //milliseconds

        $this->assertEquals(2, sizeof($results));
        $this->assertEquals(sizeof($downloads->getResult()), sizeof($results));
        $this->assertEquals(0, $downloads->getExitCode());

        $this->assertEquals($expectedCalls, $callTimes);
        $this->assertTrue($timeResult >= ($sleepTime * 1000));
    }
}