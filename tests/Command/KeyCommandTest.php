<?php

declare(strict_types=1);

namespace ProdumanOrg\DoctrineEncryption\Tests\Command;

use FilesystemIterator;
use ParagonIE\Halite\KeyFactory;
use PHPUnit\Framework\TestCase;
use ProdumanOrg\DoctrineEncryption\Command\GenerateKeyCommand;
use ProdumanOrg\DoctrineEncryption\Command\ValidateKeyCommand;
use ProdumanOrg\DoctrineEncryption\Key\KeyFileManager;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class KeyCommandTest extends TestCase
{
    private string $directory;
    private string $keyFile;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/doctrine-encryption-command-'.bin2hex(random_bytes(8));
        $this->keyFile = $this->directory.'/config/secrets/test/.Halite.key';
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testGenerateKeyCommandCreatesKeyFile(): void
    {
        $tester = new CommandTester(new GenerateKeyCommand(new KeyFileManager($this->keyFile)));

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertFileExists($this->keyFile);
        self::assertSame('0600', substr(sprintf('%o', fileperms($this->keyFile)), -4));
        self::assertStringContainsString('Back up this key securely', $tester->getDisplay());
    }

    public function testGenerateKeyCommandAcceptsKeyFileOverride(): void
    {
        $configuredKeyFile = $this->directory.'/configured/.Halite.key';
        $overrideKeyFile = $this->directory.'/override/.Halite.key';
        $tester = new CommandTester(new GenerateKeyCommand(new KeyFileManager($configuredKeyFile)));

        $exitCode = $tester->execute(['--key-file' => $overrideKeyFile]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertFileDoesNotExist($configuredKeyFile);
        self::assertFileExists($overrideKeyFile);
        self::assertStringContainsString($overrideKeyFile, $tester->getDisplay());
    }

    public function testGenerateKeyCommandDoesNotOverwriteExistingKeyWithoutForce(): void
    {
        self::assertTrue(mkdir(dirname($this->keyFile), 0o700, true));
        KeyFactory::save(KeyFactory::generateEncryptionKey(), $this->keyFile);

        $tester = new CommandTester(new GenerateKeyCommand(new KeyFileManager($this->keyFile)));

        $exitCode = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('already exists', $tester->getDisplay());
    }

    public function testGenerateKeyCommandOverwritesExistingKeyWithForce(): void
    {
        self::assertTrue(mkdir(dirname($this->keyFile), 0o700, true));
        KeyFactory::save(KeyFactory::generateEncryptionKey(), $this->keyFile);
        $oldContents = file_get_contents($this->keyFile);

        $tester = new CommandTester(new GenerateKeyCommand(new KeyFileManager($this->keyFile)));

        $exitCode = $tester->execute(['--force' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertIsString($oldContents);
        self::assertNotSame($oldContents, file_get_contents($this->keyFile));
    }

    public function testValidateKeyCommandAcceptsValidKey(): void
    {
        self::assertTrue(mkdir(dirname($this->keyFile), 0o700, true));
        KeyFactory::save(KeyFactory::generateEncryptionKey(), $this->keyFile);
        chmod($this->keyFile, 0o600);

        $tester = new CommandTester(new ValidateKeyCommand(new KeyFileManager($this->keyFile)));

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('is valid', $tester->getDisplay());
    }

    public function testValidateKeyCommandAcceptsKeyFileOverride(): void
    {
        $configuredKeyFile = $this->directory.'/configured/.Halite.key';
        $overrideKeyFile = $this->directory.'/override/.Halite.key';
        self::assertTrue(mkdir(dirname($overrideKeyFile), 0o700, true));
        KeyFactory::save(KeyFactory::generateEncryptionKey(), $overrideKeyFile);
        chmod($overrideKeyFile, 0o600);

        $tester = new CommandTester(new ValidateKeyCommand(new KeyFileManager($configuredKeyFile)));

        $exitCode = $tester->execute(['--key-file' => $overrideKeyFile]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString($overrideKeyFile, $tester->getDisplay());
    }

    public function testValidateKeyCommandRejectsMissingKey(): void
    {
        $tester = new CommandTester(new ValidateKeyCommand(new KeyFileManager($this->keyFile)));

        $exitCode = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('does not exist', $tester->getDisplay());
    }

    public function testValidateKeyCommandRejectsCorruptedKey(): void
    {
        self::assertTrue(mkdir(dirname($this->keyFile), 0o700, true));
        file_put_contents($this->keyFile, 'not a halite key');
        chmod($this->keyFile, 0o600);

        $tester = new CommandTester(new ValidateKeyCommand(new KeyFileManager($this->keyFile)));

        $exitCode = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('not a valid Halite encryption key', $tester->getDisplay());
    }

    public function testValidateKeyCommandRejectsBroadPermissions(): void
    {
        self::assertTrue(mkdir(dirname($this->keyFile), 0o700, true));
        KeyFactory::save(KeyFactory::generateEncryptionKey(), $this->keyFile);
        chmod($this->keyFile, 0o644);

        $tester = new CommandTester(new ValidateKeyCommand(new KeyFileManager($this->keyFile)));

        $exitCode = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('permissions are too broad', $tester->getDisplay());
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
