<?php

declare(strict_types=1);

namespace DoctrineEncryption\Tests\DependencyInjection;

use DoctrineEncryption\Contract\FieldEncryptorInterface;
use DoctrineEncryption\DependencyInjection\DoctrineEncryptionExtension;
use DoctrineEncryption\Encryption\HaliteFieldEncryptor;
use DoctrineEncryption\EventSubscriber\DoctrineEncryptionSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class DoctrineEncryptionExtensionTest extends TestCase
{
    public function testItRegistersBundleServices(): void
    {
        $container = new ContainerBuilder();

        (new DoctrineEncryptionExtension())->load([], $container);

        self::assertTrue($container->hasDefinition(HaliteFieldEncryptor::class));
        self::assertTrue($container->hasDefinition(DoctrineEncryptionSubscriber::class));
        self::assertTrue($container->hasAlias(FieldEncryptorInterface::class));
        self::assertSame(
            '%kernel.project_dir%/config/secrets/%kernel.environment%/.Halite.key',
            $container->getDefinition(HaliteFieldEncryptor::class)->getArgument('$keyFile'),
        );
        self::assertTrue($container->getDefinition(DoctrineEncryptionSubscriber::class)->hasTag('doctrine.event_subscriber'));
    }
}
