<?php
namespace edwardstock\forker\tests;

use PHPUnit_Framework_TestCase;

/**
 * forker. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 */
class TestCase extends PHPUnit_Framework_TestCase
{

    protected function getTestPIDFile($filename = null)
    {
        return FORKER_RPATH . '/' . ($filename??'test.pid');
    }
}