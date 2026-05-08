<?php

declare(strict_types=1);

namespace DoctrineEncryption\Tests\Integration;

use DoctrineEncryption\Contract\FieldEncryptorInterface;
use DoctrineEncryption\DoctrineEncryptionBundle;
use DoctrineEncryption\Encryption\HaliteFieldEncryptor;
use DoctrineEncryption\EventSubscriber\DoctrineEncryptionSubscriber;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;

final class SymfonyContainerTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/doctrine-encryption-kernel-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        if (isset($this->projectDir)) {
            $this->removeDirectory($this->projectDir);
        }
    }

    public function testBundleRegistersUsableServicesInSymfonyContainer(): void
    {
        $kernel = new DoctrineEncryptionTestKernel('test', true, $this->projectDir);

        try {
            $kernel->boot();
            $container = $kernel->getContainer();

            $encryptor = $container->get('test.doctrine_encryption.encryptor');
            $subscriber = $container->get('test.doctrine_encryption.subscriber');
            $fieldEncryptor = $container->get('test.doctrine_encryption.field_encryptor');

            self::assertInstanceOf(HaliteFieldEncryptor::class, $encryptor);
            self::assertInstanceOf(DoctrineEncryptionSubscriber::class, $subscriber);
            self::assertSame($encryptor, $fieldEncryptor);

            $ciphertext = $encryptor->encrypt('container secret');

            self::assertIsString($ciphertext);
            self::assertSame('container secret', $encryptor->decrypt($ciphertext));
            self::assertFileExists($this->projectDir . '/config/secrets/test/.Halite.key');
        } finally {
            $kernel->shutdown();
        }
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

final class DoctrineEncryptionTestKernel extends Kernel
{
    public function __construct(
        string $environment,
        bool $debug,
        private readonly string $projectDir,
    ) {
        parent::__construct($environment, $debug);
    }

    /**
     * @return iterable<BundleInterface>
     */
    public function registerBundles(): iterable
    {
        yield new DoctrineEncryptionBundle();
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(static function (ContainerBuilder $container): void {
            $container
                ->setAlias('test.doctrine_encryption.encryptor', HaliteFieldEncryptor::class)
                ->setPublic(true);

            $container
                ->setAlias('test.doctrine_encryption.field_encryptor', FieldEncryptorInterface::class)
                ->setPublic(true);

            $container
                ->setAlias('test.doctrine_encryption.subscriber', DoctrineEncryptionSubscriber::class)
                ->setPublic(true);
        });
    }

    public function getProjectDir(): string
    {
        return $this->projectDir;
    }

    public function getCacheDir(): string
    {
        return $this->projectDir . '/var/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return $this->projectDir . '/var/log';
    }
}
