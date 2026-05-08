<?php

declare(strict_types=1);

namespace ProdumanOrg\DoctrineEncryption\Exception;

final class KeyNotFoundException extends DoctrineEncryptionException
{
    public static function forPath(string $keyFile): self
    {
        return new self(sprintf(
            'Halite encryption key file "%s" was not found. Create it with "bin/console doctrine-encryption:generate-key" or enable "auto_generate_key" explicitly for non-production environments.',
            $keyFile,
        ));
    }
}
