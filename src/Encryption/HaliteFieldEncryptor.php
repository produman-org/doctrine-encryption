<?php

declare(strict_types=1);

namespace DoctrineEncryption\Encryption;

use DoctrineEncryption\Contract\CiphertextDetectorInterface;
use DoctrineEncryption\Contract\FieldEncryptorInterface;
use ParagonIE\Halite\KeyFactory;
use ParagonIE\Halite\Symmetric\Crypto;
use ParagonIE\Halite\Symmetric\EncryptionKey;
use ParagonIE\HiddenString\HiddenString;

final class HaliteFieldEncryptor implements FieldEncryptorInterface, CiphertextDetectorInterface
{
    private const CIPHERTEXT_PREFIX = 'doctrine-encryption:halite:v1:';

    private ?EncryptionKey $key = null;

    public function __construct(private readonly string $keyFile)
    {
    }

    public function encrypt(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return self::CIPHERTEXT_PREFIX . Crypto::encrypt(new HiddenString($value), $this->key());
    }

    public function decrypt(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$this->isCiphertext($value)) {
            return $value;
        }

        return Crypto::decrypt(substr($value, strlen(self::CIPHERTEXT_PREFIX)), $this->key())->getString();
    }

    public function isCiphertext(?string $value): bool
    {
        return $value !== null && str_starts_with($value, self::CIPHERTEXT_PREFIX);
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

        $lockFile = $this->keyFile . '.lock';
        $lockHandle = fopen($lockFile, 'c');

        if ($lockHandle === false) {
            throw new \RuntimeException(sprintf('Unable to open Halite key lock file "%s".', $lockFile));
        }

        chmod($lockFile, 0600);

        try {
            if (!flock($lockHandle, LOCK_EX)) {
                throw new \RuntimeException(sprintf('Unable to lock Halite key lock file "%s".', $lockFile));
            }

            if (is_file($this->keyFile)) {
                return;
            }

            $temporaryKeyFile = sprintf(
                '%s.%s.tmp',
                $this->keyFile,
                bin2hex(random_bytes(8)),
            );

            try {
                KeyFactory::save(KeyFactory::generateEncryptionKey(), $temporaryKeyFile);
                chmod($temporaryKeyFile, 0600);

                if (!rename($temporaryKeyFile, $this->keyFile)) {
                    throw new \RuntimeException(sprintf('Unable to move generated Halite key file to "%s".', $this->keyFile));
                }

                chmod($this->keyFile, 0600);
            } finally {
                if (isset($temporaryKeyFile) && is_file($temporaryKeyFile)) {
                    unlink($temporaryKeyFile);
                }
            }
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }
}
