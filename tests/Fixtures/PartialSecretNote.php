<?php

declare(strict_types=1);

namespace ProdumanOrg\DoctrineEncryption\Tests\Fixtures;

use ProdumanOrg\DoctrineEncryption\Attribute\Encrypted;

final class PartialSecretNote
{
    #[Encrypted]
    private ?string $secret;

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function setSecretForTest(?string $secret): void
    {
        $this->secret = $secret;
    }
}
