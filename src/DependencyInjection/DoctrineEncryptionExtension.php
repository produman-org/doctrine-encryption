<?php

declare(strict_types=1);

namespace ProdumanOrg\DoctrineEncryption\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * @internal
 */
final class DoctrineEncryptionExtension extends Extension
{
    /**
     * @param array<int, array<string, mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{key_file: string, auto_generate_key: bool, allow_plaintext: bool} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('doctrine_encryption.key_file', $config['key_file']);
        $container->setParameter('doctrine_encryption.auto_generate_key', $config['auto_generate_key']);
        $container->setParameter('doctrine_encryption.allow_plaintext', $config['allow_plaintext']);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.php');
    }
}
