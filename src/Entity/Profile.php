<?php

namespace Entity;

use ORM\Attributes\Column;
use ORM\Attributes\Entity;
use ORM\Attributes\GeneratedValue;
use ORM\Attributes\Id;
use ORM\Attributes\OneToOne;
use ORM\Attributes\Table;
use ORM\Entity\EntityBase;

#[Entity]
#[Table("profiles")]
class Profile extends EntityBase
{
    #[Id]
    #[GeneratedValue]
    #[Column(type: "int", name: "id", nullable: false)]
    private int $id;

    #[Column(type: "text", name: "bio", nullable: true)]
    private ?string $bio = null;

    #[OneToOne(entity: User::class, mappedBy: "profile")]
    private ?User $user = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): self
    {
        $this->bio = $bio;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function jsonSerialize(): mixed
    {
        return [
            "id"  => $this->getId(),
            "bio" => $this->getBio(),
        ];
    }
}