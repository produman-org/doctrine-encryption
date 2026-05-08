<?php

declare(strict_types=1);

use ProdumanOrg\DoctrineEncryption\Command\GenerateKeyCommand;
use ProdumanOrg\DoctrineEncryption\Command\ValidateKeyCommand;
use ProdumanOrg\DoctrineEncryption\Contract\FieldEncryptorInterface;
use ProdumanOrg\DoctrineEncryption\Encryption\HaliteFieldEncryptor;
use ProdumanOrg\DoctrineEncryption\EventSubscriber\DoctrineEncryptionSubscriber;
use ProdumanOrg\DoctrineEncryption\Key\KeyFileManager;
use ProdumanOrg\DoctrineEncryption\Metadata\EncryptedFieldMetadataFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(EncryptedFieldMetadataFactory::class);

    $services->set(KeyFileManager::class)
        ->arg('$keyFile', '%doctrine_encryption.key_file%');

    $services->set(HaliteFieldEncryptor::class)
        ->arg('$keyFile', '%doctrine_encryption.key_file%')
        ->arg('$autoGenerateKey', '%doctrine_encryption.auto_generate_key%')
        ->arg('$allowPlaintext', '%doctrine_encryption.allow_plaintext%');

    $services->alias(FieldEncryptorInterface::class, HaliteFieldEncryptor::class);

    $services->set(DoctrineEncryptionSubscriber::class)
        ->tag('doctrine.event_subscriber');

    $services->set(GenerateKeyCommand::class)
        ->tag('console.command', ['command' => 'doctrine-encryption:generate-key']);

    $services->set(ValidateKeyCommand::class)
        ->tag('console.command', ['command' => 'doctrine-encryption:validate-key']);
};
