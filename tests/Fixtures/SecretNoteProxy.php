<?php

declare(strict_types=1);

namespace DoctrineEncryption\Tests\Fixtures;

use Doctrine\Persistence\Proxy;

/**
 * @implements Proxy<InheritableSecretNote>
 */
final readonly class SecretNoteProxy extends InheritableSecretNote implements Proxy
{
    public function __load(): void
    {
    }

    public function __isInitialized(): bool
    {
        return true;
    }
}
