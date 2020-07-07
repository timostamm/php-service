<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 05.09.18
 * Time: 19:01
 */

namespace TS\PhpService;


use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;


class DoctrineSqlKeepAlive
{

    /** @var int */
    private $startTimestamp;

    /** @var int */
    private $interval = 3600;

    /** @var ManagerRegistry[] */
    private $registries;

    /** @var EntityManagerInterface[] */
    private $ems;

    /**
     * @deprecated
     * @var TimerInterface
     */
    private $timer;


    public function __construct(ManagerRegistry $doctrineRegistry = null)
    {
        $this->registries = [];
        $this->ems = [];
        if ($doctrineRegistry) {
            $this->addRegistry($doctrineRegistry);
        }
        $this->startTimestamp = time();
    }


    public function getInterval(): int
    {
        return $this->interval;
    }

    public function setInterval(int $interval): void
    {
        $this->interval = $interval;
    }


    public function addRegistry(ManagerRegistry $doctrineRegistry): void
    {
        $this->registries[] = $doctrineRegistry;
    }


    public function addEntityManager(EntityManagerInterface $entityManager): void
    {
        $this->ems[] = $entityManager;
    }


    public function reconnectIfRequired(): void {
        $now = time();
        if ($this->startTimestamp + $this->interval > $now) {
            $this->reconnect();
            $this->startTimestamp = $now;
        }
    }


    public function reconnect(): void
    {
        foreach ($this->findConnections() as $conn) {
            $conn->close();
            $conn->connect();
        }
    }



    /**
     * @deprecated
     */
    public function attach(LoopInterface $loop, int $interval = 3600): void
    {
        $this->timer = $loop->addPeriodicTimer($interval, function () {
            $this->reconnect();
        });
    }

    /**
     * @deprecated
     */
    public function detach(LoopInterface $loop): void
    {
        $loop->cancelTimer($this->timer);
    }


    /**
     * @return Connection[]
     */
    private function findConnections(): array
    {
        /** @var Connection[] $connections */
        $connections = [];
        foreach ($this->findEms() as $em) {
            /** @var EntityManagerInterface $em */
            $conn = $em->getConnection();
            if (!in_array($conn, $connections, true)) {
                $connections[] = $conn;
            }
        }
        return $connections;
    }


    private function findEms(): iterable
    {
        foreach ($this->registries as $registry) {
            foreach ($registry->getManagerNames() as $key => $value) {
                $name = is_string($key) ? $key : $value;
                $em = $registry->getManager($name);
                if ($em instanceof EntityManagerInterface) {
                    yield $name => $em;
                }
            }
        }
        foreach ($this->ems as $em) {
            yield 'unknown' => $em;
        }
    }


}
