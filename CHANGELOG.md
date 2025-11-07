# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - 2024-12-19

### Fixed
- Fixed executeInsert() to use getIdentifiers() for composite keys
- Fixed detach() method to use buildIdentityKey() consistently
- Fixed duplicate configuration files
- Added missing symfony/console dependency
- Fixed services.yaml configuration for commands
- Fixed class references in examples
- Corrected Symfony Flex recipe configuration

## [1.0.0] - 2024-12-19

### Added
- Initial release of Sybase ASE ORM Bundle
- Entity mapping with PHP Attributes
- EntityManager with persist(), remove(), flush() methods
- Repository pattern with common methods (find(), findAll(), findBy(), etc.)
- Support for OneToOne, OneToMany, ManyToOne relationships
- OQL (Object Query Language) similar to DQL
- Automatic transaction management
- Basic lazy loading implementation
- Custom repositories with OQL queries
- JOIN support (INNER JOIN, LEFT JOIN) with ON and WITH syntax
- Database URL configuration support
- Query caching for improved performance
- Metadata caching
- Identity Map pattern
- Transaction management with transactional() method
- Entity lifecycle management (clear(), detach())
- Optional query logging
- Comprehensive test suite
- Full documentation and examples

### Features
- **Mapeo de Entidades**: Utiliza PHP Attributes para definir entidades y sus propiedades
- **Entity Manager**: Manejo de persistencia con métodos persist(), remove(), flush()
- **Repositorios**: Patrón Repository con métodos comunes y repositorios personalizados
- **Relaciones**: Soporte para OneToOne, OneToMany, ManyToOne y ManyToMany
- **OQL**: Lenguaje de consulta orientado a objetos similar a DQL
- **JOINs**: Soporte completo para JOINs con sintaxis ON y WITH (estilo Doctrine)
- **Transacciones**: Manejo automático y manual de transacciones
- **Configuración**: Soporte para DATABASE_URL y configuración detallada
- **Optimizaciones**: Cache de metadatos, parsing OQL, Identity Map
- **Testing**: Suite completa de tests unitarios

### Requirements
- PHP 8.1+
- Symfony 6.0+ or 7.0+
- PDO_DBLIB extension
- Sybase ASE 15.0+