<?php

declare(strict_types=1);

namespace DoctrineEncryption\Encryption;

use DoctrineEncryption\Contract\FieldEncryptorInterface;
use ParagonIE\Halite\KeyFactory;
use ParagonIE\Halite\Symmetric\Crypto;
use ParagonIE\Halite\Symmetric\EncryptionKey;
use ParagonIE\HiddenString\HiddenString;

final class HaliteFieldEncryptor implements FieldEncryptorInterface
{
    private ?EncryptionKey $key = null;

    public function __construct(private readonly string $keyFile)
    {
    }

    public function encrypt(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Crypto::encrypt(new HiddenString($value), $this->key());
    }

    public function decrypt(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Crypto::decrypt($value, $this->key())->getString();
    }

    private function key(): EncryptionKey
    {
        if ($this->key !== null) {
            return $this->key;
        }

        $this->ensureKeyFileExists();

        return $this->key = KeyFactory::loadEncryptionKey($this->keyFile);
    }

    private function ensureKeyFileExists(): void
    {
        if ($this->keyFile === '') {
            throw new \RuntimeException('Halite encryption key file path must not be empty.');
        }

        if (is_file($this->keyFile)) {
            return;
        }

        $directory = dirname($this->keyFile);

        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create Halite key directory "%s".', $directory));
        }

        KeyFactory::save(KeyFactory::generateEncryptionKey(), $this->keyFile);
    }
}
