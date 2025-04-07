<?php

namespace Entity;

use ORM\Attributes\Column;
use ORM\Attributes\OneToOne;
use ORM\Attributes\Table;

#[Table(name: "profiles")]
class Profile
{
    #[Column(name: "id", type: "int", primary: true, autoIncrement: true)]
    public int $id;

    #[Column(name: "bio", type: "string", length: 255)]
    public string $bio;

    #[Column(name: "birthday", type: "date")]
    public string $birthday;
}