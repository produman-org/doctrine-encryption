<?php

declare(strict_types=1);

namespace ProdumanOrg\DoctrineEncryption\Contract;

interface CiphertextDetectorInterface
{
    public function isCiphertext(?string $value): bool;
}
