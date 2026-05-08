<?php

declare(strict_types=1);

namespace ProdumanOrg\DoctrineEncryption\Tests\Support;

use ProdumanOrg\DoctrineEncryption\Contract\CiphertextDetectorInterface;
use ProdumanOrg\DoctrineEncryption\Contract\FieldEncryptorInterface;

final class InMemoryFieldEncryptor implements FieldEncryptorInterface, CiphertextDetectorInterface
{
    /**
     * @var list<string|null>
     */
    public array $encryptedValues = [];

    /**
     * @var list<string|null>
     */
    public array $decryptedValues = [];

    public function encrypt(?string $value): ?string
    {
        $this->encryptedValues[] = $value;

        return null === $value ? null : 'enc:'.$value;
    }

    public function decrypt(?string $value): ?string
    {
        $this->decryptedValues[] = $value;

        return $this->isCiphertext($value) ? substr((string) $value, 4) : $value;
    }

    public function isCiphertext(?string $value): bool
    {
        return null !== $value && str_starts_with($value, 'enc:');
    }
}
