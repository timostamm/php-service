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

    /** @var ManagerRegistry[] */
    private $registries;

    /** @var EntityManagerInterface[] */
    private $ems;

    /** @var TimerInterface */
    private $timer;


    public function __construct(ManagerRegistry $doctrineRegistry = null)
    {
        $this->registries = [];
        $this->ems = [];
        if ($doctrineRegistry) {
            $this->addRegistry($doctrineRegistry);
        }
    }


    public function addRegistry(ManagerRegistry $doctrineRegistry): void
    {
        $this->registries[] = $doctrineRegistry;
    }


    public function addEntityManager(EntityManagerInterface $entityManager): void
    {
        $this->ems[] = $entityManager;
    }


    public function attach(LoopInterface $loop, int $interval = 3600): void
    {
        $this->timer = $loop->addPeriodicTimer($interval, function () {
            $this->reconnect();
        });
    }


    public function detach(LoopInterface $loop): void
    {
        $loop->cancelTimer($this->timer);
    }


    public function reconnect(): void
    {
        foreach ($this->findConnections() as $conn) {
            $conn->close();
            $conn->connect();
        }
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
