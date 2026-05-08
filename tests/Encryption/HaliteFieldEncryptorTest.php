<?php

declare(strict_types=1);

namespace ProdumanOrg\DoctrineEncryption\Tests\Encryption;

use FilesystemIterator;
use ParagonIE\Halite\KeyFactory;
use PHPUnit\Framework\TestCase;
use ProdumanOrg\DoctrineEncryption\Encryption\HaliteFieldEncryptor;
use ProdumanOrg\DoctrineEncryption\Exception\ConfigurationException;
use ProdumanOrg\DoctrineEncryption\Exception\DecryptionFailedException;
use ProdumanOrg\DoctrineEncryption\Exception\InvalidCiphertextException;
use ProdumanOrg\DoctrineEncryption\Exception\KeyNotFoundException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class HaliteFieldEncryptorTest extends TestCase
{
    private string $keyFile;
    private string $keyDirectory;

    protected function setUp(): void
    {
        $this->keyDirectory = sys_get_temp_dir().'/doctrine-encryption-'.bin2hex(random_bytes(8));
        $this->keyFile = $this->keyDirectory.'/config/secrets/test/.Halite.key';

        self::assertTrue(mkdir(dirname($this->keyFile), 0o700, true));
        KeyFactory::save(KeyFactory::generateEncryptionKey(), $this->keyFile);
    }

    protected function tearDown(): void
    {
        if (is_file($this->keyFile)) {
            unlink($this->keyFile);
        }

        $this->removeDirectory($this->keyDirectory);
    }

    public function testItEncryptsAndDecryptsNullableStrings(): void
    {
        $encryptor = new HaliteFieldEncryptor($this->keyFile);

        self::assertNull($encryptor->encrypt(null));
        self::assertNull($encryptor->decrypt(null));

        $ciphertext = $encryptor->encrypt('top secret');

        self::assertIsString($ciphertext);
        self::assertNotSame('top secret', $ciphertext);
        self::assertStringStartsWith('doctrine-encryption:halite:v1:', $ciphertext);
        self::assertSame('top secret', $encryptor->decrypt($ciphertext));
    }

    public function testItRejectsPlaintextValuesByDefault(): void
    {
        $encryptor = new HaliteFieldEncryptor($this->keyFile);

        $this->expectException(InvalidCiphertextException::class);
        $this->expectExceptionMessage('Plaintext value found in an encrypted field');

        $encryptor->decrypt('legacy plaintext');
    }

    public function testItAllowsPlaintextValuesInLegacyMode(): void
    {
        $encryptor = new HaliteFieldEncryptor($this->keyFile, allowPlaintext: true);

        self::assertSame('legacy plaintext', $encryptor->decrypt('legacy plaintext'));
    }

    public function testItRejectsMissingKeyFileByDefault(): void
    {
        $keyFile = $this->keyDirectory.'/missing/config/secrets/test/.Halite.key';
        $encryptor = new HaliteFieldEncryptor($keyFile);

        $this->expectException(KeyNotFoundException::class);
        $this->expectExceptionMessage('was not found');

        $encryptor->encrypt('top secret');
    }

    public function testItCreatesMissingKeyFileWhenAutoGenerationIsEnabled(): void
    {
        $keyFile = $this->keyDirectory.'/another/config/secrets/test/.Halite.key';
        $encryptor = new HaliteFieldEncryptor($keyFile, autoGenerateKey: true);

        self::assertFileDoesNotExist($keyFile);

        $ciphertext = $encryptor->encrypt('top secret');

        self::assertFileExists($keyFile);
        self::assertFileExists($keyFile.'.lock');
        self::assertSame('0600', substr(sprintf('%o', fileperms($keyFile)), -4));
        self::assertSame('0600', substr(sprintf('%o', fileperms($keyFile.'.lock')), -4));
        self::assertSame('top secret', (new HaliteFieldEncryptor($keyFile))->decrypt($ciphertext));
    }

    public function testItRejectsCorruptedCiphertext(): void
    {
        $encryptor = new HaliteFieldEncryptor($this->keyFile);

        $this->expectException(DecryptionFailedException::class);
        $this->expectExceptionMessage('Unable to decrypt encrypted field value.');

        $encryptor->decrypt('doctrine-encryption:halite:v1:not-valid-ciphertext');
    }

    public function testItRejectsCiphertextEncryptedWithAnotherKey(): void
    {
        $anotherKeyFile = $this->keyDirectory.'/another-key/.Halite.key';
        self::assertTrue(mkdir(dirname($anotherKeyFile), 0o700, true));
        KeyFactory::save(KeyFactory::generateEncryptionKey(), $anotherKeyFile);

        $ciphertext = (new HaliteFieldEncryptor($this->keyFile))->encrypt('top secret');

        $this->expectException(DecryptionFailedException::class);

        (new HaliteFieldEncryptor($anotherKeyFile))->decrypt($ciphertext);
    }

    public function testItRejectsEmptyKeyFilePath(): void
    {
        $encryptor = new HaliteFieldEncryptor('');

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('The "doctrine_encryption.key_file" option must not be empty.');

        $encryptor->encrypt('top secret');
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        ) as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
                continue;
            }

            unlink($item->getPathname());
        }

        rmdir($directory);
    }
}
