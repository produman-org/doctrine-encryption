<?php

declare(strict_types=1);

namespace DoctrineEncryption\Metadata;

final class EncryptedFieldMetadata
{
    public function __construct(
        public readonly string $name,
        private readonly \ReflectionProperty $property,
    ) {
    }

    public function getValue(object $object): mixed
    {
        return $this->property->getValue($object);
    }

    public function isInitialized(object $object): bool
    {
        return $this->property->isInitialized($object);
    }

    public function setValue(object $object, mixed $value): void
    {
        $this->property->setValue($object, $value);
    }
}
