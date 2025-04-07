<?php

namespace Entity;

use JsonSerializable;
use ORM\Attributes\Column;
use ORM\Attributes\JoinColumn;
use ORM\Attributes\OneToOne;
use ORM\Attributes\PrimaryGeneratedColumn;
use ORM\Attributes\Table;

#[Table(name: "users")]
class User implements JsonSerializable
{
//    #[Column(name: "id", type: "int", primary: true, autoIncrement: true)]
    #[PrimaryGeneratedColumn(name: "id", type: "int")]
    public int $id;

    #[Column(name: "username", type: "string", length: 255)]
    public string $username;

    #[Column(name: "email", type: "string", length: 255)]
    public string $email;

    #[OneToOne(entity: Profile::class)]
    #[JoinColumn(name: "profile_id", referencedColumn: "id")]
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
