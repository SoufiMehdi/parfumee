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
 * P2-01 from end to end, against the real database: an editor builds a top-level category
 * and its children, the shop finds the products of the children under the parent, and only
 * an empty category is deleted.
 *
 * Requests go straight through the kernel, so that they share the connection (and the
 * rolled-back transaction) of the test.
 */
final class CategoriesOverHttpTest extends DatabaseTestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $editor = DoctrineUserFactory::createOne(['roles' => ['ROLE_EDITOR']]);
        $this->token = self::getContainer()->get(JWTTokenManagerInterface::class)->create($editor);
    }

    public function testAnEditorBuildsTwoLevelsAndTheShopFollowsThem(): void
    {
        $candles = $this->create(['name' => 'Bougies']);
        $scented = $this->create(['name' => 'Bougies parfumées', 'parent_id' => $candles]);

        $child = $this->decode($this->request('GET', '/api/v1/admin/categories/'.$scented));
        self::assertSame('bougies-parfumees', $child['slug']);
        self::assertSame(['id' => $candles, 'name' => 'Bougies'], $child['parent']);
        self::assertSame(['Bougies parfumées'], $this->namesListedBy('/api/v1/admin/categories?parent_id='.$candles));
        self::assertSame(['Bougies'], $this->namesListedBy('/api/v1/admin/categories?top_level=true'));

        // A product filed in the child category shows under the parent in the shop.
        DoctrineProductFactory::createOne(['slug' => 'ambre', 'status' => ProductStatus::PUBLISHED, 'category' => DoctrineCategoryFactory::find($scented)]);
        $shop = $this->decode($this->request('GET', '/api/v1/shop/products?category_id='.$candles, authenticated: false));
        self::assertSame(['ambre'], array_column($shop['items'], 'slug'));
    }

    public function testTwoLevelsOnly(): void
    {
        $candles = $this->create(['name' => 'Bougies']);
        $scented = $this->create(['name' => 'Bougies parfumées', 'parent_id' => $candles]);
        $melts = $this->create(['name' => 'Fondants']);

        $underAChild = $this->request('POST', '/api/v1/admin/categories', ['name' => 'Ambre', 'parent_id' => $scented]);
        $parentUnderAnother = $this->request('PUT', '/api/v1/admin/categories/'.$candles, ['name' => 'Bougies', 'parent_id' => $melts]);

        self::assertSame(422, $underAChild->getStatusCode(), (string) $underAChild->getContent());
        self::assertSame(422, $parentUnderAnother->getStatusCode(), (string) $parentUnderAnother->getContent());
    }

    public function testOnlyAnEmptyCategoryIsDeleted(): void
    {
        $candles = $this->create(['name' => 'Bougies']);
        $scented = $this->create(['name' => 'Bougies parfumées', 'parent_id' => $candles]);
        $melts = $this->create(['name' => 'Fondants']);
        DoctrineProductFactory::createOne(['status' => ProductStatus::ARCHIVED, 'category' => DoctrineCategoryFactory::find($melts)]);

        self::assertSame(409, $this->request('DELETE', '/api/v1/admin/categories/'.$candles)->getStatusCode(), 'It holds a child category.');
        self::assertSame(409, $this->request('DELETE', '/api/v1/admin/categories/'.$melts)->getStatusCode(), 'It holds an archived product.');

        self::assertSame(204, $this->request('DELETE', '/api/v1/admin/categories/'.$scented)->getStatusCode());
        self::assertSame(204, $this->request('DELETE', '/api/v1/admin/categories/'.$candles)->getStatusCode());
        self::assertSame(404, $this->request('GET', '/api/v1/admin/categories/'.$candles)->getStatusCode());
        self::assertSame(['Fondants'], $this->namesListedBy('/api/v1/admin/categories'));
    }

    public function testARenamedCategoryGetsAFreeSlug(): void
    {
        $this->create(['name' => 'Fondants']);
        $other = $this->create(['name' => 'Cires']);

        $renamed = $this->request('PUT', '/api/v1/admin/categories/'.$other, ['name' => 'Fondants']);

        self::assertSame(200, $renamed->getStatusCode(), (string) $renamed->getContent());
        self::assertSame('fondants-2', $this->decode($renamed)['slug']);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function create(array $body): string
    {
        $response = $this->request('POST', '/api/v1/admin/categories', $body);
        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        $id = $this->decode($response)['id'];
        self::assertIsString($id);

        return $id;
    }

    /**
     * @return list<mixed>
     */
    private function namesListedBy(string $uri): array
    {
        $response = $this->request('GET', $uri);
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $items = $this->decode($response)['items'];
        self::assertIsArray($items);

        return array_column($items, 'name');
    }

    /**
     * @param array<string, mixed>|null $body
     */
    private function request(string $method, string $uri, ?array $body = null, bool $authenticated = true): Response
    {
        $server = $authenticated ? ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token] : [];
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
