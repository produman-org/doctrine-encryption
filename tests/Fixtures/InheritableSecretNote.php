<?php

declare(strict_types=1);

namespace ProdumanOrg\DoctrineEncryption\Tests\Fixtures;

use ProdumanOrg\DoctrineEncryption\Attribute\Encrypted;

readonly class InheritableSecretNote
{
    public function __construct(
        #[Encrypted]
        private ?string $secret,
    ) {
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }
}
