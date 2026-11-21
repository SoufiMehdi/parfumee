<?php

declare(strict_types=1);

namespace App\Tests\Integration\Ecommerce\Infrastructure\Controller;

use App\Tests\Factory\Catalog\DoctrineCategoryFactory;
use App\Tests\Factory\Catalog\DoctrineProductFactory;
use App\Tests\Factory\User\DoctrineUserFactory;
use App\Tests\Support\DatabaseTestCase;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The list format over HTTP, against the real database: query string in,
 * one page with its pagination and the X-Total-Count header out.
 *
 * Requests go straight through the kernel, so that they share the connection
 * (and the rolled-back transaction) of the test.
 */
final class ListEndpointsTest extends DatabaseTestCase
{
    public function testAPageOfProductsComesWithItsPaginationAndTheTotalHeader(): void
    {
        $category = DoctrineCategoryFactory::createOne();
        foreach (['cheap' => 8.0, 'mid' => 20.0, 'upper' => 30.0, 'dear' => 45.0, 'luxury' => 90.0] as $slug => $price) {
            DoctrineProductFactory::createOne(['slug' => $slug, 'price' => $price, 'category' => $category]);
        }

        $response = $this->request('/api/v1/shop/products?sort=price&order=desc&per_page=2&page=2');

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $body = $this->decode($response);
        self::assertSame(['items', 'pagination'], array_keys($body));
        self::assertSame(['upper', 'mid'], array_column($body['items'], 'slug'));
        self::assertSame(['page' => 2, 'per_page' => 2, 'total' => 5, 'total_pages' => 3], $body['pagination']);
        self::assertSame('5', $response->headers->get('X-Total-Count'));
    }

    public function testThePageAfterTheLastIsEmptyButKnowsTheTotal(): void
    {
        DoctrineProductFactory::createMany(3);

        $response = $this->request('/api/v1/shop/products?page=9');

        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode($response);
        self::assertSame([], $body['items']);
        self::assertSame(['page' => 9, 'per_page' => 20, 'total' => 3, 'total_pages' => 1], $body['pagination']);
        self::assertSame('3', $response->headers->get('X-Total-Count'));
    }

    public function testTheFiltersAndTheTotalGoTogether(): void
    {
        DoctrineProductFactory::createOne(['slug' => 'amber', 'attributes' => ['fragrance' => 'amber']]);
        DoctrineProductFactory::createOne(['slug' => 'vanilla', 'attributes' => ['fragrance' => 'vanilla']]);

        $response = $this->request('/api/v1/shop/products?fragrance=amber');

        $body = $this->decode($response);
        self::assertSame(['amber'], array_column($body['items'], 'slug'));
        self::assertSame(1, $body['pagination']['total']);
        self::assertSame('1', $response->headers->get('X-Total-Count'));
    }

    public function testTheBackOfficeSearchesTheAccounts(): void
    {
        $admin = DoctrineUserFactory::new()->admin()->create(['email' => 'admin@example.test']);
        DoctrineUserFactory::createOne(['email' => 'ada@example.test', 'lastName' => 'Lovelace']);
        DoctrineUserFactory::createOne(['email' => 'grace@example.test', 'lastName' => 'Hopper']);
        $token = self::getContainer()->get(JWTTokenManagerInterface::class)->create($admin);

        $response = $this->request('/api/v1/admin/users?q=LOVE', $token);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $body = $this->decode($response);
        self::assertSame(['ada@example.test'], array_column($body['items'], 'email'));
        self::assertSame(1, $body['pagination']['total']);
        self::assertSame('1', $response->headers->get('X-Total-Count'));
    }

    private function request(string $uri, ?string $token = null): Response
    {
        $server = null === $token ? [] : ['HTTP_AUTHORIZATION' => 'Bearer '.$token];
        \assert(null !== self::$kernel);

        return self::$kernel->handle(Request::create($uri, 'GET', server: $server));
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
