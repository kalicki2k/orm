<?php

namespace Entity;

use ORM\Attributes\Column;
use ORM\Attributes\Entity;
use ORM\Attributes\GeneratedValue;
use ORM\Attributes\Id;
use ORM\Attributes\Table;
use ORM\Entity\EntityBase;

#[Entity]
#[Table("roles")]
class Role extends EntityBase
{
    #[Id]
    #[GeneratedValue]
    #[Column(type: "int", name: "id", nullable: false)]
    private int $id;

    #[Column(type: "string", name: "name", nullable: false)]
    private string $name;

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }
}
