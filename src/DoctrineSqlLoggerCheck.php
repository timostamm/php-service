<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 13.09.18
 * Time: 17:56
 */

namespace TS\PhpService;


use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;


class DoctrineSqlLoggerCheck
{


    /** @var ManagerRegistry[] */
    private $registries;

    /** @var EntityManagerInterface[] */
    private $ems;


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


    public function hasOffenses(): bool
    {
        foreach ($this->findOffendingEms() as $offendingEm) {
            return true;
        }
        return false;
    }


    public function removeSqlLoggers(): int
    {
        $removed = 0;
        foreach ($this->findOffendingEms() as $name => $em) {
            /** @var string $name */
            /** @var EntityManagerInterface $em */
            $config = $em->getConnection()->getConfiguration();
            $config->setSQLLogger(null);
            $removed++;
        }
        return $removed;
    }


    /**
     * @throws \Exception if there is an entity manager with an SQL logger.
     */
    public function assertNoOffenses(): void
    {
        if (!$this->hasOffenses()) {
            return;
        }
        throw new \Exception($this->getOffenseMessage());
    }


    public function getOffenseMessage(): ?string
    {
        if (!$this->hasOffenses()) {
            return null;
        }
        $names = $this->getOffendingManagerNames();
        if (count($names) > 1) {
            $msg = sprintf('The connection configurations of the entity managers "%s" have an SQL logger, which will leak memory.', join('", "', $names));
        } else {
            $msg = sprintf('The connection configuration of the entity manager "%s" has an SQL logger, which will leak memory.', join(', ', $names));
        }
        return $msg;
    }


    public function getOffendingManagerNames(): array
    {
        $names = [];
        foreach ($this->findOffendingEms() as $name => $em) {
            /** @var string $name */
            /** @var EntityManagerInterface $em */
            $names[] = $name;
        }
        return $names;
    }


    private function findOffendingEms(): iterable
    {
        foreach ($this->findEms() as $name => $em) {
            /** @var string $name */
            /** @var EntityManagerInterface $em */
            $config = $em->getConnection()->getConfiguration();
            if ($config->getSQLLogger()) {
                yield $name => $em;
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
