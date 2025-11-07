<?php

namespace Shedeza\SybaseAseOrmBundle\Repository;

use Shedeza\SybaseAseOrmBundle\ORM\Repository\AbstractRepository;
use Shedeza\SybaseAseOrmBundle\Entity\User;

class UserRepository extends AbstractRepository
{
    public function findByUsername(string $username): ?User
    {
        $query = $this->createQuery('SELECT u FROM User u WHERE u.username = :username');
        $query->setParameter('username', $username);
        return $query->getSingleResult();
    }

    public function findByEmail(string $email): ?User
    {
        $query = $this->createQuery('SELECT u FROM User u WHERE u.email = :email');
        $query->setParameter('email', $email);
        return $query->getSingleResult();
    }

    public function findActiveUsers(): array
    {
        $query = $this->createQuery('SELECT u FROM User u WHERE u.createdAt IS NOT NULL ORDER BY u.createdAt DESC');
        return $query->getResult();
    }

    public function findUsersWithPosts(): array
    {
        $query = $this->createQuery('
            SELECT u FROM User u 
            INNER JOIN u.posts p
            ORDER BY u.username ASC
        ');
        return $query->getResult();
    }
    
    public function findUsersWithPostCount(): array
    {
        $query = $this->createQuery('
            SELECT u FROM User u
            LEFT JOIN u.posts p
            ORDER BY u.username ASC
        ');
        return $query->getResult();
    }
    
    public function findUsersWithRecentPosts(int $days = 30): array
    {
        $query = $this->createQuery('
            SELECT u FROM User u
            INNER JOIN u.posts p WITH p.createdAt >= :date
            ORDER BY u.username ASC
        ');
        $date = new \DateTime("-{$days} days");
        $query->setParameter('date', $date->format('Y-m-d H:i:s'));
        return $query->getResult();
    }
    
    public function findUsersWithPostsByTitle(string $titlePattern): array
    {
        $query = $this->createQuery('
            SELECT u FROM User u
            INNER JOIN u.posts p WITH p.title LIKE :title
            ORDER BY u.username ASC
        ');
        $query->setParameter('title', $titlePattern);
        return $query->getResult();
    }

    public function findRecentUsers(int $days = 30): array
    {
        $query = $this->createQuery('
            SELECT u FROM User u 
            WHERE u.createdAt >= :date 
            ORDER BY u.createdAt DESC
        ');
        $date = new \DateTime("-{$days} days");
        $query->setParameter('date', $date->format('Y-m-d H:i:s'));
        return $query->getResult();
    }

    public function countUsersByDomain(string $domain): int
    {
        $query = $this->createQuery('
            SELECT COUNT(u) as total FROM User u 
            WHERE u.email LIKE :domain
        ');
        $query->setParameter('domain', "%@{$domain}");
        $result = $query->getSingleResult();
        return $result ? (int)$result : 0;
    }
}