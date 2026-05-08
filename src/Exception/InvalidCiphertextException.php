<?php

declare(strict_types=1);

namespace ProdumanOrg\DoctrineEncryption\Exception;

final class InvalidCiphertextException extends DoctrineEncryptionException
{
    public static function plaintextNotAllowed(): self
    {
        return new self('Plaintext value found in an encrypted field, but plaintext migration mode is disabled. Enable "allow_plaintext" only while migrating legacy data.');
    }
}
