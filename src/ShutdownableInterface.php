<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 13.09.18
 * Time: 22:00
 */

namespace TS\PhpService;


interface ShutdownableInterface
{

    function shutdown(int $signal = 0): void;

}
