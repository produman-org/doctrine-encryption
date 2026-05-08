<?php

declare(strict_types=1);

namespace DoctrineEncryption\Tests\Fixtures;

use DoctrineEncryption\Attribute\Encrypted;

readonly class InheritableSecretNote
{
    public function __construct(
        #[Encrypted]
        private ?string $secret,
    ) {}

    public function getSecret(): ?string
    {
        return $this->secret;
    }
}
