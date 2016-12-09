<?php
namespace edwardstock\forker\tests\unit\helpers;

use edwardstock\forker\helpers\ProcessHelper;
use edwardstock\forker\log\Logger;
use edwardstock\forker\tests\TestCase;
use Psr\Log\LoggerInterface;

/**
 * forker. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 */
class ProcessHelperTest extends TestCase
{

    use ProcessHelper;

    public function testSetNullTitle()
    {
        $exceptions = 1;
        try {
            $this->setProcessTitle(null);
            $exceptions--;
        } catch (\Throwable $e) {

        }

        $this->assertEquals(0, $exceptions);
    }

    public function testSetEmptyTitle()
    {
        $exceptions = 0;
        try {
            $this->setProcessTitle('');
        } catch (\Throwable $e) {
            $exceptions++;
        }

        $this->assertEquals(1, $exceptions);
    }

    public function testSetNormalTitle()
    {
        $exceptions = 1;
        try {
            $this->setProcessTitle('Super process title');
            $exceptions--;
        } catch (\Throwable $e) {
            $this->getLogger()->err($e);
        }

        $this->assertEquals(0, $exceptions);
    }

    public function testSetDefaultProcessPriority()
    {

        $exceptions = 1;
        try {
            $this->setPriority(0);
            $exceptions--;
        } catch (\Throwable $e) {
            $this->getLogger()->err($e);
        }

        $this->assertEquals(0, $exceptions);
    }


    public function testSetOverflowedProcessPriority()
    {

        $exceptions = 0;
        try {
            $this->setPriority(100);
        } catch (\Throwable $e) {
            $exceptions++;
        }

        $this->assertEquals(1, $exceptions);
    }

    public function testSetUnderflowProcessPriority()
    {

        $exceptions = 0;
        try {
            $this->setPriority(-100);
        } catch (\Throwable $e) {
            $exceptions++;
        }

        $this->assertEquals(1, $exceptions);
    }

    /**
     * @return Logger|LoggerInterface
     */
    public function getLogger()
    {
        return new Logger();
    }
}