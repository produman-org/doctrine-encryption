<?php

declare(strict_types=1);

namespace DoctrineEncryption\Contract;

interface CiphertextDetectorInterface
{
    public function isCiphertext(?string $value): bool;
}
