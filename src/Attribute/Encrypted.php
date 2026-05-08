<?php

declare(strict_types=1);

namespace ProdumanOrg\DoctrineEncryption\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Encrypted
{
}
