<?php

declare(strict_types=1);

namespace ProdumanOrg\DoctrineEncryption\Tests\Fixtures;

use ProdumanOrg\DoctrineEncryption\Attribute\Encrypted;

final class ReadonlySecretNote
{
    public function __construct(
        #[Encrypted]
        private readonly string $secret,
    ) {
    }

    public function getSecret(): string
    {
        return $this->secret;
    }
}
