<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Shedeza\SybaseAseOrmBundle\DBAL\Connection;
use Shedeza\SybaseAseOrmBundle\DBAL\DatabaseUrlParser;
use Shedeza\SybaseAseOrmBundle\ORM\EntityManager;
use Shedeza\SybaseAseOrmBundle\Entity\User;
use Shedeza\SybaseAseOrmBundle\Entity\Post;

// Configuración de conexión usando DATABASE_URL
$databaseUrl = $_ENV['SYBASE_DATABASE_URL'] ?? 'sybase://sa:password@localhost:5000/testdb?charset=utf8';
$config = DatabaseUrlParser::parseUrl($databaseUrl);

try {
    $connection = new Connection($config);
    $entityManager = new EntityManager($connection);

    echo "=== Ejemplos de JOINs en OQL ===\n\n";

    // Ejemplo 1: INNER JOIN simple
    echo "1. INNER JOIN simple:\n";
    $query = $entityManager->createQuery('
        SELECT u FROM User u 
        INNER JOIN Post p ON p.author = u.id 
        WHERE u.username = :username
    ');
    $query->setParameter('username', 'john_doe');
    $users = $query->getResult();
    echo "Usuarios con posts: " . count($users) . "\n\n";

    // Ejemplo 2: LEFT JOIN
    echo "2. LEFT JOIN:\n";
    $query = $entityManager->createQuery('
        SELECT u FROM User u 
        LEFT JOIN Post p ON p.author = u.id 
        ORDER BY u.username ASC
    ');
    $users = $query->getResult();
    echo "Todos los usuarios (con o sin posts): " . count($users) . "\n\n";

    // Ejemplo 3: JOIN con múltiples condiciones
    echo "3. JOIN con múltiples condiciones:\n";
    $query = $entityManager->createQuery('
        SELECT p FROM Post p 
        INNER JOIN User u ON p.author = u.id 
        WHERE u.email LIKE :domain AND p.title LIKE :title
        ORDER BY p.id DESC
    ');
    $query->setParameter('domain', '%@example.com');
    $query->setParameter('title', '%post%');
    $posts = $query->getResult();
    echo "Posts de usuarios con dominio example.com: " . count($posts) . "\n\n";

    // Ejemplo 4: JOINs anidados (múltiples JOINs)
    echo "4. JOINs anidados:\n";
    $query = $entityManager->createQuery('
        SELECT p FROM Post p
        INNER JOIN User u ON p.author = u.id
        LEFT JOIN Post p2 ON p2.author = u.id
        WHERE u.createdAt IS NOT NULL
        ORDER BY p.id DESC
    ');
    $posts = $query->getResult();
    echo "Posts con JOINs anidados: " . count($posts) . "\n\n";

    // Ejemplo 5: Usando repositorios personalizados con JOINs
    echo "5. Repositorios personalizados con JOINs:\n";
    $userRepository = $entityManager->getRepository(User::class);
    $usersWithPosts = $userRepository->findUsersWithPosts();
    echo "Usuarios con posts (via repositorio): " . count($usersWithPosts) . "\n";

    $usersWithPostCount = $userRepository->findUsersWithPostCount();
    echo "Usuarios con conteo de posts: " . count($usersWithPostCount) . "\n\n";

    $postRepository = $entityManager->getRepository(Post::class);
    $postsWithAuthor = $postRepository->findPostsWithAuthorInfo();
    echo "Posts con info del autor: " . count($postsWithAuthor) . "\n";

    $postsNested = $postRepository->findPostsWithNestedJoin();
    echo "Posts con JOINs anidados: " . count($postsNested) . "\n\n";

    // Ejemplo 6: JOIN complejo con subconsulta
    echo "6. JOIN complejo:\n";
    $query = $entityManager->createQuery('
        SELECT u FROM User u
        INNER JOIN Post p ON p.author = u.id
        WHERE u.id IN (
            SELECT DISTINCT u2.id FROM User u2
            INNER JOIN Post p2 ON p2.author = u2.id
            WHERE p2.title LIKE :searchTerm
        )
        ORDER BY u.username ASC
    ');
    $query->setParameter('searchTerm', '%post%');
    $users = $query->getResult();
    echo "Usuarios con posts que contienen 'post': " . count($users) . "\n\n";

    echo "=== Ejemplos de JOINs completados ===\n";

} catch (\InvalidArgumentException $e) {
    echo "Validation Error: " . $e->getMessage() . "\n";
} catch (\RuntimeException $e) {
    echo "Runtime Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Unexpected Error: " . $e->getMessage() . "\n";
    error_log($e->getTraceAsString());
}