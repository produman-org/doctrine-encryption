<?php

declare(strict_types=1);

namespace ProdumanOrg\DoctrineEncryption\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @internal
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('doctrine_encryption');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('key_file')
                    ->defaultValue('%env(resolve:DOCTRINE_ENCRYPTION_KEY_FILE)%')
                    ->cannotBeEmpty()
                    ->validate()
                        ->ifTrue(static fn (mixed $value): bool => !is_string($value) || '' === trim($value))
                        ->thenInvalid('The "doctrine_encryption.key_file" option must not be empty.')
                    ->end()
                    ->info('Path to the Halite encryption key file.')
                ->end()
                ->booleanNode('auto_generate_key')
                    ->defaultFalse()
                    ->info('Generate the key file automatically when it is missing. Keep this disabled in production.')
                ->end()
                ->booleanNode('allow_plaintext')
                    ->defaultFalse()
                    ->info('Allow legacy plaintext values while migrating existing data.')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
