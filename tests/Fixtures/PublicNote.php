<?php

declare(strict_types=1);

namespace ProdumanOrg\DoctrineEncryption\Tests\Fixtures;

final readonly class PublicNote
{
    public function __construct(public ?string $title)
    {
    }
}
