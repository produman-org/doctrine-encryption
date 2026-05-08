<?php

declare(strict_types=1);

namespace DoctrineEncryption\Tests\Fixtures;

final readonly class PublicNote
{
    public function __construct(public ?string $title)
    {
    }
}
