<?php

namespace Entity;

use Closure;
use ORM\Attributes\Column;
use ORM\Attributes\Entity;
use ORM\Attributes\GeneratedValue;
use ORM\Attributes\Id;
use ORM\Attributes\OneToOne;
use ORM\Attributes\Table;
use ORM\Entity\EntityBase;
use ORM\Entity\Type\FetchType;

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
    private Closure|User|null $user = null;
//    private Closure|User $user;


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

    public function getUser(): User
    {
        if ($this->user instanceof \Closure) {
            $this->user = ($this->user)();
        }

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