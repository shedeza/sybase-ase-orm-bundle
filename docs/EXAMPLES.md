# Ejemplos Prácticos

## Ejemplo Completo: Sistema de Blog

### Entidades

```php
<?php
// src/Entity/User.php
namespace App\Entity;

use Shedeza\SybaseAseOrmBundle\ORM\Mapping as ORM;
use App\Repository\UserRepository;

#[ORM\Entity]
#[ORM\Repository(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: ORM\GeneratedValue::IDENTITY)]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $username;

    #[ORM\Column(type: 'string', length: 255)]
    private string $email;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\OneToMany(targetEntity: Post::class, mappedBy: 'author')]
    private array $posts = [];

    #[ORM\OneToOne(targetEntity: UserProfile::class, mappedBy: 'user')]
    private ?UserProfile $profile = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->posts = [];
    }

    // Getters y Setters...
}
```

```php
<?php
// src/Entity/Post.php
namespace App\Entity;

use Shedeza\SybaseAseOrmBundle\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'posts')]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: ORM\GeneratedValue::IDENTITY)]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column(type: 'boolean')]
    private bool $published = false;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'posts')]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id')]
    private User $author;

    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'posts')]
    #[ORM\JoinTable(
        name: 'post_tags',
        joinColumns: [['name' => 'post_id', 'referencedColumnName' => 'id']],
        inverseJoinColumns: [['name' => 'tag_id', 'referencedColumnName' => 'id']]
    )]
    private array $tags = [];

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
    }

    // Getters y Setters...
}
```

### Repositorio Personalizado

```php
<?php
// src/Repository/UserRepository.php
namespace App\Repository;

use Shedeza\SybaseAseOrmBundle\ORM\Repository\AbstractRepository;
use App\Entity\User;

class UserRepository extends AbstractRepository
{
    public function findActiveAuthors(): array
    {
        $query = $this->createQuery('
            SELECT DISTINCT u FROM User u
            INNER JOIN u.posts p
            WHERE p.published = :published
            ORDER BY u.username ASC
        ');
        
        $query->setParameter('published', true);
        return $query->getResult();
    }

    public function findTopAuthors(int $limit = 10): array
    {
        return $this->executeNativeQuery('
            SELECT u.*, COUNT(p.id) as post_count
            FROM users u
            INNER JOIN posts p ON p.author_id = u.id
            WHERE p.published = 1
            GROUP BY u.id, u.username, u.email, u.created_at
            ORDER BY post_count DESC
            TOP :limit
        ', ['limit' => $limit]);
    }

    public function searchByKeyword(string $keyword): array
    {
        return $this->fullTextSearch($keyword, ['username', 'email']);
    }
}
```

### Controlador

```php
<?php
// src/Controller/BlogController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Shedeza\SybaseAseOrmBundle\ORM\EntityManager;
use App\Entity\User;
use App\Entity\Post;
use App\Entity\Tag;

class BlogController extends AbstractController
{
    #[Route('/blog/create-post', name: 'blog_create_post')]
    public function createPost(EntityManager $entityManager): Response
    {
        // Crear usuario
        $user = new User();
        $user->setUsername('blogger');
        $user->setEmail('blogger@example.com');

        // Crear tags
        $phpTag = new Tag();
        $phpTag->setName('PHP');

        $symfonyTag = new Tag();
        $symfonyTag->setName('Symfony');

        // Crear post
        $post = new Post();
        $post->setTitle('Mi primer post con Sybase ASE ORM');
        $post->setContent('Este es el contenido del post...');
        $post->setAuthor($user);
        $post->addTag($phpTag);
        $post->addTag($symfonyTag);
        $post->setPublished(true);

        // Persistir todo
        $entityManager->persist($user);
        $entityManager->persist($phpTag);
        $entityManager->persist($symfonyTag);
        $entityManager->persist($post);
        $entityManager->flush();

        return new Response('Post creado con ID: ' . $post->getId());
    }

    #[Route('/blog/posts', name: 'blog_posts')]
    public function listPosts(EntityManager $entityManager): Response
    {
        $repository = $entityManager->getRepository(Post::class);
        
        // Obtener posts publicados con paginación
        $posts = $repository->findPaginated(1, 10, ['published' => true]);
        
        $html = '<h1>Posts del Blog</h1>';
        $html .= '<p>Total: ' . $posts['total'] . ' posts</p>';
        
        foreach ($posts['data'] as $post) {
            $html .= '<div>';
            $html .= '<h2>' . $post->getTitle() . '</h2>';
            $html .= '<p>Por: ' . $post->getAuthor()->getUsername() . '</p>';
            $html .= '<p>' . substr($post->getContent(), 0, 100) . '...</p>';
            $html .= '</div><hr>';
        }
        
        return new Response($html);
    }

    #[Route('/blog/authors', name: 'blog_authors')]
    public function listAuthors(EntityManager $entityManager): Response
    {
        $userRepository = $entityManager->getRepository(User::class);
        $authors = $userRepository->findActiveAuthors();
        
        $html = '<h1>Autores Activos</h1>';
        
        foreach ($authors as $author) {
            $postCount = count($author->getPosts());
            $html .= '<div>';
            $html .= '<h3>' . $author->getUsername() . '</h3>';
            $html .= '<p>Email: ' . $author->getEmail() . '</p>';
            $html .= '<p>Posts: ' . $postCount . '</p>';
            $html .= '</div><hr>';
        }
        
        return new Response($html);
    }

    #[Route('/blog/stats', name: 'blog_stats')]
    public function showStats(EntityManager $entityManager): Response
    {
        $userRepo = $entityManager->getRepository(User::class);
        $postRepo = $entityManager->getRepository(Post::class);
        
        $userStats = $userRepo->getStatistics();
        $postStats = $postRepo->getStatistics();
        
        $publishedPosts = $postRepo->count(['published' => true]);
        $draftPosts = $postRepo->count(['published' => false]);
        
        $html = '<h1>Estadísticas del Blog</h1>';
        $html .= '<h2>Usuarios</h2>';
        $html .= '<p>Total usuarios: ' . $userStats['totalRecords'] . '</p>';
        $html .= '<h2>Posts</h2>';
        $html .= '<p>Total posts: ' . $postStats['totalRecords'] . '</p>';
        $html .= '<p>Publicados: ' . $publishedPosts . '</p>';
        $html .= '<p>Borradores: ' . $draftPosts . '</p>';
        
        return new Response($html);
    }
}
```

## Ejemplo: E-commerce

### Entidades de E-commerce

```php
<?php
// src/Entity/Product.php
namespace App\Entity;

use Shedeza\SybaseAseOrmBundle\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'products')]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: ORM\GeneratedValue::IDENTITY)]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private float $price;

    #[ORM\Column(type: 'integer')]
    private int $stock = 0;

    #[ORM\Column(type: 'boolean')]
    private bool $active = true;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'products')]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id')]
    private ?Category $category = null;

    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'product')]
    private array $orderItems = [];

    // Getters y Setters...
}
```

```php
<?php
// src/Entity/Order.php
namespace App\Entity;

use Shedeza\SybaseAseOrmBundle\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'orders')]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: ORM\GeneratedValue::IDENTITY)]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = 'pending';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private float $total = 0.0;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'customer_id', referencedColumnName: 'id')]
    private User $customer;

    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'order', cascade: true)]
    private array $items = [];

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
    }

    public function calculateTotal(): void
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += $item->getSubtotal();
        }
        $this->total = $total;
    }

    // Getters y Setters...
}
```

### Servicio de E-commerce

```php
<?php
// src/Service/OrderService.php
namespace App\Service;

use Shedeza\SybaseAseOrmBundle\ORM\EntityManager;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;

class OrderService
{
    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function createOrder(User $customer, array $items): Order
    {
        return $this->entityManager->transactional(function($em) use ($customer, $items) {
            $order = new Order();
            $order->setCustomer($customer);

            foreach ($items as $itemData) {
                $product = $em->find(Product::class, $itemData['product_id']);
                
                if (!$product || $product->getStock() < $itemData['quantity']) {
                    throw new \Exception('Producto no disponible');
                }

                $orderItem = new OrderItem();
                $orderItem->setProduct($product);
                $orderItem->setQuantity($itemData['quantity']);
                $orderItem->setPrice($product->getPrice());
                $orderItem->setOrder($order);

                // Reducir stock
                $product->setStock($product->getStock() - $itemData['quantity']);

                $order->addItem($orderItem);
                $em->persist($orderItem);
                $em->persist($product);
            }

            $order->calculateTotal();
            $em->persist($order);

            return $order;
        });
    }

    public function getOrdersByDateRange(\DateTime $start, \DateTime $end): array
    {
        $repository = $this->entityManager->getRepository(Order::class);
        return $repository->findByDateRange('createdAt', $start, $end);
    }

    public function getTopSellingProducts(int $limit = 10): array
    {
        $repository = $this->entityManager->getRepository(Product::class);
        
        return $repository->executeNativeQuery('
            SELECT p.*, SUM(oi.quantity) as total_sold
            FROM products p
            INNER JOIN order_items oi ON oi.product_id = p.id
            INNER JOIN orders o ON o.id = oi.order_id
            WHERE o.status = ?
            GROUP BY p.id, p.name, p.price, p.stock, p.active, p.category_id
            ORDER BY total_sold DESC
            TOP ?
        ', ['completed', $limit], false);
    }
}
```

## Ejemplo: Sistema de Auditoría

### Entidad Auditable

```php
<?php
// src/Entity/AuditableEntity.php
namespace App\Entity;

use Shedeza\SybaseAseOrmBundle\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'auditable_records')]
class AuditableEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: ORM\GeneratedValue::IDENTITY)]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 100)]
    private string $status = 'active';

    #[ORM\Column(type: 'datetime')]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $updatedAt = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $createdBy = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $updatedBy = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        
        // En un caso real, obtendrías el usuario actual del contexto de seguridad
        $this->createdBy = 'system';
        $this->updatedBy = 'system';
    }

    #[ORM\PostPersist]
    public function onPostPersist(): void
    {
        error_log("AuditableEntity created: ID {$this->id}, Name: {$this->name}");
    }

    public function updateRecord(string $name, string $updatedBy): void
    {
        $this->name = $name;
        $this->updatedAt = new \DateTime();
        $this->updatedBy = $updatedBy;
    }

    // Getters y Setters...
}
```

### Servicio de Auditoría

```php
<?php
// src/Service/AuditService.php
namespace App\Service;

use Shedeza\SybaseAseOrmBundle\ORM\EntityManager;
use App\Entity\AuditableEntity;

class AuditService
{
    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function createAuditableRecord(string $name, string $createdBy): AuditableEntity
    {
        $entity = new AuditableEntity();
        $entity->setName($name);
        $entity->setCreatedBy($createdBy);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $entity;
    }

    public function getAuditTrail(\DateTime $since): array
    {
        $repository = $this->entityManager->getRepository(AuditableEntity::class);
        
        return $repository->findByDateRange('createdAt', $since, new \DateTime(), [
            'status' => 'active'
        ]);
    }

    public function getRecordsByUser(string $username): array
    {
        $repository = $this->entityManager->getRepository(AuditableEntity::class);
        
        $createdByUser = $repository->findBy(['createdBy' => $username]);
        $updatedByUser = $repository->findBy(['updatedBy' => $username]);
        
        return array_merge($createdByUser, $updatedByUser);
    }

    public function getStatsByUser(): array
    {
        $repository = $this->entityManager->getRepository(AuditableEntity::class);
        
        return $repository->executeNativeQuery('
            SELECT 
                created_by as username,
                COUNT(*) as records_created,
                MAX(created_at) as last_activity
            FROM auditable_records 
            WHERE created_by IS NOT NULL
            GROUP BY created_by
            ORDER BY records_created DESC
        ', [], false);
    }
}
```

## Ejemplo: Testing Completo

```php
<?php
// tests/Repository/UserRepositoryTest.php
namespace App\Tests\Repository;

use PHPUnit\Framework\TestCase;
use Shedeza\SybaseAseOrmBundle\ORM\EntityManager;
use Shedeza\SybaseAseOrmBundle\DBAL\Connection;
use App\Entity\User;
use App\Entity\Post;

class UserRepositoryTest extends TestCase
{
    private EntityManager $entityManager;
    private array $testUsers = [];

    protected function setUp(): void
    {
        $config = [
            'host' => $_ENV['TEST_DB_HOST'] ?? 'localhost',
            'port' => $_ENV['TEST_DB_PORT'] ?? 5000,
            'database' => $_ENV['TEST_DB_NAME'] ?? 'test_db',
            'username' => $_ENV['TEST_DB_USER'] ?? 'test_user',
            'password' => $_ENV['TEST_DB_PASS'] ?? 'test_pass',
            'charset' => 'utf8'
        ];

        $connection = new Connection($config);
        $this->entityManager = new EntityManager($connection);

        $this->createTestData();
    }

    private function createTestData(): void
    {
        // Crear usuarios de prueba
        for ($i = 1; $i <= 5; $i++) {
            $user = new User();
            $user->setUsername("testuser{$i}");
            $user->setEmail("test{$i}@example.com");
            $user->setActive($i % 2 === 0); // Alternar activo/inactivo

            $this->entityManager->persist($user);
            $this->testUsers[] = $user;
        }

        // Crear posts para algunos usuarios
        foreach (array_slice($this->testUsers, 0, 3) as $user) {
            $post = new Post();
            $post->setTitle("Post by {$user->getUsername()}");
            $post->setContent("Content of the post...");
            $post->setAuthor($user);
            $post->setPublished(true);

            $this->entityManager->persist($post);
        }

        $this->entityManager->flush();
    }

    public function testFindActiveAuthors(): void
    {
        $repository = $this->entityManager->getRepository(User::class);
        $activeAuthors = $repository->findActiveAuthors();

        $this->assertNotEmpty($activeAuthors);
        
        foreach ($activeAuthors as $author) {
            $this->assertTrue($author->isActive());
            $this->assertNotEmpty($author->getPosts());
        }
    }

    public function testSearchByKeyword(): void
    {
        $repository = $this->entityManager->getRepository(User::class);
        $results = $repository->searchByKeyword('testuser');

        $this->assertCount(5, $results);
        
        foreach ($results as $user) {
            $this->assertStringContains('testuser', $user->getUsername());
        }
    }

    public function testUserStats(): void
    {
        $repository = $this->entityManager->getRepository(User::class);
        $stats = $repository->getUserStats();

        $this->assertEquals(5, $stats['total']);
        $this->assertEquals(2, $stats['active']); // usuarios 2 y 4
        $this->assertEquals(3, $stats['inactive']); // usuarios 1, 3 y 5
    }

    public function testPaginatedResults(): void
    {
        $repository = $this->entityManager->getRepository(User::class);
        $paginatedResults = $repository->findUsersPaginated(1, 3);

        $this->assertArrayHasKey('data', $paginatedResults);
        $this->assertArrayHasKey('total', $paginatedResults);
        $this->assertArrayHasKey('page', $paginatedResults);
        $this->assertArrayHasKey('totalPages', $paginatedResults);

        $this->assertCount(3, $paginatedResults['data']);
        $this->assertEquals(5, $paginatedResults['total']);
        $this->assertEquals(1, $paginatedResults['page']);
        $this->assertEquals(2, $paginatedResults['totalPages']);
    }

    public function testTransactionalOperations(): void
    {
        $initialCount = $this->entityManager->getRepository(User::class)->count();

        // Transacción exitosa
        $result = $this->entityManager->transactional(function($em) {
            $user = new User();
            $user->setUsername('transactional_user');
            $user->setEmail('trans@example.com');
            
            $em->persist($user);
            return $user->getId();
        });

        $this->assertNotNull($result);
        $this->assertEquals($initialCount + 1, $this->entityManager->getRepository(User::class)->count());

        // Transacción con error (rollback)
        try {
            $this->entityManager->transactional(function($em) {
                $user = new User();
                $user->setUsername('error_user');
                $user->setEmail('error@example.com');
                
                $em->persist($user);
                
                // Simular error
                throw new \Exception('Simulated error');
            });
        } catch (\Exception $e) {
            // Esperado
        }

        // El conteo debe seguir igual (rollback exitoso)
        $this->assertEquals($initialCount + 1, $this->entityManager->getRepository(User::class)->count());
    }

    protected function tearDown(): void
    {
        // Limpiar datos de prueba
        foreach ($this->testUsers as $user) {
            if ($this->entityManager->contains($user)) {
                $this->entityManager->remove($user);
            }
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
    }
}
```

Estos ejemplos muestran el uso completo del Sybase ASE ORM Bundle en escenarios reales, incluyendo relaciones complejas, transacciones, servicios de negocio y testing comprehensivo.