<?php

declare(strict_types=1);

namespace ProdumanOrg\DoctrineEncryption\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use ProdumanOrg\DoctrineEncryption\Contract\FieldEncryptorInterface;
use ProdumanOrg\DoctrineEncryption\DependencyInjection\DoctrineEncryptionExtension;
use ProdumanOrg\DoctrineEncryption\Encryption\HaliteFieldEncryptor;
use ProdumanOrg\DoctrineEncryption\EventSubscriber\DoctrineEncryptionSubscriber;
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
