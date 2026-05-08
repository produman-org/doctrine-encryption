<?php

declare(strict_types=1);

namespace DoctrineEncryption\Tests\Support;

use BadMethodCallException;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;

final class RecordingObjectManager implements ObjectManager
{
    public int $getUnitOfWorkCalls = 0;

    public readonly RecordingUnitOfWork $unitOfWork;

    public function __construct()
    {
        $this->unitOfWork = new RecordingUnitOfWork();
    }

    public function getUnitOfWork(): RecordingUnitOfWork
    {
        ++$this->getUnitOfWorkCalls;

        return $this->unitOfWork;
    }

    public function find(string $className, mixed $id): object|null
    {
        throw new BadMethodCallException('Not implemented.');
    }

    public function persist(object $object): void
    {
        throw new BadMethodCallException('Not implemented.');
    }

    public function remove(object $object): void
    {
        throw new BadMethodCallException('Not implemented.');
    }

    public function clear(): void
    {
        throw new BadMethodCallException('Not implemented.');
    }

    public function detach(object $object): void
    {
        throw new BadMethodCallException('Not implemented.');
    }

    public function refresh(object $object): void
    {
        throw new BadMethodCallException('Not implemented.');
    }

    public function flush(): void
    {
        throw new BadMethodCallException('Not implemented.');
    }

    public function getRepository(string $className): ObjectRepository
    {
        throw new BadMethodCallException('Not implemented.');
    }

    public function getClassMetadata(string $className): ClassMetadata
    {
        throw new BadMethodCallException('Not implemented.');
    }

    public function getMetadataFactory(): ClassMetadataFactory
    {
        throw new BadMethodCallException('Not implemented.');
    }

    public function initializeObject(object $obj): void
    {
        throw new BadMethodCallException('Not implemented.');
    }

    public function isUninitializedObject(mixed $value): bool
    {
        throw new BadMethodCallException('Not implemented.');
    }

    public function contains(object $object): bool
    {
        throw new BadMethodCallException('Not implemented.');
    }
}
