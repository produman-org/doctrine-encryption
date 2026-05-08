<?php

declare(strict_types=1);

namespace ProdumanOrg\DoctrineEncryption\Metadata;

use ReflectionProperty;

/**
 * @internal
 */
final readonly class EncryptedFieldMetadata
{
    public function __construct(
        public string $name,
        private ReflectionProperty $property,
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
