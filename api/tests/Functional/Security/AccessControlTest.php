<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Ecommerce\Application\UseCase\Catalog\GetFilteredProductsUseCase;
use App\Ecommerce\Application\UseCase\User\DeleteUserUseCase;
use App\Ecommerce\Application\UseCase\User\GetUsersUseCase;
use App\Ecommerce\Application\UseCase\User\GetUserUseCase;
use App\Ecommerce\Application\UseCase\User\UpdateUserUseCase;
use App\Ecommerce\Domain\Model\User\User;
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
        yield 'shop: read account' => ['GET', '/api/shop/users/'.self::OWNER_ID];
        yield 'shop: update account' => ['PUT', '/api/shop/users/'.self::OWNER_ID];
        yield 'shop: delete account' => ['DELETE', '/api/shop/users/'.self::OWNER_ID];
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

        $this->client->request('GET', '/api/admin/users', server: ['HTTP_AUTHORIZATION' => 'Bearer not-a-jwt']);

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function adminOnlyRoutes(): iterable
    {
        yield 'admin: list users' => ['GET', '/api/admin/users'];
        yield 'admin: read user' => ['GET', '/api/admin/users/'.self::OWNER_ID];
        yield 'admin: update user' => ['PUT', '/api/admin/users/'.self::OWNER_ID];
        yield 'admin: delete user' => ['DELETE', '/api/admin/users/'.self::OWNER_ID];
        yield 'admin: list products' => ['GET', '/api/admin/products'];
        yield 'admin: read product' => ['GET', '/api/admin/products/'.self::OTHER_ID];
        yield 'admin: create product' => ['POST', '/api/admin/products'];
        yield 'admin: update product' => ['PUT', '/api/admin/products/'.self::OTHER_ID];
        yield 'admin: upload picture' => ['POST', '/api/admin/products/'.self::OTHER_ID.'/pictures'];
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
     * @return iterable<string, array{string}>
     */
    public static function accountMethods(): iterable
    {
        yield 'read' => ['GET'];
        yield 'update' => ['PUT'];
        yield 'delete' => ['DELETE'];
    }

    #[DataProvider('accountMethods')]
    public function testCustomerIsForbiddenOnSomeoneElsesAccount(string $method): void
    {
        $this->forbidUseCases();
        $this->authenticateAs($this->client, self::OWNER_ID);

        $this->client->request($method, '/api/shop/users/'.self::OTHER_ID, content: '{}');

        self::assertResponseStatusCodeSame(403);
        self::assertSame('{"error":"Access denied."}', $this->client->getResponse()->getContent());
    }

    #[DataProvider('accountMethods')]
    public function testCustomerReachesTheirOwnAccount(string $method): void
    {
        $this->stubAccountUseCases(self::OWNER_ID);
        $this->authenticateAs($this->client, self::OWNER_ID);

        $this->client->request($method, '/api/shop/users/'.self::OWNER_ID, server: ['CONTENT_TYPE' => 'application/json'], content: self::ACCOUNT_BODY);

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

        $this->client->request($method, '/api/admin/users/'.self::OWNER_ID, content: '{}');

        self::assertResponseStatusCodeSame(403);
    }

    #[DataProvider('accountMethods')]
    public function testAdminReachesAnyAccount(string $method): void
    {
        $this->stubAccountUseCases(self::OWNER_ID);
        $this->authenticateAs($this->client, self::ADMIN_ID, ['ROLE_ADMIN']);

        $this->client->request($method, '/api/admin/users/'.self::OWNER_ID, server: ['CONTENT_TYPE' => 'application/json'], content: self::ACCOUNT_BODY);

        self::assertResponseIsSuccessful();
    }

    public function testAdminListsUsers(): void
    {
        $useCase = $this->createStub(GetUsersUseCase::class);
        $useCase->method('execute')->willReturn([]);
        static::getContainer()->set(GetUsersUseCase::class, $useCase);
        $this->authenticateAs($this->client, self::ADMIN_ID, ['ROLE_ADMIN']);

        $this->client->request('GET', '/api/admin/users');

        self::assertResponseIsSuccessful();
    }

    public function testCatalogueReadingIsPublic(): void
    {
        $useCase = $this->createStub(GetFilteredProductsUseCase::class);
        $useCase->method('execute')->willReturn([]);
        static::getContainer()->set(GetFilteredProductsUseCase::class, $useCase);

        $this->client->request('GET', '/api/shop/products');

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
        yield 'shop login' => ['/api/shop/login'];
        yield 'shop registration' => ['/api/shop/register'];
        yield 'back-office login' => ['/api/admin/login'];
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
        foreach ([GetUsersUseCase::class, GetUserUseCase::class, UpdateUserUseCase::class, DeleteUserUseCase::class] as $class) {
            $useCase = $this->createMock($class);
            $useCase->expects(self::never())->method('execute');
            static::getContainer()->set($class, $useCase);
        }
    }

    private function stubAccountUseCases(string $id): void
    {
        $user = new User($id, new Email('owner@example.test'), 'hash', 'Test', 'User');

        $get = $this->createStub(GetUserUseCase::class);
        $get->method('execute')->willReturn($user);
        static::getContainer()->set(GetUserUseCase::class, $get);

        $update = $this->createStub(UpdateUserUseCase::class);
        $update->method('execute')->willReturn($user);
        static::getContainer()->set(UpdateUserUseCase::class, $update);

        static::getContainer()->set(DeleteUserUseCase::class, $this->createStub(DeleteUserUseCase::class));
    }
}
