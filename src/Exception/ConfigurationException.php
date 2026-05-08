<?php

declare(strict_types=1);

namespace ProdumanOrg\DoctrineEncryption\Exception;

final class ConfigurationException extends DoctrineEncryptionException
{
    public static function emptyKeyFile(): self
    {
        return new self('The "doctrine_encryption.key_file" option must not be empty.');
    }

    public static function unreadableKeyFile(string $keyFile): self
    {
        return new self(sprintf('Halite encryption key file "%s" is not readable.', $keyFile));
    }

    public static function invalidKeyFile(string $keyFile): self
    {
        return new self(sprintf('Halite encryption key file "%s" is not a valid encryption key.', $keyFile));
    }

    public static function readonlyEncryptedProperty(string $class, string $property): self
    {
        return new self(sprintf(
            'Encrypted readonly properties are not supported. Remove #[Encrypted] from "%s::$%s" or make the property mutable.',
            $class,
            $property,
        ));
    }

    public static function encryptedFieldMustBeStringOrNull(string $field): self
    {
        return new self(sprintf('Encrypted field "%s" must be a string or null.', $field));
    }
}
