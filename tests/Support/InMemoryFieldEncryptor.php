<?php

declare(strict_types=1);

namespace DoctrineEncryption\Tests\Support;

use DoctrineEncryption\Contract\FieldEncryptorInterface;

final class InMemoryFieldEncryptor implements FieldEncryptorInterface
{
    public function encrypt(?string $value): ?string
    {
        return $value === null ? null : 'enc:' . $value;
    }

    public function decrypt(?string $value): ?string
    {
        return $value === null ? null : substr($value, 4);
    }
}
