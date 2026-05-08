<?php

declare(strict_types=1);

namespace ProdumanOrg\DoctrineEncryption\Tests\Fixtures;

use ProdumanOrg\DoctrineEncryption\Attribute\Encrypted;

final class MixedSecretNote
{
    public function __construct(
        #[Encrypted]
        private mixed $secret,
    ) {
    }

    public function getSecret(): mixed
    {
        return $this->secret;
    }
}
