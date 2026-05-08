<?php

declare(strict_types=1);

namespace ProdumanOrg\DoctrineEncryption\Exception;

use Throwable;

final class DecryptionFailedException extends DoctrineEncryptionException
{
    public static function forCiphertext(Throwable $previous): self
    {
        return new self('Unable to decrypt encrypted field value. The ciphertext is corrupted or the configured key is wrong.', 0, $previous);
    }
}
