<?php

declare(strict_types=1);

namespace DoctrineEncryption\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Encrypted {}
