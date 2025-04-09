<?php

namespace Entity;

use JsonSerializable;
use ORM\Attributes\Column;
use ORM\Attributes\Entity;
use ORM\Attributes\GeneratedValue;
use ORM\Attributes\Id;
use ORM\Attributes\JoinColumn;
use ORM\Attributes\OneToOne;
use ORM\Attributes\Table;

#[Entity]
#[Table("users")]
class User implements JsonSerializable
{
    #[Id]
    #[GeneratedValue]
    #[Column(type: "int")]
    public int $id;

    #[Column(type: "string", length: 255, nullable: false)]
    public string $username;

    #[Column(type: "string", length: 255, nullable: false)]
    public string $email;

    #[OneToOne(entity: Profile::class)]
    #[JoinColumn(name: "profile_id", referencedColumn: "id", nullable: false)]
    public Profile $profile;

    public function jsonSerialize(): mixed
    {
        return [
            "id" => $this->id,
            "username" => $this->username,
            "email" => $this->email,
            "profile" => $this->profile,
        ];
    }
}
