<?php

declare(strict_types=1);

namespace ProdumanOrg\DoctrineEncryption\Encryption;

use ParagonIE\Halite\Symmetric\Crypto;
use ParagonIE\Halite\Symmetric\EncryptionKey;
use ParagonIE\HiddenString\HiddenString;
use ProdumanOrg\DoctrineEncryption\Contract\CiphertextDetectorInterface;
use ProdumanOrg\DoctrineEncryption\Contract\FieldEncryptorInterface;
use ProdumanOrg\DoctrineEncryption\Exception\DecryptionFailedException;
use ProdumanOrg\DoctrineEncryption\Exception\InvalidCiphertextException;
use ProdumanOrg\DoctrineEncryption\Key\KeyFileManager;
use Throwable;

final class HaliteFieldEncryptor implements FieldEncryptorInterface, CiphertextDetectorInterface
{
    private const CIPHERTEXT_PREFIX = 'doctrine-encryption:halite:v1:';

    private ?EncryptionKey $key = null;
    private readonly KeyFileManager $keyFileManager;

    public function __construct(
        string $keyFile,
        private readonly bool $autoGenerateKey = false,
        private readonly bool $allowPlaintext = false,
    ) {
        $this->keyFileManager = new KeyFileManager($keyFile);
    }

    public function encrypt(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        return self::CIPHERTEXT_PREFIX.Crypto::encrypt(new HiddenString($value), $this->key());
    }

    public function decrypt(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$this->isCiphertext($value)) {
            if ($this->allowPlaintext) {
                return $value;
            }

            throw InvalidCiphertextException::plaintextNotAllowed();
        }

        try {
            return Crypto::decrypt(substr($value, strlen(self::CIPHERTEXT_PREFIX)), $this->key())->getString();
        } catch (Throwable $exception) {
            throw DecryptionFailedException::forCiphertext($exception);
        }
    }

    public function isCiphertext(?string $value): bool
    {
        return null !== $value && str_starts_with($value, self::CIPHERTEXT_PREFIX);
    }

    private function key(): EncryptionKey
    {
        if (null !== $this->key) {
            return $this->key;
        }

        return $this->key = $this->keyFileManager->load($this->autoGenerateKey);
    }
}
