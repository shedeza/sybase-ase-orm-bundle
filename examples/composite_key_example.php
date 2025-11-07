<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Shedeza\SybaseAseOrmBundle\DBAL\Connection;
use Shedeza\SybaseAseOrmBundle\DBAL\DatabaseUrlParser;
use Shedeza\SybaseAseOrmBundle\ORM\EntityManager;
use Shedeza\SybaseAseOrmBundle\ORM\Mapping as ORM;

// Ejemplo de entidad con llave primaria compuesta
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

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $assignedBy = null;

    public function getUserId(): ?int { return $this->userId; }
    public function setUserId(int $userId): self { $this->userId = $userId; return $this; }
    
    public function getRoleId(): ?int { return $this->roleId; }
    public function setRoleId(int $roleId): self { $this->roleId = $roleId; return $this; }
    
    public function getAssignedAt(): ?\DateTime { return $this->assignedAt; }
    public function setAssignedAt(\DateTime $assignedAt): self { $this->assignedAt = $assignedAt; return $this; }
    
    public function getAssignedBy(): ?string { return $this->assignedBy; }
    public function setAssignedBy(?string $assignedBy): self { $this->assignedBy = $assignedBy; return $this; }
}

// Configuración de conexión
$databaseUrl = $_ENV['DATABASE_SYBASE_URL'] ?? 'sybase://sa:password@localhost:5000/testdb?charset=utf8';
$config = DatabaseUrlParser::parseUrl($databaseUrl);

try {
    $connection = new Connection($config);
    $entityManager = new EntityManager($connection);

    echo "=== Ejemplo de Llaves Primarias Compuestas ===\n\n";

    // Ejemplo 1: Crear entidad con llave compuesta
    echo "1. Creando entidad con llave primaria compuesta:\n";
    $userRole = new UserRole();
    $userRole->setUserId(1);
    $userRole->setRoleId(2);
    $userRole->setAssignedAt(new DateTime());
    $userRole->setAssignedBy('admin');

    $entityManager->persist($userRole);
    $entityManager->flush();

    echo "UserRole creado con llave compuesta (userId: 1, roleId: 2)\n\n";

    // Ejemplo 2: Buscar por llave compuesta
    echo "2. Buscando por llave primaria compuesta:\n";
    $compositeId = ['userId' => 1, 'roleId' => 2];
    $foundUserRole = $entityManager->find(UserRole::class, $compositeId);

    if ($foundUserRole) {
        echo "UserRole encontrado: userId={$foundUserRole->getUserId()}, roleId={$foundUserRole->getRoleId()}\n";
        echo "Asignado por: {$foundUserRole->getAssignedBy()}\n";
    } else {
        echo "UserRole no encontrado\n";
    }

    echo "\n3. Validando metadatos de llave compuesta:\n";
    $metadata = $entityManager->getClassMetadata(UserRole::class);
    
    echo "Identificadores: " . implode(', ', $metadata->getIdentifiers()) . "\n";
    echo "¿Tiene llave compuesta?: " . ($metadata->hasCompositeId() ? 'Sí' : 'No') . "\n";

    // Ejemplo 4: Actualizar entidad con llave compuesta
    echo "\n4. Actualizando entidad con llave compuesta:\n";
    if ($foundUserRole) {
        $foundUserRole->setAssignedBy('super_admin');
        $entityManager->persist($foundUserRole);
        $entityManager->flush();
        echo "UserRole actualizado\n";
    }

    // Ejemplo 5: Eliminar entidad con llave compuesta
    echo "\n5. Eliminando entidad con llave compuesta:\n";
    if ($foundUserRole) {
        $entityManager->remove($foundUserRole);
        $entityManager->flush();
        echo "UserRole eliminado\n";
    }

    // Ejemplo 6: Validar esquema
    echo "\n6. Validando esquema:\n";
    $validator = new \Shedeza\SybaseAseOrmBundle\ORM\Tools\SchemaValidator($entityManager);
    $errors = $validator->validateEntity(UserRole::class);
    
    if (empty($errors)) {
        echo "✓ Esquema de UserRole válido\n";
    } else {
        echo "✗ Errores en esquema:\n";
        foreach ($errors as $error) {
            echo "  - {$error}\n";
        }
    }

    echo "\n=== Ejemplo completado ===\n";

} catch (\InvalidArgumentException $e) {
    echo "Validation Error: " . $e->getMessage() . "\n";
} catch (\RuntimeException $e) {
    echo "Runtime Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Unexpected Error: " . $e->getMessage() . "\n";
    error_log($e->getTraceAsString());
}