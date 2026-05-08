<?php

declare(strict_types=1);

namespace DoctrineEncryption\Metadata;

final class EncryptedFieldMetadata
{
    public function __construct(
        public readonly string $name,
        private readonly \ReflectionProperty $property,
    ) {
        $this->property->setAccessible(true);
    }

    public function getValue(object $object): mixed
    {
        return $this->property->getValue($object);
    }

    public function setValue(object $object, mixed $value): void
    {
        $this->property->setValue($object, $value);
    }
}
