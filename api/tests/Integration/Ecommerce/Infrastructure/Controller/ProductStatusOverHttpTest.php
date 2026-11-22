<?php

declare(strict_types=1);

namespace App\Tests\Integration\Ecommerce\Infrastructure\Controller;

use App\Ecommerce\Domain\Model\Catalog\ProductStatus;
use App\Tests\Factory\Catalog\DoctrineCategoryFactory;
use App\Tests\Factory\Catalog\DoctrineProductFactory;
use App\Tests\Factory\User\DoctrineUserFactory;
use App\Tests\Support\DatabaseTestCase;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * P2-02 from end to end, against the real database: a product created by the back-office
 * is a draft the shop does not see, a published one is shown, and deleting it archives it.
 *
 * Requests go straight through the kernel, so that they share the connection (and the
 * rolled-back transaction) of the test.
 */
final class ProductStatusOverHttpTest extends DatabaseTestCase
{
    private string $token;
    private string $categoryId;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = DoctrineUserFactory::new()->admin()->create();
        $this->token = self::getContainer()->get(JWTTokenManagerInterface::class)->create($admin);
        $this->categoryId = DoctrineCategoryFactory::createOne()->getId();
    }

    public function testANewProductIsADraftTheShopDoesNotSee(): void
    {
        $id = $this->createProduct(['name' => 'Bougie Ambre']);

        self::assertSame(404, $this->request('GET', '/api/v1/shop/products/'.$id)->getStatusCode());
        self::assertSame([], $this->slugsListedBy('/api/v1/shop/products'));
        self::assertSame('draft', $this->decode($this->request('GET', '/api/v1/admin/products/'.$id, $this->token))['status']);
        self::assertSame(['bougie-ambre'], $this->slugsListedBy('/api/v1/admin/products', $this->token));
    }

    public function testAPublishedProductIsShownUntilItIsArchived(): void
    {
        $id = $this->createProduct(['name' => 'Bougie Vanille', 'status' => 'published']);
        self::assertSame(200, $this->request('GET', '/api/v1/shop/products/'.$id)->getStatusCode());
        self::assertSame(['bougie-vanille'], $this->slugsListedBy('/api/v1/shop/products'));

        $deleted = $this->request('DELETE', '/api/v1/admin/products/'.$id, $this->token);

        self::assertSame(204, $deleted->getStatusCode(), (string) $deleted->getContent());
        self::assertSame(404, $this->request('GET', '/api/v1/shop/products/'.$id)->getStatusCode());
        self::assertSame([], $this->slugsListedBy('/api/v1/shop/products'));
        // Kept, and set aside in the back-office: listed only when asked for.
        self::assertSame([], $this->slugsListedBy('/api/v1/admin/products', $this->token));
        self::assertSame(['bougie-vanille'], $this->slugsListedBy('/api/v1/admin/products?status=archived', $this->token));
        self::assertSame('archived', $this->decode($this->request('GET', '/api/v1/admin/products/'.$id, $this->token))['status']);
    }

    public function testAnUpdateWithoutStatusKeepsIt(): void
    {
        $id = DoctrineProductFactory::createOne(['status' => ProductStatus::PUBLISHED, 'category' => DoctrineCategoryFactory::find($this->categoryId)])->getId();

        $response = $this->request('PUT', '/api/v1/admin/products/'.$id, $this->token, ['name' => 'Bougie Figue', 'price' => 22.0, 'category_id' => $this->categoryId]);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('published', $this->decode($response)['status']);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function createProduct(array $fields): string
    {
        $response = $this->request('POST', '/api/v1/admin/products', $this->token, $fields + ['price' => 24.9, 'category_id' => $this->categoryId]);
        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        $id = $this->decode($response)['id'];
        self::assertIsString($id);

        return $id;
    }

    /**
     * @return list<mixed>
     */
    private function slugsListedBy(string $uri, ?string $token = null): array
    {
        $response = $this->request('GET', $uri, $token);
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $items = $this->decode($response)['items'];
        self::assertIsArray($items);

        return array_column($items, 'slug');
    }

    /**
     * @param array<string, mixed>|null $body
     */
    private function request(string $method, string $uri, ?string $token = null, ?array $body = null): Response
    {
        $server = null === $token ? [] : ['HTTP_AUTHORIZATION' => 'Bearer '.$token];
        if (null !== $body) {
            $server['CONTENT_TYPE'] = 'application/json';
        }
        \assert(null !== self::$kernel);

        return self::$kernel->handle(Request::create($uri, $method, server: $server, content: null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR)));
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        $data = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
