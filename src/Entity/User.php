<?php

namespace Shedeza\SybaseAseOrmBundle\Entity;

use Shedeza\SybaseAseOrmBundle\ORM\Mapping as ORM;
use Shedeza\SybaseAseOrmBundle\Repository\UserRepository;

#[ORM\Entity]
#[ORM\Repository(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: ORM\GeneratedValue::IDENTITY)]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    private string $username;

    #[ORM\Column(type: 'string', length: 255)]
    private string $email;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $createdAt = null;

    #[ORM\OneToMany(targetEntity: Post::class, mappedBy: 'author')]
    private array $posts = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        if (empty($username) || strlen($username) > 100) {
            throw new \InvalidArgumentException('Username must be between 1 and 100 characters');
        }
        
        $this->username = $username;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            throw new \InvalidArgumentException('Invalid email address or too long (max 255 characters)');
        }
        
        $this->email = $email;
        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getPosts(): array
    {
        return $this->posts;
    }

    public function addPost(Post $post): self
    {
        if (!in_array($post, $this->posts, true)) {
            $this->posts[] = $post;
            $post->setAuthor($this);
        }
        return $this;
    }
}