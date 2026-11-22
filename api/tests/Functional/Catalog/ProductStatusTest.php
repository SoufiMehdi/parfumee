<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog;

use App\Ecommerce\Application\DTO\Catalog\CreateProductDto;
use App\Ecommerce\Application\DTO\Catalog\UpdateProductDto;
use App\Ecommerce\Application\UseCase\Catalog\ArchiveProductUseCase;
use App\Ecommerce\Application\UseCase\Catalog\CreateProductUseCase;
use App\Ecommerce\Application\UseCase\Catalog\GetProductUseCase;
use App\Ecommerce\Application\UseCase\Catalog\ListProductsUseCase;
use App\Ecommerce\Application\UseCase\Catalog\UpdateProductUseCase;
use App\Ecommerce\Domain\Exception\Catalog\ProductNotFoundException;
use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Domain\Model\Catalog\ProductStatus;
use App\Ecommerce\Domain\Repository\Page;
use App\Ecommerce\Domain\Repository\Pagination;
use App\Tests\Support\AuthenticatesRequests;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * P2-02: the shop sees the published products only, the back-office sees them all, and
 * deleting a product archives it.
 *
 * The use cases are doubles: these tests check what each route asks them for. That the
 * repository then keeps to these statuses is covered by DoctrineProductRepositoryTest.
 */
final class ProductStatusTest extends WebTestCase
{
    use AuthenticatesRequests;

    private const string PRODUCT_ID = '11111111-1111-4111-8111-111111111111';
    private const string CATEGORY_ID = '22222222-2222-4222-8222-222222222222';
    private const string ADMIN_ID = '99999999-9999-4999-8999-999999999999';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testTheShopListsThePublishedProductsOnly(): void
    {
        $this->expectListedStatuses([ProductStatus::PUBLISHED]);

        // A status in the query string changes nothing: the shop has no such filter.
        $this->client->request('GET', '/api/v1/shop/products?status=draft');

        self::assertResponseIsSuccessful();
    }

    public function testTheShopReadsAPublishedProductOnly(): void
    {
        $useCase = $this->createMock(GetProductUseCase::class);
        $useCase->expects(self::once())->method('execute')
            ->with(self::PRODUCT_ID, [ProductStatus::PUBLISHED])
            ->willThrowException(new ProductNotFoundException(self::PRODUCT_ID));
        static::getContainer()->set(GetProductUseCase::class, $useCase);

        $this->client->request('GET', '/api/v1/shop/products/'.self::PRODUCT_ID);

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return iterable<string, array{string, list<ProductStatus>}>
     */
    public static function backOfficeFilters(): iterable
    {
        yield 'no status: drafts and published, not archived' => ['', [ProductStatus::DRAFT, ProductStatus::PUBLISHED]];
        yield 'drafts' => ['?status=draft', [ProductStatus::DRAFT]];
        yield 'published' => ['?status=published', [ProductStatus::PUBLISHED]];
        yield 'archived' => ['?status=archived', [ProductStatus::ARCHIVED]];
    }

    /**
     * @param list<ProductStatus> $expected
     */
    #[DataProvider('backOfficeFilters')]
    public function testTheBackOfficeFiltersByStatus(string $query, array $expected): void
    {
        $this->authenticateAs($this->client, self::ADMIN_ID, ['ROLE_ADMIN']);
        $this->expectListedStatuses($expected);

        $this->client->request('GET', '/api/v1/admin/products'.$query);

        self::assertResponseIsSuccessful();
    }

    public function testTheBackOfficeReadsAProductWhateverItsStatus(): void
    {
        $this->authenticateAs($this->client, self::ADMIN_ID, ['ROLE_ADMIN']);
        $useCase = $this->createMock(GetProductUseCase::class);
        $useCase->expects(self::once())->method('execute')
            ->with(self::PRODUCT_ID, ProductStatus::cases())
            ->willReturn(self::savedProduct(ProductStatus::ARCHIVED));
        static::getContainer()->set(GetProductUseCase::class, $useCase);

        $this->client->request('GET', '/api/v1/admin/products/'.self::PRODUCT_ID);

        self::assertResponseIsSuccessful();
        self::assertSame('archived', $this->decodedResponse()['status']);
    }

    public function testDeletingAProductArchivesIt(): void
    {
        $this->authenticateAs($this->client, self::ADMIN_ID, ['ROLE_ADMIN']);
        $useCase = $this->createMock(ArchiveProductUseCase::class);
        $useCase->expects(self::once())->method('execute')->with(self::PRODUCT_ID);
        static::getContainer()->set(ArchiveProductUseCase::class, $useCase);

        $this->client->request('DELETE', '/api/v1/admin/products/'.self::PRODUCT_ID);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('', (string) $this->client->getResponse()->getContent());
    }

    public function testDeletingAnUnknownProductIsNotFound(): void
    {
        $this->authenticateAs($this->client, self::ADMIN_ID, ['ROLE_ADMIN']);
        $useCase = $this->createStub(ArchiveProductUseCase::class);
        $useCase->method('execute')->willThrowException(new ProductNotFoundException(self::PRODUCT_ID));
        static::getContainer()->set(ArchiveProductUseCase::class, $useCase);

        $this->client->request('DELETE', '/api/v1/admin/products/'.self::PRODUCT_ID);

        self::assertResponseStatusCodeSame(404);
        self::assertSame(['error' => 'Product with id "11111111-1111-4111-8111-111111111111" not found.'], $this->decodedResponse());
    }

    /**
     * @return iterable<string, array{array<string, string>, ?string}>
     */
    public static function createdStatuses(): iterable
    {
        yield 'left out: the use case makes it a draft' => [[], null];
        yield 'published at once' => [['status' => 'published'], 'published'];
    }

    /**
     * @param array<string, string> $status
     */
    #[DataProvider('createdStatuses')]
    public function testTheStatusOfANewProductIsMapped(array $status, ?string $expected): void
    {
        $this->authenticateAs($this->client, self::ADMIN_ID, ['ROLE_ADMIN']);
        $useCase = $this->createMock(CreateProductUseCase::class);
        $useCase->expects(self::once())->method('execute')
            ->with(self::callback(static fn (CreateProductDto $dto): bool => $expected === $dto->status))
            ->willReturn(self::PRODUCT_ID);
        static::getContainer()->set(CreateProductUseCase::class, $useCase);

        $this->sendJson('POST', '/api/v1/admin/products', $status + ['name' => 'Ambre', 'price' => 24.9, 'category_id' => self::CATEGORY_ID]);

        self::assertResponseStatusCodeSame(201);
    }

    public function testAnUpdateWithoutStatusLeavesItToTheUseCase(): void
    {
        $this->authenticateAs($this->client, self::ADMIN_ID, ['ROLE_ADMIN']);
        $useCase = $this->createMock(UpdateProductUseCase::class);
        $useCase->expects(self::once())->method('execute')
            ->with(self::callback(static fn (UpdateProductDto $dto): bool => null === $dto->status), self::PRODUCT_ID)
            ->willReturn(self::savedProduct(ProductStatus::PUBLISHED));
        static::getContainer()->set(UpdateProductUseCase::class, $useCase);

        $this->sendJson('PUT', '/api/v1/admin/products/'.self::PRODUCT_ID, ['name' => 'Ambre', 'price' => 24.9, 'category_id' => self::CATEGORY_ID]);

        self::assertResponseIsSuccessful();
        self::assertSame('published', $this->decodedResponse()['status']);
    }

    /**
     * @param list<ProductStatus> $statuses
     */
    private function expectListedStatuses(array $statuses): void
    {
        $useCase = $this->createMock(ListProductsUseCase::class);
        $useCase->expects(self::once())->method('execute')
            ->with(self::anything(), $statuses)
            ->willReturn(new Page([], 0, new Pagination()));
        static::getContainer()->set(ListProductsUseCase::class, $useCase);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function sendJson(string $method, string $uri, array $body): void
    {
        $this->client->request($method, $uri, server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($body, \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodedResponse(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    private static function savedProduct(ProductStatus $status): Product
    {
        return new Product(
            self::PRODUCT_ID,
            'Ambre',
            'ambre',
            24.9,
            new Category(self::CATEGORY_ID, 'Bougies', 'bougies'),
            createdAt: new \DateTimeImmutable('2026-11-01T09:30:00+00:00'),
            updatedAt: new \DateTimeImmutable('2026-11-20T18:00:00+00:00'),
            status: $status,
        );
    }
}
