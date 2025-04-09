<?php

namespace Entity;

use JsonSerializable;
use ORM\Attributes\Column;
use ORM\Attributes\Entity;
use ORM\Attributes\OneToOne;
use ORM\Attributes\Table;

#[Entity]
#[Table("profiles")]
class Profile implements JsonSerializable
{
    #[Id]
    #[GeneratedValue]
    #[Column(type: "int", name: "id", nullable: false)]
    public int $id;

    #[Column(type: "text", name: "bio", nullable: true)]
    public ?string $bio = null;

    #[OneToOne(entity: User::class, mappedBy: "profile")]
    public ?User $user = null;

    public function jsonSerialize(): mixed
    {
        return [
            "id"  => $this->id,
            "bio" => $this->bio,
        ];
    }
}