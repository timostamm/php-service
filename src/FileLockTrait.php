<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 13.09.18
 * Time: 19:06
 */

namespace TS\PhpService;


use Symfony\Component\Filesystem\Exception\IOException;

trait FileLockTrait
{


    /** @var resource */
    private $lockResource;


    protected function acquireLock(): void
    {
        $ref = new \ReflectionClass($this);
        $file = $ref->getFileName();

        $fp = fopen($file, 'r');
        if (flock($fp, LOCK_EX | LOCK_NB)) {
            $this->lockResource = $fp;
        } else {
            throw new IOException(sprintf('Unable to acquire lock for "%s". This command is already runnning', $ref->getName()), 0, null, $file);
        }
    }


    protected function releaseLock(): void
    {
        if ($this->lockResource) {
            flock($this->lockResource, LOCK_UN);
            fclose($this->lockResource);
        }
    }

}
