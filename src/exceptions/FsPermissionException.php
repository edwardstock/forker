<?php
namespace edwardstock\forker\exceptions;

/**
 * forker. 2016
 * @author Eduard Maximovich <edward.vstock@gmail.com>
 *
 */
class FsPermissionException extends \Exception
{
    /**
     * FsPermissionException constructor.
     *
     * @param string         $path
     * @param int            $perm
     * @param Exception|null $previous
     */
    public function __construct(string $path, int $perm = 0777, Exception $previous = null)
    {
        $curUser = get_current_user();
        $message = sprintf('Cannot process - permission denied for %s to directory %s with rights: 0%o', $curUser,
            $path, $perm);
        parent::__construct($message, 0, $previous);
    }
}