<?php
namespace edwardstock\forker\tests\unit\event;

use edwardstock\forker\event\SignalDispatcher;
use edwardstock\forker\log\Logger;
use edwardstock\forker\tests\TestCase;
use Psr\Log\LoggerInterface;

/**
 * forker. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 */
class SignalDispatcherTest extends TestCase
{

    use SignalDispatcher;

    public function testAddSignalHandler()
    {
        $signal  = SIGUSR1;
        $handler = function () {
        };

        $this->attachSignalHandler($signal, $handler);

        $this->dispatchSignals();

        $this->assertEquals(1, sizeof($this->getSignalHandlers()));
        $this->assertEquals(0, sizeof($this->getSignalCommonHandlers()));
    }

    public function testAddMultipleSignalHandlers()
    {
        $signals = [SIGUSR1, SIGUSR2];
        $handler = function () {
        };

        $this->attachSignalsHandler($signals, $handler);

        $this->dispatchSignals();

        $this->assertEquals(2, sizeof($this->getSignalHandlers()));
        $this->assertEquals(0, sizeof($this->getSignalCommonHandlers()));
    }

    public function testDetachSignalHandlers()
    {
        $signals = [SIGUSR1, SIGUSR2];
        $handler = function () {
        };

        $this->attachSignalsHandler($signals, $handler);

        $this->assertEquals(2, sizeof($this->getSignalHandlers()));

        $this->detachSignalHandlers(SIGUSR1);
        $this->assertEquals(1, sizeof($this->getSignalHandlers()));

        $this->detachSignalHandlers(SIGABRT); // nothing changed, cause we didn't added SIGABRT handler
        $this->assertEquals(1, sizeof($this->getSignalHandlers()));

        $this->detachSignalHandlers(SIGBUS); // nothing changed, cause we didn't added SIGBUS handler
        $this->assertEquals(1, sizeof($this->getSignalHandlers()));

        $this->detachSignalHandlers(SIGUSR2);
        $this->assertEquals(0, sizeof($this->getSignalHandlers()));

        $this->dispatchSignals();
    }

    public function testAttachCommonSignalHandler()
    {
        $handler = function () {
        };

        $this->attachSignalCommonHandler($handler);
        $this->assertEquals(1, sizeof($this->getSignalCommonHandlers()));

        $this->attachSignalCommonHandler($handler);
        $this->assertEquals(2, sizeof($this->getSignalCommonHandlers()));

        $this->dispatchSignals();
    }

    public function testReceivingSignal()
    {
        $this->dispatchSignals();

        $this->assertFalse($this->isTerminated());

        $signaled = false;
        $this->attachSignalHandler(SIGUSR1, function () use (&$signaled) {
            $signaled = true;
        });
        $this->assertFalse($this->isTerminated());
        $this->assertEquals($signaled, $this->isTerminated());

        posix_kill(posix_getpid(), SIGUSR1);

        $this->assertFalse($this->isTerminated());
        $this->assertTrue($signaled);
    }

    /**
     * @return Logger|LoggerInterface
     */
    public function getLogger()
    {
        static $logger;
        if ($logger === null) {
            $logger = new Logger('event:signal:dispatcher');
        }

        return $logger;
    }
}