<?php

declare(strict_types=1);

namespace ProdumanOrg\DoctrineEncryption\Tests\Fixtures;

use Doctrine\Persistence\Proxy;

/**
 * @implements Proxy<InheritableSecretNote>
 */
final class SecretNoteProxy extends InheritableSecretNote implements Proxy
{
    public function __load(): void
    {
    }

    public function __isInitialized(): bool
    {
        return true;
    }
}
