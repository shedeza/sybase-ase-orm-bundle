<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Shedeza\SybaseAseOrmBundle\DBAL\Connection;
use Shedeza\SybaseAseOrmBundle\DBAL\DatabaseUrlParser;
use Shedeza\SybaseAseOrmBundle\ORM\EntityManager;
use Shedeza\SybaseAseOrmBundle\Entity\User;
use Shedeza\SybaseAseOrmBundle\Entity\Post;

// Configuración de conexión usando DATABASE_SYBASE_URL
$databaseUrl = $_ENV['DATABASE_SYBASE_URL'] ?? 'sybase://sa:password@localhost:5000/testdb?charset=utf8';
$config = DatabaseUrlParser::parseUrl($databaseUrl);

try {
    $connection = new Connection($config);
    $entityManager = new EntityManager($connection);

    echo "=== Ejemplos de JOINs con sintaxis WITH (estilo Doctrine) ===\n\n";

    // Ejemplo 1: JOIN simple usando asociación
    echo "1. JOIN simple usando asociación:\n";
    $query = $entityManager->createQuery('
        SELECT u FROM User u 
        INNER JOIN u.posts p
        ORDER BY u.username ASC
    ');
    $users = $query->getResult();
    echo "Usuarios con posts (usando u.posts): " . count($users) . "\n\n";

    // Ejemplo 2: JOIN con condición WITH
    echo "2. JOIN con condición WITH:\n";
    $query = $entityManager->createQuery('
        SELECT u FROM User u 
        INNER JOIN u.posts p WITH p.title LIKE :title
        ORDER BY u.username ASC
    ');
    $query->setParameter('title', '%post%');
    $users = $query->getResult();
    echo "Usuarios con posts que contienen 'post': " . count($users) . "\n\n";

    // Ejemplo 3: LEFT JOIN con WITH
    echo "3. LEFT JOIN con WITH:\n";
    $query = $entityManager->createQuery('
        SELECT u FROM User u 
        LEFT JOIN u.posts p WITH p.createdAt >= :date
        ORDER BY u.username ASC
    ');
    $date = new \DateTime('-30 days');
    $query->setParameter('date', $date->format('Y-m-d H:i:s'));
    $users = $query->getResult();
    echo "Usuarios con posts recientes (LEFT JOIN): " . count($users) . "\n\n";

    // Ejemplo 4: JOIN inverso (desde Post a User)
    echo "4. JOIN inverso usando asociación:\n";
    $query = $entityManager->createQuery('
        SELECT p FROM Post p 
        INNER JOIN p.author u WITH u.email LIKE :domain
        ORDER BY p.id DESC
    ');
    $query->setParameter('domain', '%@example.com');
    $posts = $query->getResult();
    echo "Posts de usuarios con dominio example.com: " . count($posts) . "\n\n";

    // Ejemplo 5: JOINs anidados con WITH
    echo "5. JOINs anidados con WITH:\n";
    $query = $entityManager->createQuery('
        SELECT p FROM Post p
        INNER JOIN p.author u WITH u.createdAt IS NOT NULL
        LEFT JOIN u.posts p2 WITH p2.id != p.id AND p2.title LIKE :pattern
        ORDER BY p.id DESC
    ');
    $query->setParameter('pattern', '%post%');
    $posts = $query->getResult();
    echo "Posts con JOINs anidados y condiciones WITH: " . count($posts) . "\n\n";

    // Ejemplo 6: Múltiples condiciones en WITH
    echo "6. Múltiples condiciones en WITH:\n";
    $query = $entityManager->createQuery('
        SELECT u FROM User u
        INNER JOIN u.posts p WITH p.title IS NOT NULL AND p.content LIKE :content
        ORDER BY u.username ASC
    ');
    $query->setParameter('content', '%contenido%');
    $users = $query->getResult();
    echo "Usuarios con posts que tienen contenido específico: " . count($users) . "\n\n";

    // Ejemplo 7: Usando repositorios personalizados con WITH
    echo "7. Repositorios personalizados con WITH:\n";
    $userRepository = $entityManager->getRepository(User::class);
    
    $usersWithRecentPosts = $userRepository->findUsersWithRecentPosts(7);
    echo "Usuarios con posts recientes (7 días): " . count($usersWithRecentPosts) . "\n";
    
    $usersWithTitlePosts = $userRepository->findUsersWithPostsByTitle('%primer%');
    echo "Usuarios con posts que contienen 'primer': " . count($usersWithTitlePosts) . "\n\n";

    $postRepository = $entityManager->getRepository(Post::class);
    
    $postsByActiveAuthors = $postRepository->findPostsByActiveAuthors();
    echo "Posts de autores activos: " . count($postsByActiveAuthors) . "\n";
    
    $postsByDomain = $postRepository->findPostsWithAuthorDomain('example.com');
    echo "Posts de autores con dominio example.com: " . count($postsByDomain) . "\n\n";

    // Ejemplo 8: Comparación de sintaxis
    echo "8. Comparación de sintaxis:\n";
    echo "Sintaxis tradicional ON:\n";
    echo "SELECT p FROM Post p INNER JOIN User u ON p.author = u.id WHERE u.email LIKE '%@domain.com'\n\n";
    
    echo "Sintaxis WITH (Doctrine-style):\n";
    echo "SELECT p FROM Post p INNER JOIN p.author u WITH u.email LIKE '%@domain.com'\n\n";

    echo "=== Ejemplos de WITH completados ===\n";

} catch (\InvalidArgumentException $e) {
    echo "Validation Error: " . $e->getMessage() . "\n";
} catch (\RuntimeException $e) {
    echo "Runtime Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Unexpected Error: " . $e->getMessage() . "\n";
    error_log($e->getTraceAsString());
}