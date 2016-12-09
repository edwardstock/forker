<?php
namespace edwardstock\forker\tests\unit\errors;

use edwardstock\forker\exceptions\FsPermissionException;
use edwardstock\forker\tests\TestCase;

/**
 * forker. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 */
class FsPermissionExceptionTest extends TestCase
{

    public function testMessageException()
    {
        $path    = '/';
        $perm    = 0777;
        $curUser = get_current_user();

        $exception = new FsPermissionException($path, $perm);

        $expectedMessage = sprintf('Cannot process - permission denied for %s to directory %s with rights: 0%o',
            $curUser, $path, $perm);

        $this->assertEquals($expectedMessage, $exception->getMessage());

    }
}