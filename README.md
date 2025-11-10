# Sybase ASE ORM Bundle

[![Version](https://img.shields.io/badge/version-1.0.9-blue.svg)](https://github.com/shedeza/sybase-ase-orm-bundle)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%3E%3D8.1-blue.svg)](https://php.net/)
[![Symfony](https://img.shields.io/badge/symfony-%3E%3D6.0-blue.svg)](https://symfony.com/)

Un Bundle de Symfony que implementa un Object-Relational Mapper (ORM) para Sybase ASE.
## Características

- **Mapeo de Entidades**: Utiliza PHP Attributes para definir entidades y sus propiedades
- **Entity Manager**: Manejo de persistencia con métodos `persist()`, `remove()`, `flush()`
- **Repositorios**: Patrón Repository con métodos comunes (`find()`, `findAll()`, `findBy()`, etc.)
- **Relaciones**: Soporte completo para OneToOne, OneToMany, ManyToOne y ManyToMany con cascadas
- **OQL**: Lenguaje de consulta orientado a objetos
- **Transacciones**: Manejo automático de transacciones
- **Lazy Loading**: Carga perezosa de relaciones (implementación básica)

## Instalación

```bash
composer require shedeza/sybase-ase-orm-bundle
```

## Configuración

Agregar en `config/bundles.php`:

```php
return [
    // ...
    Shedeza\SybaseAseOrmBundle\SybaseAseOrmBundle::class => ['all' => true],
];
```

Configurar en `config/packages/sybase_ase_orm.yaml`:

```yaml
sybase_ase_orm:
  default_connection: default
  default_entity_manager: default
  
  connections:
    default: '%env(resolve:DATABASE_SYBASE_URL)%'
  
  entity_managers:
    default:
      connection: default
      mappings:
        App:
          type: attribute
          dir: '%kernel.project_dir%/src/Entity'
          prefix: 'App\Entity'
```

Agregar en tu archivo `.env`:

```env
# Format: sybase://username:password@host:port/database?charset=utf8
DATABASE_SYBASE_URL=sybase://sa:your_password@localhost:5000/mydb?charset=utf8
```

### Configuración Alternativa (Detallada)

También puedes usar la configuración detallada:

```yaml
sybase_ase_orm:
  connections:
    default:
      host: '%env(SYBASE_HOST)%'
      port: '%env(int:SYBASE_PORT)%'
      database: '%env(SYBASE_DATABASE)%'
      username: '%env(SYBASE_USERNAME)%'
      password: '%env(SYBASE_PASSWORD)%'
      charset: '%env(SYBASE_CHARSET)%'
```

## Uso Básico

### Definir una Entidad

#### Entidad con Llave Primaria Simple

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

    // getters y setters...
}
```

#### Entidad con Llave Primaria Compuesta

```php
<?php

namespace App\Entity;

use Shedeza\SybaseAseOrmBundle\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'user_roles')]
class UserRole
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private ?int $userId = null;

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private ?int $roleId = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTime $assignedAt = null;

    // getters y setters...
}
```

### Usar el Entity Manager

```php
<?php

namespace App\Controller;

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
    
    public function findComposite(EntityManager $entityManager): ?UserRole
    {
        // Buscar por llave primaria compuesta
        $compositeId = ['userId' => 1, 'roleId' => 2];
        return $entityManager->find(UserRole::class, $compositeId);
    }
}
```

### Usar Repositorios

#### Repositorio Básico

```php
<?php

$repository = $entityManager->getRepository(User::class);

// Buscar por ID
$user = $repository->find(1);

// Buscar todos
$users = $repository->findAll();

// Buscar con criterios
$users = $repository->findBy(['username' => 'john_doe']);

// Buscar uno con criterios
$user = $repository->findOneBy(['email' => 'john@example.com']);
```

#### Repositorios Personalizados

```php
<?php

namespace App\Repository;

use Shedeza\SybaseAseOrmBundle\ORM\Repository\AbstractRepository;
use App\Entity\User;

class UserRepository extends AbstractRepository
{
    public function findByUsername(string $username): ?User
    {
        $query = $this->createQuery('SELECT u FROM User u WHERE u.username = :username');
        $query->setParameter('username', $username);
        return $query->getSingleResult();
    }

    public function findActiveUsers(): array
    {
        $query = $this->createQuery('SELECT u FROM User u WHERE u.createdAt IS NOT NULL ORDER BY u.createdAt DESC');
        return $query->getResult();
    }
}
```

#### Inyección Directa de Repositorios

```php
<?php

// Los repositorios se pueden inyectar directamente
use App\Repository\UserRepository;

class UserService
{
    public function __construct(
        private UserRepository $userRepository
    ) {}
    
    public function getActiveUsers(): array
    {
        return $this->userRepository->findActiveUsers();
    }
}
```

```php
<?php

namespace App\Entity;

use Shedeza\SybaseAseOrmBundle\ORM\Mapping as ORM;
use App\Repository\UserRepository;

#[ORM\Entity]
#[ORM\Repository(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
class User
{
    // ...
}
```

### Consultas OQL

#### Consultas Básicas

```php
<?php

$query = $entityManager->createQuery('SELECT u FROM User u WHERE u.username = :username');
$query->setParameter('username', 'john_doe');
$users = $query->getResult();
```

#### Consultas con JOINs

##### Sintaxis tradicional con ON

```php
<?php

// INNER JOIN
$query = $entityManager->createQuery('
    SELECT u FROM User u 
    INNER JOIN Post p ON p.author = u.id 
    WHERE p.title LIKE :title
');
$query->setParameter('title', '%post%');
$users = $query->getResult();

// LEFT JOIN
$query = $entityManager->createQuery('
    SELECT u FROM User u 
    LEFT JOIN Post p ON p.author = u.id 
    ORDER BY u.username ASC
');
$users = $query->getResult();
```

##### Sintaxis WITH (estilo Doctrine)

```php
<?php

// JOIN usando asociaciones definidas en entidades
$query = $entityManager->createQuery('
    SELECT u FROM User u 
    INNER JOIN u.posts p
    ORDER BY u.username ASC
');
$users = $query->getResult();

// JOIN con condiciones WITH
$query = $entityManager->createQuery('
    SELECT u FROM User u 
    INNER JOIN u.posts p WITH p.title LIKE :title
    ORDER BY u.username ASC
');
$query->setParameter('title', '%post%');
$users = $query->getResult();

// LEFT JOIN con WITH
$query = $entityManager->createQuery('
    SELECT p FROM Post p
    LEFT JOIN p.author u WITH u.email LIKE :domain
    ORDER BY p.id DESC
');
$query->setParameter('domain', '%@example.com');
$posts = $query->getResult();

// JOINs anidados con WITH
$query = $entityManager->createQuery('
    SELECT p FROM Post p
    INNER JOIN p.author u WITH u.createdAt IS NOT NULL
    LEFT JOIN u.posts p2 WITH p2.id != p.id
    ORDER BY p.id DESC
');
$posts = $query->getResult();
```

### Relaciones

```php
<?php

#[ORM\Entity]
class Post
{
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'posts')]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id')]
    private ?User $author = null;
}

#[ORM\Entity]
class User
{
    #[ORM\OneToMany(targetEntity: Post::class, mappedBy: 'author')]
    private array $posts = [];
}
```

## Atributos Disponibles

### Entidades y Tablas
- `#[ORM\Entity]`: Marca una clase como entidad
- `#[ORM\Repository(repositoryClass: MyRepository::class)]`: Define repositorio personalizado
- `#[ORM\Table(name: 'table_name')]`: Define el nombre de la tabla
- `#[ORM\Index(columns: ['field1', 'field2'])]`: Define índices

### Campos
- `#[ORM\Column(type: 'string', length: 255)]`: Define una columna
- `#[ORM\Id]`: Marca una propiedad como identificador
- `#[ORM\GeneratedValue]`: Define estrategia de generación de ID

### Relaciones
- `#[ORM\OneToOne]`: Relación uno a uno
- `#[ORM\OneToMany]`: Relación uno a muchos
- `#[ORM\ManyToOne]`: Relación muchos a uno
- `#[ORM\ManyToMany]`: Relación muchos a muchos
- `#[ORM\JoinColumn]`: Define columna de unión
- `#[ORM\JoinTable]`: Define tabla de unión para ManyToMany

### Eventos del Ciclo de Vida
- `#[ORM\PrePersist]`: Ejecuta antes de persistir
- `#[ORM\PostPersist]`: Ejecuta después de persistir

## Características Avanzadas

### Manejo de Transacciones

```php
<?php

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

// Transacción automática
$result = $entityManager->transactional(function($em) {
    $em->persist($entity1);
    $em->persist($entity2);
    return $entity1->getId();
});
```

### Gestión del Ciclo de Vida

```php
<?php

// Limpiar todas las entidades del contexto
$entityManager->clear();

// Desconectar una entidad específica
$entityManager->detach($entity);

// Limpiar cache de metadatos
$entityManager->clearMetadataCache();
```

### Logging de Consultas

```php
<?php

use Shedeza\SybaseAseOrmBundle\DBAL\Logger\QueryLogger;

class MyQueryLogger implements QueryLogger
{
    public function logQuery(string $sql, array $params = [], float $executionTime = 0.0): void
    {
        error_log("SQL: {$sql} - Time: {$executionTime}s");
    }
}

$connection = new Connection($config, new MyQueryLogger());
```

## Testing

```bash
# Ejecutar tests
composer test

# O directamente con PHPUnit
vendor/bin/phpunit
```

## Requisitos

- PHP 8.1+
- Symfony 6.0+ o 7.0+
- Extensión PDO_DBLIB (p.ej. `pdo_dblib`) — se requiere un driver DB-lib compatible (FreeTDS)
- Sybase ASE 15.0+ (o una instancia compatible con TDS/dblib)

Nota: la extensión que proporciona el soporte `dblib` puede instalarse mediante el paquete de tu distribución (p. ej. `php-sybase` / `php-pdo-dblib`) o compilando/instalando vía PECL; los nombres de paquetes varían entre distribuciones. A continuación hay ejemplos y recomendaciones.

### Instalación (ejemplos)

En Debian/Ubuntu (ejemplo orientativo):

```bash
# Instalar FreeTDS y herramientas de compilación
sudo apt update
sudo apt install -y freetds-bin freetds-dev build-essential php-dev

# Intentar instalar la extensión PDO_DBLIB desde paquetes si existe
sudo apt install -y php-sybase || true

# Si no hay paquete disponible, puedes intentar vía PECL (requiere php-dev):
# sudo pecl install pdo_dblib
# y luego habilitar la extensión en php.ini: extension=pdo_dblib.so
```

En CentOS/RHEL (ejemplo orientativo):

```bash
sudo yum install -y freetds freetds-devel php-devel make gcc
# Luego instalar la extensión compatible para PHP (paquete o compilación/PECL)
```

Si usas una imagen Docker o contenedor, asegúrate de instalar FreeTDS y la extensión `pdo_dblib` en la imagen base.

### Configurar FreeTDS

FreeTDS usa `/etc/freetds/freetds.conf` para definir versiones TDS y hosts. Un ejemplo mínimo:

```ini
[sybase-server]
    host = my-sybase-host.example
    port = 5000
    tds version = 5.0
```

En muchos entornos la URL `DATABASE_SYBASE_URL` es suficiente (ver más abajo), pero si tienes problemas de conectividad revisa `freetds.conf` y variable de entorno `TDSVER`.

### Ejemplo de `DATABASE_SYBASE_URL` (.env)

La librería soporta una URL en el formato:

```
sybase://username:password@host:port/database?charset=utf8
```

Ejemplo real en `.env`:

```env
# Format: sybase://username:password@host:port/database?charset=utf8
DATABASE_SYBASE_URL=sybase://sa:SuperSecret@db.example.com:5000/my_database?charset=utf8
```

Si usas la configuración detallada en lugar de la URL, asegúrate de definir `host`, `port`, `database`, `username` y `password` en la sección `connections` de tu `config/packages/sybase_ase_orm.yaml`.

### Solución de problemas comunes

- Error de conexión / timeout: verifica que `freetds-bin` pueda hacer telnet/tsql al host/puerto.
- Extensión PDO_DBLIB no encontrada: asegúrate de que la extensión esté instalada y habilitada (comprueba `php -m | grep dblib` o `php -i`).
- `lastInsertId()` no devuelve valor esperado: algunos backends dblib tienen comportamientos distintos; prueba alternativas del servidor o consulta la documentación de FreeTDS/Sybase.

Si necesitas ayuda con un error específico, adjunta la salida de los logs y el mensaje de error y te ayudo a diagnosticarlo.

## Optimizaciones Incluidas

- **Cache de metadatos**: Los metadatos de entidades se cachean automáticamente
- **Cache de parsing OQL**: Las consultas parseadas se cachean para mejor rendimiento
- **Lazy loading**: Carga perezosa de relaciones (implementación básica)
- **Identity Map**: Previene duplicación de objetos en memoria
- **Transacciones optimizadas**: Solo inicia transacciones cuando hay cambios pendientes

## Contribuir

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/amazing-feature`)
3. Commit tus cambios (`git commit -m 'Add amazing feature'`)
4. Push a la rama (`git push origin feature/amazing-feature`)
5. Abre un Pull Request

## Licencia

MIT