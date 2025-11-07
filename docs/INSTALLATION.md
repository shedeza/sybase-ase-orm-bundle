# Guía de Instalación

## Requisitos del Sistema

- **PHP**: 8.1 o superior
- **Symfony**: 6.0+ o 7.0+
- **Sybase ASE**: 15.0 o superior
- **Extensiones PHP**:
  - `pdo`
  - `pdo_dblib`

## Verificar Requisitos

```bash
# Verificar versión de PHP
php --version

# Verificar extensiones
php -m | grep pdo
php -m | grep pdo_dblib

# Si no está instalada la extensión
sudo apt-get install php-sybase  # Ubuntu/Debian
sudo yum install php-pdo_dblib   # CentOS/RHEL
```

## Instalación

### 1. Instalar vía Composer

```bash
composer require shedeza/sybase-ase-orm-bundle
```

### 2. Registro del Bundle (Symfony Flex)

Con Symfony Flex, el bundle se registra automáticamente.

### 3. Registro Manual (sin Flex)

```php
// config/bundles.php
return [
    // ...
    Shedeza\SybaseAseOrmBundle\SybaseAseOrmBundle::class => ['all' => true],
];
```

### 4. Configuración

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

### 5. Variables de Entorno

```env
# .env
DATABASE_SYBASE_URL=sybase://username:password@host:port/database?charset=utf8
```

## Verificar Instalación

```bash
# Listar comandos disponibles
php bin/console list sybase

# Validar configuración
php bin/console sybase:orm:validate-schema
```

## Configuración Avanzada

### Múltiples Conexiones

```yaml
sybase_ase_orm:
  connections:
    default:
      host: localhost
      port: 5000
      database: main_db
      username: main_user
      password: main_pass
    
    reports:
      host: reports.server.com
      port: 5000
      database: reports_db
      username: reports_user
      password: reports_pass
  
  entity_managers:
    default:
      connection: default
      mappings:
        App:
          type: attribute
          dir: '%kernel.project_dir%/src/Entity'
          prefix: 'App\Entity'
    
    reports:
      connection: reports
      mappings:
        App\Reports:
          type: attribute
          dir: '%kernel.project_dir%/src/Entity/Reports'
          prefix: 'App\Entity\Reports'
```

### Configuración de Desarrollo

```yaml
# config/packages/dev/sybase_ase_orm.yaml
sybase_ase_orm:
  entity_managers:
    default:
      enable_metadata_cache: false
```

### Configuración de Producción

```yaml
# config/packages/prod/sybase_ase_orm.yaml
sybase_ase_orm:
  entity_managers:
    default:
      enable_metadata_cache: true
```

## Troubleshooting

### Error: "Extension pdo_dblib not found"

```bash
# Ubuntu/Debian
sudo apt-get install php-sybase php-dev
sudo service apache2 restart

# CentOS/RHEL
sudo yum install php-pdo_dblib
sudo systemctl restart httpd
```

### Error: "Connection refused"

- Verificar que Sybase ASE esté ejecutándose
- Comprobar firewall y puertos
- Validar credenciales de conexión

### Error: "Class not found"

- Verificar autoload de Composer: `composer dump-autoload`
- Comprobar namespace en configuración
- Validar estructura de directorios