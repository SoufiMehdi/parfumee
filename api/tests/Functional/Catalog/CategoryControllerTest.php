<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog;

use App\Ecommerce\Application\DTO\Catalog\CreateCategoryDto;
use App\Ecommerce\Application\DTO\Catalog\ListCategoriesQueryDto;
use App\Ecommerce\Application\DTO\Catalog\UpdateCategoryDto;
use App\Ecommerce\Application\UseCase\Catalog\CreateCategoryUseCase;
use App\Ecommerce\Application\UseCase\Catalog\DeleteCategoryUseCase;
use App\Ecommerce\Application\UseCase\Catalog\GetCategoryUseCase;
use App\Ecommerce\Application\UseCase\Catalog\ListCategoriesUseCase;
use App\Ecommerce\Application\UseCase\Catalog\UpdateCategoryUseCase;
use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Repository\Page;
use App\Ecommerce\Domain\Repository\Pagination;
use App\Tests\Support\AuthenticatesRequests;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The category routes of the back-office (P2-01): what each one hands its use case, and
 * what it answers. The use cases are doubles: nothing reaches the database.
 */
final class CategoryControllerTest extends WebTestCase
{
    use AuthenticatesRequests;

    private const string ID = '22222222-2222-4222-8222-222222222222';
    private const string PARENT_ID = '11111111-1111-4111-8111-111111111111';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->authenticateAs($this->client, '99999999-9999-4999-8999-999999999999', ['ROLE_EDITOR']);
    }

    public function testTheListIsOnePageOfCategoriesWithItsTotal(): void
    {
        $useCase = $this->createMock(ListCategoriesUseCase::class);
        $useCase->expects(self::once())->method('execute')
            ->with(new ListCategoriesQueryDto(q: 'bou', parentId: self::PARENT_ID, topLevel: false, page: 2, perPage: 1, sort: 'created_at', order: 'desc'))
            ->willReturn(new Page([self::child()], 7, new Pagination(page: 2, perPage: 1)));
        static::getContainer()->set(ListCategoriesUseCase::class, $useCase);

        $this->client->request('GET', '/api/v1/admin/categories?q=bou&parent_id='.self::PARENT_ID.'&page=2&per_page=1&sort=created_at&order=desc');

        self::assertResponseIsSuccessful();
        $body = $this->decodedResponse();
        self::assertSame([self::ID], array_column($body['items'], 'id'));
        self::assertSame(['page' => 2, 'per_page' => 1, 'total' => 7, 'total_pages' => 7], $body['pagination']);
        self::assertSame('7', $this->client->getResponse()->headers->get('X-Total-Count'));
    }

    public function testTheTopLevelFilterIsReadFromTheQueryString(): void
    {
        $useCase = $this->createMock(ListCategoriesUseCase::class);
        $useCase->expects(self::once())->method('execute')
            ->with(new ListCategoriesQueryDto(topLevel: true))
            ->willReturn(new Page([], 0, new Pagination()));
        static::getContainer()->set(ListCategoriesUseCase::class, $useCase);

        $this->client->request('GET', '/api/v1/admin/categories?top_level=true');

        self::assertResponseIsSuccessful();
    }

    public function testACategoryIsReadWithItsParentAndItsDatesInUtc(): void
    {
        $useCase = $this->createMock(GetCategoryUseCase::class);
        $useCase->expects(self::once())->method('execute')->with(self::ID)->willReturn(self::child());
        static::getContainer()->set(GetCategoryUseCase::class, $useCase);

        $this->client->request('GET', '/api/v1/admin/categories/'.self::ID);

        self::assertResponseIsSuccessful();
        self::assertSame([
            'id' => self::ID,
            'name' => 'Bougies parfumées',
            'slug' => 'bougies-parfumees',
            'parent' => ['id' => self::PARENT_ID, 'name' => 'Bougies'],
            'created_at' => '2026-11-01T09:30:00+00:00',
            'updated_at' => '2026-11-21T19:43:09+00:00',
        ], $this->decodedResponse());
    }

    public function testATopLevelCategoryHasANullParent(): void
    {
        $useCase = $this->createStub(GetCategoryUseCase::class);
        $useCase->method('execute')->willReturn(new Category(self::PARENT_ID, 'Bougies', 'bougies', null, new \DateTimeImmutable('2026-11-01T09:30:00+00:00'), new \DateTimeImmutable('2026-11-01T09:30:00+00:00')));
        static::getContainer()->set(GetCategoryUseCase::class, $useCase);

        $this->client->request('GET', '/api/v1/admin/categories/'.self::PARENT_ID);

        self::assertResponseIsSuccessful();
        self::assertArrayHasKey('parent', $body = $this->decodedResponse());
        self::assertNull($body['parent']);
    }

    public function testACategoryIsCreatedFromASnakeCaseBody(): void
    {
        $useCase = $this->createMock(CreateCategoryUseCase::class);
        $useCase->expects(self::once())->method('execute')
            ->with(new CreateCategoryDto('Bougies parfumées', self::PARENT_ID))
            ->willReturn(self::ID);
        static::getContainer()->set(CreateCategoryUseCase::class, $useCase);

        $this->sendJson('POST', '/api/v1/admin/categories', ['name' => 'Bougies parfumées', 'parent_id' => self::PARENT_ID]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame(['id' => self::ID], $this->decodedResponse());
    }

    public function testAnUpdateWithoutParentMakesATopLevelCategory(): void
    {
        $useCase = $this->createMock(UpdateCategoryUseCase::class);
        $useCase->expects(self::once())->method('execute')
            ->with(self::ID, new UpdateCategoryDto('Bougies parfumées'))
            ->willReturn(self::child());
        static::getContainer()->set(UpdateCategoryUseCase::class, $useCase);

        $this->sendJson('PUT', '/api/v1/admin/categories/'.self::ID, ['name' => 'Bougies parfumées']);

        self::assertResponseIsSuccessful();
        self::assertSame(self::ID, $this->decodedResponse()['id']);
    }

    public function testACategoryIsDeletedWithoutContent(): void
    {
        $useCase = $this->createMock(DeleteCategoryUseCase::class);
        $useCase->expects(self::once())->method('execute')->with(self::ID);
        static::getContainer()->set(DeleteCategoryUseCase::class, $useCase);

        $this->client->request('DELETE', '/api/v1/admin/categories/'.self::ID);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('', (string) $this->client->getResponse()->getContent());
    }

    private static function child(): Category
    {
        return new Category(
            self::ID,
            'Bougies parfumées',
            'bougies-parfumees',
            new Category(self::PARENT_ID, 'Bougies', 'bougies'),
            // Paris time on purpose: the response must give the same instant in UTC.
            new \DateTimeImmutable('2026-11-01 10:30:00', new \DateTimeZone('Europe/Paris')),
            new \DateTimeImmutable('2026-11-21 20:43:09', new \DateTimeZone('Europe/Paris')),
        );
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
}
