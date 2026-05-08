<?php

declare(strict_types=1);

namespace ProdumanOrg\DoctrineEncryption\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use ProdumanOrg\DoctrineEncryption\Command\GenerateKeyCommand;
use ProdumanOrg\DoctrineEncryption\Command\ValidateKeyCommand;
use ProdumanOrg\DoctrineEncryption\Contract\FieldEncryptorInterface;
use ProdumanOrg\DoctrineEncryption\DependencyInjection\DoctrineEncryptionExtension;
use ProdumanOrg\DoctrineEncryption\Encryption\HaliteFieldEncryptor;
use ProdumanOrg\DoctrineEncryption\EventSubscriber\DoctrineEncryptionSubscriber;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class DoctrineEncryptionExtensionTest extends TestCase
{
    public function testItRegistersBundleServices(): void
    {
        $container = new ContainerBuilder();

        (new DoctrineEncryptionExtension())->load([], $container);

        self::assertTrue($container->hasDefinition(HaliteFieldEncryptor::class));
        self::assertTrue($container->hasDefinition(DoctrineEncryptionSubscriber::class));
        self::assertTrue($container->hasDefinition(GenerateKeyCommand::class));
        self::assertTrue($container->hasDefinition(ValidateKeyCommand::class));
        self::assertTrue($container->hasAlias(FieldEncryptorInterface::class));
        self::assertSame(
            '%doctrine_encryption.key_file%',
            $container->getDefinition(HaliteFieldEncryptor::class)->getArgument('$keyFile'),
        );
        self::assertSame('%doctrine_encryption.auto_generate_key%', $container->getDefinition(HaliteFieldEncryptor::class)->getArgument('$autoGenerateKey'));
        self::assertSame('%doctrine_encryption.allow_plaintext%', $container->getDefinition(HaliteFieldEncryptor::class)->getArgument('$allowPlaintext'));
        self::assertSame('%env(resolve:DOCTRINE_ENCRYPTION_KEY_FILE)%', $container->getParameter('doctrine_encryption.key_file'));
        self::assertFalse($container->getParameter('doctrine_encryption.auto_generate_key'));
        self::assertFalse($container->getParameter('doctrine_encryption.allow_plaintext'));
        self::assertTrue($container->getDefinition(DoctrineEncryptionSubscriber::class)->hasTag('doctrine.event_subscriber'));
        self::assertSame(
            [['command' => 'doctrine-encryption:generate-key']],
            $container->getDefinition(GenerateKeyCommand::class)->getTag('console.command'),
        );
        self::assertSame(
            [['command' => 'doctrine-encryption:validate-key']],
            $container->getDefinition(ValidateKeyCommand::class)->getTag('console.command'),
        );
    }

    public function testItAcceptsExplicitConfiguration(): void
    {
        $container = new ContainerBuilder();

        (new DoctrineEncryptionExtension())->load([[
            'key_file' => '/run/secrets/doctrine-encryption.key',
            'auto_generate_key' => true,
            'allow_plaintext' => true,
        ]], $container);

        self::assertSame('/run/secrets/doctrine-encryption.key', $container->getParameter('doctrine_encryption.key_file'));
        self::assertTrue($container->getParameter('doctrine_encryption.auto_generate_key'));
        self::assertTrue($container->getParameter('doctrine_encryption.allow_plaintext'));
    }

    public function testItRejectsEmptyKeyFileConfiguration(): void
    {
        $container = new ContainerBuilder();

        $this->expectException(InvalidConfigurationException::class);

        (new DoctrineEncryptionExtension())->load([[
            'key_file' => '',
        ]], $container);
    }
}
