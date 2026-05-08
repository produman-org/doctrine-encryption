<?php

declare(strict_types=1);

namespace DoctrineEncryption\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use DoctrineEncryption\Attribute\Encrypted;

#[ORM\Entity]
#[ORM\Table(name: 'orm_secret_notes')]
final class OrmSecretNote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title;

    #[Encrypted]
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $secret;

    public function __construct(string $title, ?string $secret)
    {
        $this->title = $title;
        $this->secret = $secret;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function setSecret(?string $secret): void
    {
        $this->secret = $secret;
    }
}
