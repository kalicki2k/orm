<?php

namespace Entity;

use ORM\Attributes\Column;
use ORM\Attributes\Entity;
use ORM\Attributes\GeneratedValue;
use ORM\Attributes\Id;
use ORM\Attributes\JoinColumn;
use ORM\Attributes\OneToOne;
use ORM\Attributes\Table;
use ORM\Entity\EntityBase;

#[Entity]
#[Table("users")]
class User extends EntityBase
{
    #[Id]
    #[GeneratedValue]
    #[Column(type: "int")]
    private int $id;

    #[Column(type: "string", length: 255, nullable: false)]
    private string $username;

    #[Column(type: "string", length: 255, nullable: false)]
    private string $email;

    #[OneToOne(entity: Profile::class)]
    #[JoinColumn(name: "profile_id", referencedColumn: "id", nullable: false)]
    private Profile $profile;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getProfile(): Profile
    {
        return $this->profile;
    }

    public function setProfile(Profile $profile): self
    {
        $this->profile = $profile;
        return $this;
    }

    public function jsonSerialize(): mixed
    {
        return [
            "id" => $this->getId(),
            "username" => $this->getUsername(),
            "email" => $this->getEmail(),
            "profile" => $this->getProfile(),
        ];
    }
}
