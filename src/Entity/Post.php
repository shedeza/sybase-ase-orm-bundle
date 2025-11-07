<?php

namespace Shedeza\SybaseAseOrmBundle\Entity;

use Shedeza\SybaseAseOrmBundle\ORM\Mapping as ORM;
use Shedeza\SybaseAseOrmBundle\Repository\PostRepository;

#[ORM\Entity]
#[ORM\Repository(repositoryClass: PostRepository::class)]
#[ORM\Table(name: 'posts')]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: ORM\GeneratedValue::IDENTITY)]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: 'text')]
    private ?string $content = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'posts')]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id')]
    private ?User $author = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        if (empty($title) || strlen($title) > 255) {
            throw new \InvalidArgumentException('Title must be between 1 and 255 characters');
        }
        
        $this->title = $title;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        if (empty($content)) {
            throw new \InvalidArgumentException('Content cannot be empty');
        }
        
        $this->content = $content;
        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): self
    {
        $this->author = $author;
        return $this;
    }
}