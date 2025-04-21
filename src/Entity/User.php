<?php

namespace Entity;

use Closure;
use ORM\Attributes\Column;
use ORM\Attributes\Entity;
use ORM\Attributes\GeneratedValue;
use ORM\Attributes\Id;
use ORM\Attributes\JoinColumn;
use ORM\Attributes\OneToMany;
use ORM\Attributes\OneToOne;
use ORM\Attributes\Table;
use ORM\Collection;
use ORM\Entity\EntityBase;
use ORM\Entity\Type\CascadeType;
use ORM\Entity\Type\FetchType;

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

    #[Column(type: "string", length: 255, nullable: false, default: "default_email@example.com")]
    private string $email;

    #[OneToOne(
        entity: Profile::class,
        cascade: [CascadeType::Persist, CascadeType::Remove],
        fetch: FetchType::Eager,
    )]
    #[JoinColumn(name: "profile_id", referencedColumn: "id", nullable: false)]
    private Profile|Closure|null $profile = null;

    #[OneToMany(
        entity: Post::class,
        mappedBy: "user",
        cascade: [CascadeType::Persist, CascadeType::Remove],
        fetch: FetchType::Lazy,
    )]
    private Collection|Closure $posts;

    public function __construct()
    {
        $this->posts = new Collection();
    }

    public function getId(): int
    {
        return $this->id;
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
        if ($this->profile instanceof Closure) {
            $this->profile = ($this->profile)();
        }
        return $this->profile;
    }

    public function setProfile(Profile $profile): self
    {
        $this->profile = $profile;
        return $this;
    }

    public function  addPost(Post $post): self
    {
        $post->setUser($this);
        $this->posts->add($post);
        return $this;
    }

    public function getPosts(): Collection
    {
        if ($this->posts instanceof Closure) {
            $this->posts = ($this->posts)();
        }

        return $this->posts;
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
