<?php

declare(strict_types=1);

namespace ProdumanOrg\DoctrineEncryption\Tests\Encryption;

use FilesystemIterator;
use ParagonIE\Halite\KeyFactory;
use PHPUnit\Framework\TestCase;
use ProdumanOrg\DoctrineEncryption\Encryption\HaliteFieldEncryptor;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

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

    public function testItReturnsPlaintextValuesWithoutCiphertextPrefixAsIs(): void
    {
        $encryptor = new HaliteFieldEncryptor($this->keyFile);

        self::assertSame('legacy plaintext', $encryptor->decrypt('legacy plaintext'));
    }

    public function testItCreatesMissingKeyFile(): void
    {
        $keyFile = $this->keyDirectory.'/another/config/secrets/test/.Halite.key';
        $encryptor = new HaliteFieldEncryptor($keyFile);

        self::assertFileDoesNotExist($keyFile);

        $ciphertext = $encryptor->encrypt('top secret');

        self::assertFileExists($keyFile);
        self::assertFileExists($keyFile.'.lock');
        self::assertSame('0600', substr(sprintf('%o', fileperms($keyFile)), -4));
        self::assertSame('0600', substr(sprintf('%o', fileperms($keyFile.'.lock')), -4));
        self::assertSame('top secret', (new HaliteFieldEncryptor($keyFile))->decrypt($ciphertext));
    }

    public function testItRejectsEmptyKeyFilePath(): void
    {
        $encryptor = new HaliteFieldEncryptor('');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Halite encryption key file path must not be empty.');

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
