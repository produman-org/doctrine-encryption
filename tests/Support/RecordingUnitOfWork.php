<?php

declare(strict_types=1);

namespace DoctrineEncryption\Tests\Support;

final class RecordingUnitOfWork
{
    /**
     * @var list<array{objectId: int, property: string, value: mixed}>
     */
    public array $originalEntityProperties = [];

    public function setOriginalEntityProperty(int $oid, string $property, mixed $value): void
    {
        $this->originalEntityProperties[] = [
            'objectId' => $oid,
            'property' => $property,
            'value' => $value,
        ];
    }
}
