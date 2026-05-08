<?php

declare(strict_types=1);

namespace DoctrineEncryption\Metadata;

use DoctrineEncryption\Attribute\Encrypted;

final class EncryptedFieldMetadataFactory
{
    /**
     * @var array<class-string, list<EncryptedFieldMetadata>>
     */
    private array $cache = [];

    /**
     * @return list<EncryptedFieldMetadata>
     */
    public function forObject(object $object): array
    {
        return $this->forClass($object::class);
    }

    /**
     * @param class-string $class
     *
     * @return list<EncryptedFieldMetadata>
     */
    public function forClass(string $class): array
    {
        return $this->cache[$class] ??= $this->buildForClass($class);
    }

    /**
     * @param class-string $class
     *
     * @return list<EncryptedFieldMetadata>
     */
    private function buildForClass(string $class): array
    {
        $fields = [];
        $seen = [];
        $reflection = new \ReflectionClass($class);

        do {
            foreach ($reflection->getProperties() as $property) {
                if (isset($seen[$property->getName()])) {
                    continue;
                }

                $seen[$property->getName()] = true;

                if ($property->getAttributes(Encrypted::class) === []) {
                    continue;
                }

                $fields[] = new EncryptedFieldMetadata($property->getName(), $property);
            }

            $reflection = $reflection->getParentClass();
        } while ($reflection !== false);

        return $fields;
    }
}
