# Referencia de API

## EntityManager

### Métodos Principales

#### `persist(object $entity): void`
Programa una entidad para persistencia.

```php
$user = new User();
$entityManager->persist($user);
```

#### `remove(object $entity): void`
Programa una entidad para eliminación.

```php
$entityManager->remove($user);
```

#### `flush(): void`
Ejecuta todas las operaciones pendientes.

```php
$entityManager->flush();
```

#### `find(string $className, mixed $id): ?object`
Busca una entidad por su identificador.

```php
$user = $entityManager->find(User::class, 1);
$userRole = $entityManager->find(UserRole::class, ['userId' => 1, 'roleId' => 2]);
```

#### `getRepository(string $className): AbstractRepository`
Obtiene el repositorio de una entidad.

```php
$repository = $entityManager->getRepository(User::class);
```

#### `createQuery(string $oql): Query`
Crea una consulta OQL.

```php
$query = $entityManager->createQuery('SELECT u FROM User u WHERE u.active = :active');
```

#### `transactional(callable $func): mixed`
Ejecuta una función dentro de una transacción.

```php
$result = $entityManager->transactional(function($em) {
    // Operaciones transaccionales
    return $someValue;
});
```

### Métodos de Gestión

#### `clear(): void`
Limpia todas las entidades gestionadas.

#### `detach(object $entity): void`
Desconecta una entidad del contexto.

#### `refresh(object $entity): void`
Refresca una entidad desde la base de datos.

#### `contains(object $entity): bool`
Verifica si una entidad está gestionada.

#### `getReference(string $className, mixed $id): object`
Obtiene una referencia (proxy) a una entidad.

## AbstractRepository

### Métodos Básicos

#### `find(mixed $id): ?object`
Busca una entidad por ID.

#### `findAll(): array`
Obtiene todas las entidades.

#### `findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array`
Busca entidades con criterios.

#### `findOneBy(array $criteria): ?object`
Busca una entidad con criterios.

#### `count(array $criteria = []): int`
Cuenta entidades.

### Métodos Avanzados (EntityRepository)

#### `exists(array $criteria): bool`
Verifica si existe una entidad.

#### `findPaginated(int $page, int $pageSize, array $criteria = [], ?array $orderBy = null): array`
Búsqueda paginada.

#### `findByIds(array $ids): array`
Busca múltiples entidades por IDs.

#### `executeQuery(string $oql, array $parameters = []): array`
Ejecuta consulta OQL personalizada.

#### `fullTextSearch(string $searchTerm, array $fields, array $options = []): array`
Búsqueda de texto completo.

#### `findByDateRange(string $dateField, \DateTime $startDate, \DateTime $endDate, array $additionalCriteria = []): array`
Búsqueda por rango de fechas.

#### `aggregate(string $aggregateFunction, string $field, array $criteria = []): mixed`
Funciones de agregación.

#### `validateEntity(object $entity): bool`
Valida una entidad.

#### `getStatistics(): array`
Obtiene estadísticas del repositorio.

## Query

### Métodos de Configuración

#### `setParameter(string $name, mixed $value): self`
Establece un parámetro.

```php
$query->setParameter('active', true);
```

### Métodos de Ejecución

#### `getResult(): array`
Obtiene todos los resultados.

#### `getSingleResult(): ?object`
Obtiene un único resultado.

#### `getSingleScalarResult(): mixed`
Obtiene un valor escalar.

## Atributos de Mapeo

### Entidades

#### `#[ORM\Entity]`
Marca una clase como entidad.

#### `#[ORM\Repository(repositoryClass: MyRepository::class)]`
Define repositorio personalizado.

#### `#[ORM\Table(name: 'table_name', schema: 'schema_name')]`
Configuración de tabla.

### Campos

#### `#[ORM\Column(type: 'string', length: 255, nullable: false)]`
Define una columna.

**Tipos soportados:**
- `string` - Cadenas de texto
- `integer`, `int` - Números enteros
- `float`, `decimal` - Números decimales
- `boolean`, `bool` - Valores booleanos
- `datetime` - Fechas y horas
- `date` - Solo fechas
- `time` - Solo horas
- `text` - Texto largo
- `blob` - Datos binarios

#### `#[ORM\Id]`
Marca como identificador.

#### `#[ORM\GeneratedValue(strategy: 'IDENTITY')]`
Estrategia de generación de ID.

### Relaciones

#### `#[ORM\OneToOne(targetEntity: Profile::class, mappedBy: 'user')]`
Relación uno a uno.

#### `#[ORM\OneToMany(targetEntity: Post::class, mappedBy: 'author')]`
Relación uno a muchos.

#### `#[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'posts')]`
Relación muchos a uno.

#### `#[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'posts')]`
Relación muchos a muchos.

#### `#[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id')]`
Configuración de columna de unión.

#### `#[ORM\JoinTable(name: 'user_roles', joinColumns: [...], inverseJoinColumns: [...])]`
Configuración de tabla de unión.

### Eventos de Ciclo de Vida

#### `#[ORM\PrePersist]`
Ejecuta antes de persistir.

#### `#[ORM\PostPersist]`
Ejecuta después de persistir.

## Connection

### Métodos de Consulta

#### `executeQuery(string $sql, array $params = []): \PDOStatement`
Ejecuta una consulta SELECT.

#### `executeUpdate(string $sql, array $params = []): int`
Ejecuta INSERT, UPDATE o DELETE.

### Métodos de Transacción

#### `beginTransaction(): bool`
Inicia una transacción.

#### `commit(): bool`
Confirma una transacción.

#### `rollback(): bool`
Revierte una transacción.

#### `lastInsertId(): string`
Obtiene el último ID insertado.

## UnitOfWork

### Estados de Entidad

- `UnitOfWork::STATE_MANAGED` - Entidad gestionada
- `UnitOfWork::STATE_NEW` - Entidad nueva
- `UnitOfWork::STATE_DETACHED` - Entidad desconectada
- `UnitOfWork::STATE_REMOVED` - Entidad marcada para eliminación

### Métodos Principales

#### `persist(object $entity): void`
Programa entidad para persistencia.

#### `remove(object $entity): void`
Programa entidad para eliminación.

#### `commit(): void`
Ejecuta todas las operaciones pendientes.

#### `clear(): void`
Limpia todos los estados.

## Excepciones

### `ORMException`
Excepción base del ORM.

#### Métodos Estáticos

- `ORMException::entityNotFound(string $className, mixed $id)`
- `ORMException::invalidEntity(string $className)`
- `ORMException::metadataNotFound(string $className)`

## Configuración

### Estructura de Configuración

```yaml
sybase_ase_orm:
  default_connection: string
  default_entity_manager: string
  
  connections:
    connection_name:
      # Opción 1: URL de conexión
      url: string
      
      # Opción 2: Configuración detallada
      host: string
      port: integer
      database: string
      username: string
      password: string
      charset: string
  
  entity_managers:
    manager_name:
      connection: string
      enable_metadata_cache: boolean
      mappings:
        namespace:
          type: string # 'attribute'
          dir: string
          prefix: string
```

### Opciones de Configuración

#### Conexiones
- `url` - URL de conexión completa
- `host` - Servidor de base de datos
- `port` - Puerto (por defecto: 5000)
- `database` - Nombre de la base de datos
- `username` - Usuario de conexión
- `password` - Contraseña
- `charset` - Codificación (por defecto: utf8)

#### Entity Managers
- `connection` - Nombre de la conexión a usar
- `enable_metadata_cache` - Habilitar cache de metadatos
- `mappings` - Configuración de mapeos de entidades

#### Mapeos
- `type` - Tipo de mapeo ('attribute')
- `dir` - Directorio de entidades
- `prefix` - Prefijo de namespace

## Comandos de Consola

### `sybase:orm:validate-schema`
Valida el esquema de entidades.

```bash
# Validar todas las entidades
php bin/console sybase:orm:validate-schema

# Validar entidad específica
php bin/console sybase:orm:validate-schema App\\Entity\\User
```

## Interfaces

### `RepositoryInterface`
Interfaz base para repositorios.

```php
interface RepositoryInterface
{
    public function find(mixed $id): ?object;
    public function findAll(): array;
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;
    public function findOneBy(array $criteria): ?object;
    public function count(array $criteria = []): int;
}
```

### `QueryLogger`
Interfaz para logging de consultas.

```php
interface QueryLogger
{
    public function logQuery(string $sql, array $params = [], float $executionTime = 0.0): void;
}
```

## Constantes

### GeneratedValue Strategies
- `GeneratedValue::IDENTITY` - Auto-incremento
- `GeneratedValue::SEQUENCE` - Secuencia
- `GeneratedValue::TABLE` - Tabla de secuencias
- `GeneratedValue::NONE` - Sin generación automática

### Fetch Types
- `ManyToOne::FETCH_LAZY` - Carga perezosa
- `ManyToOne::FETCH_EAGER` - Carga inmediata

Esta referencia de API cubre todos los métodos y clases principales del Sybase ASE ORM Bundle.