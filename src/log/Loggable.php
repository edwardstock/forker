<?php
/**
 * forker. 2016
 * Date: 08.12.16
 * Time: 20:16
 */

namespace edwardstock\forker\log;


use Psr\Log\LoggerInterface;

trait Loggable
{
    /**
     * @return Logger|LoggerInterface
     */
    abstract public function getLogger();
}