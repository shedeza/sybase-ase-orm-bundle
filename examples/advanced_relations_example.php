<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Shedeza\SybaseAseOrmBundle\DBAL\Connection;
use Shedeza\SybaseAseOrmBundle\DBAL\DatabaseUrlParser;
use Shedeza\SybaseAseOrmBundle\ORM\EntityManager;
use Shedeza\SybaseAseOrmBundle\ORM\Mapping as ORM;

// Ejemplo de entidad con relación ManyToMany
#[ORM\Entity]
#[ORM\Table(name: 'categories')]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: ORM\GeneratedValue::IDENTITY)]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $name = null;

    #[ORM\ManyToMany(targetEntity: \Shedeza\SybaseAseOrmBundle\Entity\Post::class, mappedBy: 'categories')]
    private array $posts = [];

    public function getId(): ?int { return $this->id; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getPosts(): array { return $this->posts; }
}

// Configuración de conexión
$databaseUrl = $_ENV['DATABASE_SYBASE_URL'] ?? 'sybase://sa:password@localhost:5000/testdb?charset=utf8';
$config = DatabaseUrlParser::parseUrl($databaseUrl);

try {
    $connection = new Connection($config);
    $entityManager = new EntityManager($connection);

    echo "=== Ejemplo de Relaciones Avanzadas ===\n\n";

    // Ejemplo de validación de esquema
    echo "1. Validación de esquema:\n";
    $validator = new \Shedeza\SybaseAseOrmBundle\ORM\Tools\SchemaValidator($entityManager);
    
    $userErrors = $validator->validateEntity(\Shedeza\SybaseAseOrmBundle\Entity\User::class);
    if (empty($userErrors)) {
        echo "✓ Entidad User válida\n";
    } else {
        echo "✗ Errores en User: " . implode(', ', $userErrors) . "\n";
    }

    $postErrors = $validator->validateEntity(\Shedeza\SybaseAseOrmBundle\Entity\Post::class);
    if (empty($postErrors)) {
        echo "✓ Entidad Post válida\n";
    } else {
        echo "✗ Errores en Post: " . implode(', ', $postErrors) . "\n";
    }

    echo "\n2. Ejemplo de cascadas:\n";
    
    // Crear usuario con posts en cascada
    $user = new \Shedeza\SybaseAseOrmBundle\Entity\User();
    $user->setUsername('cascade_user');
    $user->setEmail('cascade@example.com');
    $user->setCreatedAt(new DateTime());

    $post1 = new \Shedeza\SybaseAseOrmBundle\Entity\Post();
    $post1->setTitle('Post con cascada 1');
    $post1->setContent('Contenido del post 1');
    $post1->setAuthor($user);

    $post2 = new \Shedeza\SybaseAseOrmBundle\Entity\Post();
    $post2->setTitle('Post con cascada 2');
    $post2->setContent('Contenido del post 2');
    $post2->setAuthor($user);

    $user->addPost($post1);
    $user->addPost($post2);

    // Solo persistir el usuario, los posts se persisten en cascada
    $entityManager->persist($user);
    $entityManager->flush();

    echo "Usuario y posts creados con cascada\n";

    echo "\n3. Gestión avanzada del ciclo de vida:\n";
    
    // Limpiar contexto
    $entityManager->clear();
    echo "Contexto limpiado\n";
    
    // Desconectar entidad específica
    $entityManager->detach($user);
    echo "Usuario desconectado\n";

    echo "\n=== Ejemplo completado ===\n";

} catch (\InvalidArgumentException $e) {
    echo "Validation Error: " . $e->getMessage() . "\n";
} catch (\RuntimeException $e) {
    echo "Runtime Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Unexpected Error: " . $e->getMessage() . "\n";
    error_log($e->getTraceAsString());
}