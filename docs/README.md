# Sybase ASE ORM Bundle - Documentación Completa

[![Version](https://img.shields.io/badge/version-1.0.3-blue.svg)](https://github.com/shedeza/sybase-ase-orm-bundle)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%3E%3D8.1-blue.svg)](https://php.net/)
[![Symfony](https://img.shields.io/badge/symfony-%3E%3D6.0-blue.svg)](https://symfony.com/)

## Introducción

El **Sybase ASE ORM Bundle** es un Object-Relational Mapper completo para Symfony que permite trabajar con bases de datos Sybase ASE.

### Características Principales

- **Mapeo basado en atributos PHP 8**
- **Soporte completo para relaciones**
- **Lenguaje de consulta OQL**
- **Lazy loading con proxies**
- **Transacciones robustas**
- **Cache de metadatos**
- **Claves primarias compuestas**

## Instalación

```bash
composer require shedeza/sybase-ase-orm-bundle
```

## Configuración Básica

```yaml
# config/packages/sybase_ase_orm.yaml
sybase_ase_orm:
  connections:
    default: '%env(DATABASE_SYBASE_URL)%'
  entity_managers:
    default:
      connection: default
      mappings:
        App:
          type: attribute
          dir: '%kernel.project_dir%/src/Entity'
          prefix: 'App\Entity'
```

```env
# .env
DATABASE_SYBASE_URL=sybase://username:password@host:port/database?charset=utf8
```

## Definición de Entidades

```php
<?php

namespace App\Entity;

use Shedeza\SybaseAseOrmBundle\ORM\Mapping as ORM;

#[ORM\Entity]
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

    // Getters y Setters...
}
```

## Uso del EntityManager

```php
<?php

use Shedeza\SybaseAseOrmBundle\ORM\EntityManager;
use App\Entity\User;

class UserController
{
    public function create(EntityManager $entityManager): void
    {
        $user = new User();
        $user->setUsername('john_doe');
        $user->setEmail('john@example.com');

        $entityManager->persist($user);
        $entityManager->flush();
    }

    public function find(EntityManager $entityManager, int $id): ?User
    {
        return $entityManager->find(User::class, $id);
    }
}
```

## Repositorios

```php
<?php

// Repositorio básico
$repository = $entityManager->getRepository(User::class);
$users = $repository->findAll();
$user = $repository->find(1);
$activeUsers = $repository->findBy(['active' => true]);

// Repositorio personalizado
namespace App\Repository;

use Shedeza\SybaseAseOrmBundle\ORM\Repository\AbstractRepository;

class UserRepository extends AbstractRepository
{
    public function findActiveUsers(): array
    {
        return $this->findBy(['active' => true], ['createdAt' => 'DESC']);
    }
    
    public function findByEmailDomain(string $domain): array
    {
        $query = $this->createQuery('
            SELECT u FROM User u 
            WHERE u.email LIKE :domain
        ');
        $query->setParameter('domain', '%@' . $domain);
        return $query->getResult();
    }
}
```

## Consultas OQL

```php
<?php

// Consulta básica
$query = $entityManager->createQuery('SELECT u FROM User u WHERE u.active = :active');
$query->setParameter('active', true);
$users = $query->getResult();

// JOINs con sintaxis WITH
$query = $entityManager->createQuery('
    SELECT u FROM User u 
    INNER JOIN u.posts p WITH p.published = :published
    ORDER BY u.username ASC
');
$query->setParameter('published', true);
$users = $query->getResult();

// JOINs con sintaxis ON
$query = $entityManager->createQuery('
    SELECT u FROM User u 
    INNER JOIN Post p ON p.authorId = u.id 
    WHERE p.title LIKE :title
');
$query->setParameter('title', '%tutorial%');
$users = $query->getResult();
```

## Relaciones

### OneToMany / ManyToOne

```php
<?php

// Post.php
#[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'posts')]
#[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id')]
private ?User $author = null;

// User.php
#[ORM\OneToMany(targetEntity: Post::class, mappedBy: 'author')]
private array $posts = [];
```

### ManyToMany

```php
<?php

// Post.php
#[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'posts')]
#[ORM\JoinTable(
    name: 'post_tags',
    joinColumns: [['name' => 'post_id', 'referencedColumnName' => 'id']],
    inverseJoinColumns: [['name' => 'tag_id', 'referencedColumnName' => 'id']]
)]
private array $tags = [];
```

## Transacciones

```php
<?php

// Transacción automática
$result = $entityManager->transactional(function($em) {
    $user = new User();
    $user->setUsername('transactional_user');
    $em->persist($user);
    return $user->getId();
});

// Transacción manual
$entityManager->getConnection()->beginTransaction();
try {
    $entityManager->persist($entity1);
    $entityManager->persist($entity2);
    $entityManager->flush();
    $entityManager->getConnection()->commit();
} catch (\Exception $e) {
    $entityManager->getConnection()->rollback();
    throw $e;
}
```

## Eventos de Ciclo de Vida

```php
<?php

#[ORM\Entity]
class AuditableEntity
{
    #[ORM\Column(type: 'datetime')]
    private ?\DateTime $createdAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
    }

    #[ORM\PostPersist]
    public function onPostPersist(): void
    {
        error_log("Entity persisted with ID {$this->id}");
    }
}
```

## Comandos de Consola

```bash
# Validar esquema
php bin/console sybase:orm:validate-schema

# Validar entidad específica
php bin/console sybase:orm:validate-schema App\\Entity\\User
```

## Testing

```php
<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;
use Shedeza\SybaseAseOrmBundle\ORM\EntityManager;

class UserRepositoryTest extends TestCase
{
    private EntityManager $entityManager;
    
    public function testCreateUser(): void
    {
        $user = new User();
        $user->setUsername('test_user');
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        
        $this->assertNotNull($user->getId());
    }
}
```

## Características Avanzadas

### Lazy Loading
```php
// Obtener referencia sin cargar
$userReference = $entityManager->getReference(User::class, 1);
```

### Cache de Metadatos
```php
// Limpiar cache
$entityManager->clearMetadataCache();
```

### Claves Compuestas
```php
// Buscar por clave compuesta
$compositeId = ['userId' => 1, 'roleId' => 2];
$userRole = $entityManager->find(UserRole::class, $compositeId);
```

## Troubleshooting

### Error de Conexión
- Verificar que Sybase ASE esté ejecutándose
- Comprobar credenciales y configuración
- Verificar extensión PDO_DBLIB

### Problemas de Rendimiento
- Habilitar cache de metadatos
- Usar lazy loading
- Optimizar consultas OQL
- Implementar paginación

## Licencia

MIT License. Ver [LICENSE](LICENSE) para más detalles.

---

Para documentación completa, ejemplos y guías avanzadas, visita el [repositorio en GitHub](https://github.com/shedeza/sybase-ase-orm-bundle).