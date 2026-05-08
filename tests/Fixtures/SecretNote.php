<?php

declare(strict_types=1);

namespace DoctrineEncryption\Tests\Fixtures;

use DoctrineEncryption\Attribute\Encrypted;

final class SecretNote
{
    public function __construct(
        public ?string $title,
        #[Encrypted]
        private ?string $secret,
        #[Encrypted]
        private ?string $nullableSecret = null,
    ) {}

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function setSecret(?string $secret): void
    {
        $this->secret = $secret;
    }

    public function getNullableSecret(): ?string
    {
        return $this->nullableSecret;
    }
}
