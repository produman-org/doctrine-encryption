<?php

declare(strict_types=1);

namespace DoctrineEncryption\Contract;

interface FieldEncryptorInterface
{
    public function encrypt(?string $value): ?string;

    public function decrypt(?string $value): ?string;
}
