# Symfony Flex Recipe for Sybase ASE ORM Bundle

This directory contains the Symfony Flex recipe for automatic installation and configuration of the Sybase ASE ORM Bundle.

## Recipe Structure

```
├── manifest.json           # Flex recipe configuration
├── config/
│   └── packages/
│       └── sybase_ase_orm.yaml  # Default bundle configuration
├── post-install.txt        # Post-installation message
└── symfony.lock            # Lock file for recipe versioning
```

## Installation

When users install the bundle via Composer with Symfony Flex:

```bash
composer require shedeza/sybase-ase-orm-bundle
```

Flex will automatically:

1. **Register the bundle** in `config/bundles.php`
2. **Copy configuration** to `config/packages/sybase_ase_orm.yaml`
3. **Add environment variable** `SYBASE_DATABASE_URL` to `.env`
4. **Display post-install message** with getting started instructions

## Manual Recipe Installation

For contributing this recipe to Symfony Recipes:

1. Fork the [symfony/recipes-contrib](https://github.com/symfony/recipes-contrib) repository
2. Create directory: `shedeza/sybase-ase-orm-bundle/1.0/`
3. Copy recipe files:
   - `manifest.json`
   - `config/packages/sybase_ase_orm.yaml`
   - `post-install.txt`
4. Submit pull request

## Recipe Configuration

The recipe configures:

- **Bundle registration**: Automatically adds bundle to all environments
- **Default configuration**: Sets up basic ORM configuration
- **Environment variables**: Adds `SYBASE_DATABASE_URL` with example
- **Entity mapping**: Configures `src/Entity` directory for entities

## Usage After Installation

Users can immediately start using the ORM:

```php
// In a controller
public function index(EntityManager $entityManager): Response
{
    $users = $entityManager->getRepository(User::class)->findAll();
    // ...
}
```

## Version Compatibility

- **Bundle version**: 1.0+
- **Symfony version**: 6.0+ | 7.0+
- **PHP version**: 8.1+