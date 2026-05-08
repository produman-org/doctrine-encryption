<?php

declare(strict_types=1);

namespace DoctrineEncryption\Metadata;

use DoctrineEncryption\Attribute\Encrypted;
use Doctrine\Persistence\Proxy;

final class EncryptedFieldMetadataFactory
{
    /**
     * @var array<class-string, list<EncryptedFieldMetadata>>
     */
    private array $cache = [];

    /**
     * @var array<class-string, array<string, EncryptedFieldMetadata>>
     */
    private array $cacheByFieldName = [];

    /**
     * @return list<EncryptedFieldMetadata>
     */
    public function forObject(object $object): array
    {
        return $this->forClass($this->classForObject($object));
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
     * @param list<string> $fieldNames
     *
     * @return list<EncryptedFieldMetadata>
     */
    public function forObjectFieldNames(object $object, array $fieldNames): array
    {
        return $this->forClassFieldNames($this->classForObject($object), $fieldNames);
    }

    /**
     * @param class-string $class
     * @param list<string> $fieldNames
     *
     * @return list<EncryptedFieldMetadata>
     */
    public function forClassFieldNames(string $class, array $fieldNames): array
    {
        if ($fieldNames === []) {
            return [];
        }

        $fieldsByName = $this->fieldsByNameForClass($class);
        $fields = [];

        foreach ($fieldNames as $fieldName) {
            if (!isset($fieldsByName[$fieldName])) {
                continue;
            }

            $fields[] = $fieldsByName[$fieldName];
        }

        return $fields;
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

    /**
     * @return class-string
     */
    private function classForObject(object $object): string
    {
        if ($object instanceof Proxy) {
            $parentClass = get_parent_class($object);

            if ($parentClass !== false) {
                return $parentClass;
            }
        }

        return $object::class;
    }

    /**
     * @param class-string $class
     *
     * @return array<string, EncryptedFieldMetadata>
     */
    private function fieldsByNameForClass(string $class): array
    {
        if (isset($this->cacheByFieldName[$class])) {
            return $this->cacheByFieldName[$class];
        }

        $fieldsByName = [];

        foreach ($this->forClass($class) as $field) {
            $fieldsByName[$field->name] = $field;
        }

        return $this->cacheByFieldName[$class] = $fieldsByName;
    }
}
