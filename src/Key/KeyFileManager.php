<?php

declare(strict_types=1);

namespace ProdumanOrg\DoctrineEncryption\Key;

use ParagonIE\Halite\KeyFactory;
use ParagonIE\Halite\Symmetric\EncryptionKey;
use ProdumanOrg\DoctrineEncryption\Exception\ConfigurationException;
use ProdumanOrg\DoctrineEncryption\Exception\DoctrineEncryptionException;
use ProdumanOrg\DoctrineEncryption\Exception\KeyGenerationException;
use ProdumanOrg\DoctrineEncryption\Exception\KeyNotFoundException;
use Throwable;

/**
 * @internal
 */
final readonly class KeyFileManager
{
    public function __construct(private string $keyFile)
    {
    }

    public function keyFile(): string
    {
        return $this->keyFile;
    }

    public function load(bool $autoGenerate): EncryptionKey
    {
        $this->assertKeyFilePathIsConfigured();

        if (!is_file($this->keyFile)) {
            if (!$autoGenerate) {
                throw KeyNotFoundException::forPath($this->keyFile);
            }

            $this->generateIfMissing();
        }

        if (!is_readable($this->keyFile)) {
            throw ConfigurationException::unreadableKeyFile($this->keyFile);
        }

        try {
            return KeyFactory::loadEncryptionKey($this->keyFile);
        } catch (Throwable) {
            throw ConfigurationException::invalidKeyFile($this->keyFile);
        }
    }

    public function generate(bool $force): void
    {
        $this->generateKeyFile($force, true);
    }

    private function generateIfMissing(): void
    {
        $this->generateKeyFile(false, false);
    }

    private function generateKeyFile(bool $force, bool $failIfExists): void
    {
        $this->assertKeyFilePathIsConfigured();

        $directory = dirname($this->keyFile);

        if (!is_dir($directory) && !mkdir($directory, 0o700, true) && !is_dir($directory)) {
            throw new KeyGenerationException(sprintf('Unable to create Halite key directory "%s".', $directory));
        }

        $lockFile = $this->keyFile.'.lock';
        $lockHandle = fopen($lockFile, 'c');

        if (false === $lockHandle) {
            throw new KeyGenerationException(sprintf('Unable to open Halite key lock file "%s".', $lockFile));
        }

        $this->chmod($lockFile, 0o600);

        try {
            if (!flock($lockHandle, LOCK_EX)) {
                throw new KeyGenerationException(sprintf('Unable to lock Halite key lock file "%s".', $lockFile));
            }

            if (is_file($this->keyFile) && !$force) {
                if ($failIfExists) {
                    throw new KeyGenerationException(sprintf('Halite key file "%s" already exists. Use --force to overwrite it.', $this->keyFile));
                }

                return;
            }

            $temporaryKeyFile = null;

            try {
                $temporaryKeyFile = sprintf(
                    '%s.%s.tmp',
                    $this->keyFile,
                    bin2hex(random_bytes(8)),
                );

                KeyFactory::save(KeyFactory::generateEncryptionKey(), $temporaryKeyFile);
                $this->chmod($temporaryKeyFile, 0o600);

                if (!rename($temporaryKeyFile, $this->keyFile)) {
                    throw new KeyGenerationException(sprintf('Unable to move generated Halite key file to "%s".', $this->keyFile));
                }

                $this->chmod($this->keyFile, 0o600);
            } catch (DoctrineEncryptionException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                throw new KeyGenerationException(sprintf('Unable to generate Halite key file "%s".', $this->keyFile), 0, $exception);
            } finally {
                if (null !== $temporaryKeyFile && is_file($temporaryKeyFile)) {
                    unlink($temporaryKeyFile);
                }
            }
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    /**
     * @return list<string>
     */
    public function validationErrors(): array
    {
        $errors = [];

        if ('' === trim($this->keyFile)) {
            return ['The key file path is empty.'];
        }

        if (!is_file($this->keyFile)) {
            return [sprintf('The key file "%s" does not exist.', $this->keyFile)];
        }

        if (!is_readable($this->keyFile)) {
            $errors[] = sprintf('The key file "%s" is not readable.', $this->keyFile);
        }

        $permissions = fileperms($this->keyFile);
        if (false !== $permissions && 0 !== ($permissions & 0o077)) {
            $errors[] = sprintf('The key file "%s" permissions are too broad. Expected 0600 or stricter.', $this->keyFile);
        }

        try {
            KeyFactory::loadEncryptionKey($this->keyFile);
        } catch (Throwable) {
            $errors[] = sprintf('The key file "%s" is not a valid Halite encryption key.', $this->keyFile);
        }

        return $errors;
    }

    private function assertKeyFilePathIsConfigured(): void
    {
        if ('' === trim($this->keyFile)) {
            throw ConfigurationException::emptyKeyFile();
        }
    }

    private function chmod(string $path, int $mode): void
    {
        if (!chmod($path, $mode)) {
            throw new KeyGenerationException(sprintf('Unable to set permissions on "%s".', $path));
        }
    }
}
