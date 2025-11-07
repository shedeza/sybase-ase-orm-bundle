<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Shedeza\SybaseAseOrmBundle\DBAL\Connection;
use Shedeza\SybaseAseOrmBundle\ORM\EntityManager;
use Shedeza\SybaseAseOrmBundle\Entity\User;
use Shedeza\SybaseAseOrmBundle\Entity\Post;

// Configuración de conexión usando DATABASE_URL
use Shedeza\SybaseAseOrmBundle\DBAL\DatabaseUrlParser;

$databaseUrl = $_ENV['SYBASE_DATABASE_URL'] ?? 'sybase://sa:password@localhost:5000/testdb?charset=utf8';
$config = DatabaseUrlParser::parseUrl($databaseUrl);

try {
    // Crear conexión y EntityManager
    $connection = new Connection($config);
    $entityManager = new EntityManager($connection);

    // Ejemplo 1: Crear un nuevo usuario
    echo "=== Creando nuevo usuario ===\n";
    $user = new User();
    $user->setUsername('john_doe');
    $user->setEmail('john@example.com');
    $user->setCreatedAt(new DateTime());

    $entityManager->persist($user);
    $entityManager->flush();

    echo "Usuario creado con ID: " . $user->getId() . "\n\n";

    // Ejemplo 2: Buscar usuario por ID
    echo "=== Buscando usuario por ID ===\n";
    $foundUser = $entityManager->find(User::class, $user->getId());
    if ($foundUser) {
        echo "Usuario encontrado: " . $foundUser->getUsername() . "\n\n";
    }

    // Ejemplo 3: Usar repositorio personalizado
    echo "=== Usando repositorio personalizado ===\n";
    $userRepository = $entityManager->getRepository(User::class);
    
    $allUsers = $userRepository->findAll();
    echo "Total de usuarios: " . count($allUsers) . "\n";
    
    // Usar métodos personalizados del repositorio
    $userByUsername = $userRepository->findByUsername('john_doe');
    if ($userByUsername) {
        echo "Usuario por username: " . $userByUsername->getEmail() . "\n";
    }
    
    $userByEmail = $userRepository->findByEmail('john@example.com');
    if ($userByEmail) {
        echo "Usuario por email: " . $userByEmail->getUsername() . "\n";
    }
    
    $activeUsers = $userRepository->findActiveUsers();
    echo "Usuarios activos: " . count($activeUsers) . "\n\n";

    // Ejemplo 4: Crear post relacionado
    echo "=== Creando post relacionado ===\n";
    $post = new Post();
    $post->setTitle('Mi primer post');
    $post->setContent('Este es el contenido del post');
    $post->setAuthor($user);

    $entityManager->persist($post);
    $entityManager->flush();

    echo "Post creado con ID: " . $post->getId() . "\n\n";
    
    // Ejemplo 4.1: Usar repositorio personalizado de Post
    echo "=== Usando repositorio personalizado de Post ===\n";
    $postRepository = $entityManager->getRepository(Post::class);
    
    $postsByAuthor = $postRepository->findByAuthor($user);
    echo "Posts del usuario: " . count($postsByAuthor) . "\n";
    
    $postsByUsername = $postRepository->findByAuthorUsername('john_doe');
    echo "Posts por username: " . count($postsByUsername) . "\n";
    
    $latestPosts = $postRepository->findLatestPosts(5);
    echo "Últimos 5 posts: " . count($latestPosts) . "\n";
    
    $postCount = $postRepository->countPostsByAuthor($user);
    echo "Total posts del autor: " . $postCount . "\n\n";

    // Ejemplo 5: Consultas OQL personalizadas con JOINs
    echo "=== Consultas OQL personalizadas con JOINs ===\n";
    
    // Consulta directa con EntityManager
    $query = $entityManager->createQuery('SELECT u FROM User u WHERE u.username = :username');
    $query->setParameter('username', 'john_doe');
    $users = $query->getResult();
    
    foreach ($users as $u) {
        echo "Usuario encontrado via OQL: " . $u->getUsername() . "\n";
    }
    
    // Consulta con INNER JOIN tradicional
    $query = $entityManager->createQuery('
        SELECT u FROM User u 
        INNER JOIN Post p ON p.author = u.id 
        WHERE p.title LIKE :title
    ');
    $query->setParameter('title', '%primer%');
    $usersWithPosts = $query->getResult();
    echo "Usuarios con posts que contienen 'primer' (ON): " . count($usersWithPosts) . "\n";
    
    // Consulta con JOIN usando sintaxis WITH
    $query = $entityManager->createQuery('
        SELECT u FROM User u 
        INNER JOIN u.posts p WITH p.title LIKE :title2
    ');
    $query->setParameter('title2', '%primer%');
    $usersWithPostsWITH = $query->getResult();
    echo "Usuarios con posts que contienen 'primer' (WITH): " . count($usersWithPostsWITH) . "\n";
    
    // Consultas personalizadas del repositorio
    $recentUsers = $userRepository->findRecentUsers(7);
    echo "Usuarios recientes (7 días): " . count($recentUsers) . "\n";
    
    $domainCount = $userRepository->countUsersByDomain('example.com');
    echo "Usuarios con dominio example.com: " . $domainCount . "\n";
    
    $postsWithContent = $postRepository->findPostsWithContent('primer');
    echo "Posts que contienen 'primer': " . count($postsWithContent) . "\n";
    
    // Consultas con JOINs anidados
    $postsWithAuthor = $postRepository->findPostsWithAuthorInfo();
    echo "Posts con información del autor: " . count($postsWithAuthor) . "\n";
    
    $postsNested = $postRepository->findPostsWithNestedJoin();
    echo "Posts con JOINs anidados: " . count($postsNested) . "\n";

    // Ejemplo 6: Más consultas personalizadas
    echo "\n=== Más consultas personalizadas ===\n";
    $usersWithPosts = $userRepository->findUsersWithPosts();
    echo "Usuarios con posts: " . count($usersWithPosts) . "\n";
    
    $postByTitle = $postRepository->findByTitle('Mi primer post');
    if ($postByTitle) {
        echo "Post encontrado por título: " . $postByTitle->getContent() . "\n";
    }
    
    // Ejemplo 7: Actualizar usuario
    echo "\n=== Actualizando usuario ===\n";
    $user->setEmail('john.doe@newdomain.com');
    $entityManager->persist($user);
    $entityManager->flush();

    echo "Email actualizado a: " . $user->getEmail() . "\n\n";

    // Ejemplo 8: Eliminar post
    echo "=== Eliminando post ===\n";
    $entityManager->remove($post);
    $entityManager->flush();

    echo "Post eliminado\n\n";

    // Ejemplo 9: Usando transacciones
    echo "\n=== Usando transacciones ===\n";
    $result = $entityManager->transactional(function($em) {
        $newUser = new User();
        $newUser->setUsername('transactional_user');
        $newUser->setEmail('trans@example.com');
        $newUser->setCreatedAt(new DateTime());
        
        $em->persist($newUser);
        return $newUser->getId();
    });
    echo "Usuario creado en transacción con ID: " . $result . "\n";
    
    // Ejemplo 10: Gestión del ciclo de vida
    echo "\n=== Gestión del ciclo de vida ===\n";
    $entityManager->detach($user);
    echo "Usuario desconectado del contexto\n";
    
    $entityManager->clearMetadataCache();
    echo "Cache de metadatos limpiado\n";
    
    echo "\n=== Ejemplos completados exitosamente ===\n";

} catch (\InvalidArgumentException $e) {
    echo "Validation Error: " . $e->getMessage() . "\n";
} catch (\RuntimeException $e) {
    echo "Runtime Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Unexpected Error: " . $e->getMessage() . "\n";
    error_log($e->getTraceAsString());
}