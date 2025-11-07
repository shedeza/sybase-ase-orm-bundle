<?php

namespace Shedeza\SybaseAseOrmBundle\Repository;

use Shedeza\SybaseAseOrmBundle\ORM\Repository\AbstractRepository;
use Shedeza\SybaseAseOrmBundle\Entity\Post;
use Shedeza\SybaseAseOrmBundle\Entity\User;

class PostRepository extends AbstractRepository
{
    public function findByTitle(string $title): ?Post
    {
        $query = $this->createQuery('SELECT p FROM Post p WHERE p.title = :title');
        $query->setParameter('title', $title);
        return $query->getSingleResult();
    }

    public function findByAuthor(User $author): array
    {
        $query = $this->createQuery('SELECT p FROM Post p WHERE p.author = :authorId ORDER BY p.id DESC');
        $query->setParameter('authorId', $author->getId());
        return $query->getResult();
    }

    public function findByAuthorUsername(string $username): array
    {
        $query = $this->createQuery('
            SELECT p FROM Post p 
            INNER JOIN p.author u WITH u.username = :username
            ORDER BY p.id DESC
        ');
        $query->setParameter('username', $username);
        return $query->getResult();
    }
    
    public function findPostsWithAuthorInfo(): array
    {
        $query = $this->createQuery('
            SELECT p FROM Post p
            INNER JOIN p.author u WITH u.createdAt IS NOT NULL
            ORDER BY p.id DESC
        ');
        return $query->getResult();
    }
    
    public function findPostsWithNestedJoin(): array
    {
        $query = $this->createQuery('
            SELECT p FROM Post p
            INNER JOIN p.author u
            LEFT JOIN u.posts p2 WITH p2.id != p.id
            WHERE u.email LIKE :domain
            ORDER BY p.id DESC
        ');
        $query->setParameter('domain', '%@example.com');
        return $query->getResult();
    }
    
    public function findPostsByActiveAuthors(): array
    {
        $query = $this->createQuery('
            SELECT p FROM Post p
            INNER JOIN p.author u WITH u.createdAt IS NOT NULL AND u.email IS NOT NULL
            ORDER BY p.id DESC
        ');
        return $query->getResult();
    }
    
    public function findPostsWithAuthorDomain(string $domain): array
    {
        $query = $this->createQuery('
            SELECT p FROM Post p
            INNER JOIN p.author u WITH u.email LIKE :domain
            ORDER BY u.username ASC, p.id DESC
        ');
        $query->setParameter('domain', "%@{$domain}");
        return $query->getResult();
    }

    public function findPostsWithContent(string $searchTerm): array
    {
        $query = $this->createQuery('
            SELECT p FROM Post p 
            WHERE p.content LIKE :searchTerm OR p.title LIKE :searchTerm
            ORDER BY p.id DESC
        ');
        $query->setParameter('searchTerm', "%{$searchTerm}%");
        return $query->getResult();
    }

    public function findLatestPosts(int $limit = 10): array
    {
        $query = $this->createQuery('SELECT p FROM Post p ORDER BY p.id DESC');
        return array_slice($query->getResult(), 0, $limit);
    }

    public function countPostsByAuthor(User $author): int
    {
        $query = $this->createQuery('SELECT COUNT(p) as total FROM Post p WHERE p.author = :authorId');
        $query->setParameter('authorId', $author->getId());
        $result = $query->getSingleResult();
        return $result ? (int)$result : 0;
    }
}