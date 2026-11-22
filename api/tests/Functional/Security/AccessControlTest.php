<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Ecommerce\Application\UseCase\Catalog\GetProductUseCase;
use App\Ecommerce\Application\UseCase\Catalog\ListProductsUseCase;
use App\Ecommerce\Application\UseCase\User\DeleteUserUseCase;
use App\Ecommerce\Application\UseCase\User\GetUserUseCase;
use App\Ecommerce\Application\UseCase\User\ListUsersUseCase;
use App\Ecommerce\Application\UseCase\User\UpdateUserUseCase;
use App\Ecommerce\Domain\Exception\Catalog\ProductNotFoundException;
use App\Ecommerce\Domain\Model\User\User;
use App\Ecommerce\Domain\Repository\Page;
use App\Ecommerce\Domain\Repository\Pagination;
use App\Ecommerce\Domain\ValueObject\User\Email;
use App\Tests\Support\AuthenticatesRequests;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Covers S1: who may call which route.
 *
 * Use cases are replaced by stubs, so a request that passes the firewall
 * never reaches the database. A stubbed use case that must not run is a mock
 * expecting zero calls.
 */
final class AccessControlTest extends WebTestCase
{
    use AuthenticatesRequests;

    private const string OWNER_ID = '11111111-1111-4111-8111-111111111111';
    private const string OTHER_ID = '22222222-2222-4222-8222-222222222222';
    private const string ADMIN_ID = '99999999-9999-4999-8999-999999999999';
    private const string EDITOR_ID = '88888888-8888-4888-8888-888888888888';
    private const string ACCOUNT_BODY = '{"first_name": "Ada", "last_name": "Lovelace"}';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function protectedRoutes(): iterable
    {
        yield 'shop: read my account' => ['GET', '/api/v1/shop/me'];
        yield 'shop: update my account' => ['PUT', '/api/v1/shop/me'];
        yield 'shop: delete my account' => ['DELETE', '/api/v1/shop/me'];
        yield 'admin: who am I' => ['GET', '/api/v1/admin/me'];
        yield from self::adminOnlyRoutes();
    }

    #[DataProvider('protectedRoutes')]
    public function testAnonymousIsRejected(string $method, string $uri): void
    {
        $this->forbidUseCases();

        $this->client->request($method, $uri);

        self::assertResponseStatusCodeSame(401);
    }

    public function testInvalidTokenIsRejected(): void
    {
        $this->forbidUseCases();

        $this->client->request('GET', '/api/v1/admin/users', server: ['HTTP_AUTHORIZATION' => 'Bearer not-a-jwt']);

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function adminOnlyRoutes(): iterable
    {
        yield 'admin: list users' => ['GET', '/api/v1/admin/users'];
        yield 'admin: read user' => ['GET', '/api/v1/admin/users/'.self::OWNER_ID];
        yield 'admin: update user' => ['PUT', '/api/v1/admin/users/'.self::OWNER_ID];
        yield 'admin: delete user' => ['DELETE', '/api/v1/admin/users/'.self::OWNER_ID];
        yield 'admin: list products' => ['GET', '/api/v1/admin/products'];
        yield 'admin: read product' => ['GET', '/api/v1/admin/products/'.self::OTHER_ID];
        yield 'admin: create product' => ['POST', '/api/v1/admin/products'];
        yield 'admin: update product' => ['PUT', '/api/v1/admin/products/'.self::OTHER_ID];
        yield 'admin: upload picture' => ['POST', '/api/v1/admin/products/'.self::OTHER_ID.'/pictures'];
    }

    #[DataProvider('adminOnlyRoutes')]
    public function testCustomerIsForbiddenOnAdminRoutes(string $method, string $uri): void
    {
        $this->forbidUseCases();
        $this->authenticateAs($this->client, self::OWNER_ID);

        $this->client->request($method, $uri);

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function catalogueManagementRoutes(): iterable
    {
        yield 'list products' => ['GET', '/api/v1/admin/products'];
        yield 'read product' => ['GET', '/api/v1/admin/products/'.self::OTHER_ID];
        yield 'create product' => ['POST', '/api/v1/admin/products'];
        yield 'update product' => ['PUT', '/api/v1/admin/products/'.self::OTHER_ID];
        yield 'upload picture' => ['POST', '/api/v1/admin/products/'.self::OTHER_ID.'/pictures'];
    }

    /**
     * The body is empty on purpose: the controller refuses it (400 or 404) or answers,
     * but the firewall must have let the editor through.
     */
    #[DataProvider('catalogueManagementRoutes')]
    public function testEditorManagesTheCatalogue(string $method, string $uri): void
    {
        $this->stubCatalogueReadUseCases();
        $this->authenticateAs($this->client, self::EDITOR_ID, ['ROLE_EDITOR']);

        $this->client->request($method, $uri, content: 'not-json');

        self::assertNotContains($this->client->getResponse()->getStatusCode(), [401, 403]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function customerDataRoutes(): iterable
    {
        yield 'list users' => ['GET', '/api/v1/admin/users'];
        yield 'read user' => ['GET', '/api/v1/admin/users/'.self::OWNER_ID];
        yield 'update user' => ['PUT', '/api/v1/admin/users/'.self::OWNER_ID];
        yield 'delete user' => ['DELETE', '/api/v1/admin/users/'.self::OWNER_ID];
    }

    #[DataProvider('customerDataRoutes')]
    public function testEditorHasNoAccessToCustomerData(string $method, string $uri): void
    {
        $this->forbidUseCases();
        $this->authenticateAs($this->client, self::EDITOR_ID, ['ROLE_EDITOR']);

        $this->client->request($method, $uri, content: '{}');

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * An editor is also a customer: they keep access to their own account,
     * and the back-office can ask who they are.
     */
    #[DataProvider('identityRoutes')]
    public function testEditorReadsTheirOwnIdentity(string $uri): void
    {
        $this->expectAccountUseCasesToTarget(self::EDITOR_ID);
        $this->authenticateAs($this->client, self::EDITOR_ID, ['ROLE_EDITOR']);

        $this->client->request('GET', $uri);

        self::assertResponseIsSuccessful();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function identityRoutes(): iterable
    {
        yield 'shop' => ['/api/v1/shop/me'];
        yield 'back-office' => ['/api/v1/admin/me'];
    }

    public function testCustomerCannotAskTheBackOfficeWhoTheyAre(): void
    {
        $this->forbidUseCases();
        $this->authenticateAs($this->client, self::OWNER_ID);

        $this->client->request('GET', '/api/v1/admin/me');

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function accountMethods(): iterable
    {
        yield 'read' => ['GET'];
        yield 'update' => ['PUT'];
        yield 'delete' => ['DELETE'];
    }

    /**
     * /me carries no id: the account is always the one of the token. The use
     * cases are mocks that fail the test if they are given any other id.
     */
    #[DataProvider('accountMethods')]
    public function testCustomerReachesTheirOwnAccount(string $method): void
    {
        $this->expectAccountUseCasesToTarget(self::OWNER_ID);
        $this->authenticateAs($this->client, self::OWNER_ID);

        $this->client->request($method, '/api/v1/shop/me', server: ['CONTENT_TYPE' => 'application/json'], content: self::ACCOUNT_BODY);

        self::assertResponseIsSuccessful();
    }

    /**
     * The customer is refused on their own account too when they ask for it through the back-office.
     */
    #[DataProvider('accountMethods')]
    public function testCustomerIsForbiddenOnTheirOwnAccountThroughTheBackOffice(string $method): void
    {
        $this->forbidUseCases();
        $this->authenticateAs($this->client, self::OWNER_ID);

        $this->client->request($method, '/api/v1/admin/users/'.self::OWNER_ID, content: '{}');

        self::assertResponseStatusCodeSame(403);
    }

    #[DataProvider('accountMethods')]
    public function testAdminReachesAnyAccount(string $method): void
    {
        $this->stubAccountUseCases(self::OWNER_ID);
        $this->authenticateAs($this->client, self::ADMIN_ID, ['ROLE_ADMIN']);

        $this->client->request($method, '/api/v1/admin/users/'.self::OWNER_ID, server: ['CONTENT_TYPE' => 'application/json'], content: self::ACCOUNT_BODY);

        self::assertResponseIsSuccessful();
    }

    public function testAdminListsUsers(): void
    {
        $useCase = $this->createStub(ListUsersUseCase::class);
        $useCase->method('execute')->willReturn(new Page([], 0, new Pagination()));
        static::getContainer()->set(ListUsersUseCase::class, $useCase);
        $this->authenticateAs($this->client, self::ADMIN_ID, ['ROLE_ADMIN']);

        $this->client->request('GET', '/api/v1/admin/users');

        self::assertResponseIsSuccessful();
    }

    public function testCatalogueReadingIsPublic(): void
    {
        $useCase = $this->createStub(ListProductsUseCase::class);
        $useCase->method('execute')->willReturn(new Page([], 0, new Pagination()));
        static::getContainer()->set(ListProductsUseCase::class, $useCase);

        $this->client->request('GET', '/api/v1/shop/products');

        self::assertResponseIsSuccessful();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function formerRoutes(): iterable
    {
        yield 'login' => ['POST', '/users/login'];
        yield 'registration' => ['POST', '/users/create'];
        yield 'list users' => ['GET', '/users/list'];
        yield 'list products' => ['GET', '/products'];
        yield 'filter products' => ['GET', '/products/filter'];
        yield 'read product' => ['GET', '/product/'.self::OTHER_ID];
        yield 'create product' => ['POST', '/create-product'];
        yield 'update product' => ['PUT', '/edit-product/'.self::OTHER_ID];
        yield 'upload picture' => ['POST', '/upload-picture/'.self::OTHER_ID];
        // Replaced by /api/v1/shop/me in P1-05: an account is no longer addressed by its id in the shop.
        yield 'shop account by id' => ['GET', '/api/v1/shop/users/'.self::OWNER_ID];
    }

    /**
     * The routes of before P1-01 are gone, even for an administrator.
     */
    #[DataProvider('formerRoutes')]
    public function testFormerRoutesNoLongerExist(string $method, string $uri): void
    {
        $this->authenticateAs($this->client, self::ADMIN_ID, ['ROLE_ADMIN']);

        $this->client->request($method, $uri);

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function publicAuthenticationRoutes(): iterable
    {
        yield 'shop login' => ['/api/v1/shop/login'];
        yield 'shop registration' => ['/api/v1/shop/register'];
        yield 'back-office login' => ['/api/v1/admin/login'];
    }

    /**
     * An empty body is refused by the controller (400), which proves that
     * the firewall let the request through.
     */
    #[DataProvider('publicAuthenticationRoutes')]
    public function testAuthenticationRoutesArePublic(string $uri): void
    {
        $this->client->request('POST', $uri, content: 'not-json');

        self::assertNotContains($this->client->getResponse()->getStatusCode(), [401, 403]);
    }

    /**
     * Fails the test if a rejected request still reaches a use case.
     */
    private function forbidUseCases(): void
    {
        foreach ([ListUsersUseCase::class, GetUserUseCase::class, UpdateUserUseCase::class, DeleteUserUseCase::class] as $class) {
            $useCase = $this->createMock($class);
            $useCase->expects(self::never())->method('execute');
            static::getContainer()->set($class, $useCase);
        }
    }

    private function stubCatalogueReadUseCases(): void
    {
        $list = $this->createStub(ListProductsUseCase::class);
        $list->method('execute')->willReturn(new Page([], 0, new Pagination()));
        static::getContainer()->set(ListProductsUseCase::class, $list);

        $get = $this->createStub(GetProductUseCase::class);
        $get->method('execute')->willThrowException(new ProductNotFoundException(self::OTHER_ID));
        static::getContainer()->set(GetProductUseCase::class, $get);
    }

    /**
     * Stubs the account use cases and fails the test unless they are called with this id.
     */
    private function expectAccountUseCasesToTarget(string $id): void
    {
        $user = self::savedAccount($id);

        $get = $this->createMock(GetUserUseCase::class);
        $get->expects(self::atMost(1))->method('execute')->with($id)->willReturn($user);
        static::getContainer()->set(GetUserUseCase::class, $get);

        $update = $this->createMock(UpdateUserUseCase::class);
        $update->expects(self::atMost(1))->method('execute')->with($id, self::anything())->willReturn($user);
        static::getContainer()->set(UpdateUserUseCase::class, $update);

        $delete = $this->createMock(DeleteUserUseCase::class);
        $delete->expects(self::atMost(1))->method('execute')->with($id);
        static::getContainer()->set(DeleteUserUseCase::class, $delete);
    }

    private function stubAccountUseCases(string $id): void
    {
        $user = self::savedAccount($id);

        $get = $this->createStub(GetUserUseCase::class);
        $get->method('execute')->willReturn($user);
        static::getContainer()->set(GetUserUseCase::class, $get);

        $update = $this->createStub(UpdateUserUseCase::class);
        $update->method('execute')->willReturn($user);
        static::getContainer()->set(UpdateUserUseCase::class, $update);

        static::getContainer()->set(DeleteUserUseCase::class, $this->createStub(DeleteUserUseCase::class));
    }

    /**
     * An account as the use cases return it: saved, so with its dates.
     */
    private static function savedAccount(string $id): User
    {
        $savedAt = new \DateTimeImmutable('2026-11-01T09:30:00+00:00');

        return new User($id, new Email('owner@example.test'), 'hash', 'Test', 'User', createdAt: $savedAt, updatedAt: $savedAt);
    }
}
