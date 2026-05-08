<?php

declare(strict_types=1);

namespace DoctrineEncryption\Tests\Fixtures;

use DoctrineEncryption\Attribute\Encrypted;

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
