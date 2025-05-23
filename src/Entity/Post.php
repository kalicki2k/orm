<?php

namespace Entity;

use Closure;
use ORM\Attributes\Column;
use ORM\Attributes\Entity;
use ORM\Attributes\GeneratedValue;
use ORM\Attributes\Id;
use ORM\Attributes\JoinColumn;
use ORM\Attributes\ManyToOne;
use ORM\Attributes\Table;
use ORM\Entity\EntityBase;
use ORM\Entity\Type\FetchType;

#[Entity]
#[Table("posts")]
class Post extends EntityBase
{
    #[Id]
    #[GeneratedValue]
    #[Column(type: "int")]
    private int $id;

    #[Column(type: "string", length: 255)]
    private string $title;

    #[Column(type: "text")]
    private string $content;

    #[ManyToOne(entity: User::class, fetch: FetchType::Eager)]
    #[JoinColumn(name: "user_id", referencedColumn: "id", nullable: false)]
    private User|Closure|null $user = null;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }


    /**
     * @param string $title
     * @return $this
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @param string $content
     * @return $this
     */
    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * @return User
     */
    public function getUser(): ?User
    {
        if ($this->user instanceof Closure) {
            $this->user = ($this->user)();
        }

        return $this->user;
    }

    /**
     * @param User $user
     * @return $this
     */
    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }
}
