<?php

declare(strict_types=1);

use ProdumanOrg\DoctrineEncryption\Contract\FieldEncryptorInterface;
use ProdumanOrg\DoctrineEncryption\Encryption\HaliteFieldEncryptor;
use ProdumanOrg\DoctrineEncryption\EventSubscriber\DoctrineEncryptionSubscriber;
use ProdumanOrg\DoctrineEncryption\Metadata\EncryptedFieldMetadataFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(EncryptedFieldMetadataFactory::class);

    $services->set(HaliteFieldEncryptor::class)
        ->arg('$keyFile', '%kernel.project_dir%/config/secrets/%kernel.environment%/.Halite.key');

    $services->alias(FieldEncryptorInterface::class, HaliteFieldEncryptor::class);

    $services->set(DoctrineEncryptionSubscriber::class)
        ->tag('doctrine.event_subscriber');
};
