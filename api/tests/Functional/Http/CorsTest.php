<?php

declare(strict_types=1);

namespace App\Tests\Functional\Http;

use App\Ecommerce\Application\UseCase\Catalog\GetProductUseCase;
use App\Ecommerce\Application\UseCase\Catalog\ListProductsUseCase;
use App\Ecommerce\Application\UseCase\Catalog\UpdateProductUseCase;
use App\Ecommerce\Application\UseCase\User\ListUsersUseCase;
use App\Ecommerce\Application\UseCase\User\LoginUserUseCase;
use App\Ecommerce\Domain\Exception\Catalog\ProductNotFoundException;
use App\Ecommerce\Domain\Exception\User\InvalidCredentialsException;
use App\Ecommerce\Domain\Repository\Page;
use App\Ecommerce\Domain\Repository\Pagination;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Covers S7 and P1-02: the shop and the back-office call the API from the
 * browser, from their own origins. Only these two origins may read the answers.
 *
 * The allowed origins are the fake ones of .env.test. No request here reaches
 * the database: the use cases that could run are stubs.
 */
final class CorsTest extends WebTestCase
{
    private const string PRODUCT_ID = '11111111-1111-4111-8111-111111111111';

    private KernelBrowser $client;
    private string $shopOrigin;
    private string $adminOrigin;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->shopOrigin = (string) $_SERVER['CORS_SHOP_ORIGIN'];
        $this->adminOrigin = (string) $_SERVER['CORS_ADMIN_ORIGIN'];
    }

    public function testTheTestOriginsAreSet(): void
    {
        self::assertSame('https://shop.example.test', $this->shopOrigin);
        self::assertSame('https://admin.example.test', $this->adminOrigin);
    }

    /**
     * The browser asks before a PUT with a token and a JSON body. The answer comes
     * before the firewall: a preflight carries no token and must not get a 401.
     */
    public function testTheBackOfficeMayUpdateAProduct(): void
    {
        $this->forbid(UpdateProductUseCase::class);

        $this->preflight($this->adminOrigin, 'PUT', '/api/v1/admin/products/'.self::PRODUCT_ID, 'authorization, content-type');

        self::assertResponseStatusCodeSame(200);
        $headers = $this->client->getResponse()->headers;
        self::assertSame($this->adminOrigin, $headers->get('Access-Control-Allow-Origin'));
        self::assertSame('GET, POST, PUT, DELETE', $headers->get('Access-Control-Allow-Methods'));
        self::assertSame('authorization, content-type', $headers->get('Access-Control-Allow-Headers'));
        self::assertSame('3600', $headers->get('Access-Control-Max-Age'));
        self::assertFalse($headers->has('Access-Control-Allow-Credentials'), 'The token is not a cookie: no credentials.');
    }

    public function testTheShopMayRenewASession(): void
    {
        $this->preflight($this->shopOrigin, 'POST', '/api/v1/token/refresh', 'content-type');

        self::assertResponseStatusCodeSame(200);
        self::assertSame($this->shopOrigin, $this->client->getResponse()->headers->get('Access-Control-Allow-Origin'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function fronts(): iterable
    {
        yield 'shop' => ['shop'];
        yield 'back-office' => ['admin'];
    }

    /**
     * Both fronts list products, and read the total of the list in its header.
     */
    #[DataProvider('fronts')]
    public function testEachFrontReadsAListAndItsTotal(string $front): void
    {
        $origin = 'shop' === $front ? $this->shopOrigin : $this->adminOrigin;
        $useCase = $this->createStub(ListProductsUseCase::class);
        $useCase->method('execute')->willReturn(new Page([], 0, new Pagination()));
        static::getContainer()->set(ListProductsUseCase::class, $useCase);

        $this->client->request('GET', '/api/v1/shop/products', server: ['HTTP_ORIGIN' => $origin]);

        self::assertResponseIsSuccessful();
        $headers = $this->client->getResponse()->headers;
        self::assertSame($origin, $headers->get('Access-Control-Allow-Origin'));
        self::assertSame('x-total-count, retry-after', $headers->get('Access-Control-Expose-Headers'));
        self::assertSame('0', $headers->get('X-Total-Count'));
        self::assertFalse($headers->has('Access-Control-Allow-Credentials'));
    }

    /**
     * An error the browser cannot read is an error the front cannot show: the
     * CORS headers come with the errors too. One request per test: the client
     * rebuilds the container between two requests, and would drop the stubs.
     */
    public function testAnAuthenticationErrorIsReadable(): void
    {
        $this->client->request('GET', '/api/v1/admin/users', server: ['HTTP_ORIGIN' => $this->adminOrigin]);

        self::assertResponseStatusCodeSame(401);
        self::assertSame($this->adminOrigin, $this->client->getResponse()->headers->get('Access-Control-Allow-Origin'));
    }

    /**
     * After too many attempts, the front reads how long to wait in Retry-After.
     */
    public function testTheWaitAfterTooManyAttemptsIsReadable(): void
    {
        // Several requests here: the counts, and the stub, must survive from one to the next.
        $this->client->disableReboot();
        $useCase = $this->createStub(LoginUserUseCase::class);
        $useCase->method('execute')->willThrowException(new InvalidCredentialsException());
        static::getContainer()->set(LoginUserUseCase::class, $useCase);

        foreach (range(1, 6) as $attempt) {
            $this->client->request('POST', '/api/v1/shop/login', server: ['HTTP_ORIGIN' => $this->shopOrigin, 'CONTENT_TYPE' => 'application/json'], content: '{"email": "ada@example.test", "password": "wrong"}');
        }

        self::assertResponseStatusCodeSame(429);
        $headers = $this->client->getResponse()->headers;
        self::assertSame($this->shopOrigin, $headers->get('Access-Control-Allow-Origin'));
        self::assertSame('x-total-count, retry-after', $headers->get('Access-Control-Expose-Headers'));
        self::assertGreaterThan(0, (int) $headers->get('Retry-After'));
    }

    public function testAValidationErrorIsReadable(): void
    {
        $this->forbid(ListProductsUseCase::class);

        $this->client->request('GET', '/api/v1/shop/products?per_page=0', server: ['HTTP_ORIGIN' => $this->shopOrigin]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame($this->shopOrigin, $this->client->getResponse()->headers->get('Access-Control-Allow-Origin'));
    }

    public function testANotFoundErrorIsReadable(): void
    {
        $useCase = $this->createStub(GetProductUseCase::class);
        $useCase->method('execute')->willThrowException(new ProductNotFoundException(self::PRODUCT_ID));
        static::getContainer()->set(GetProductUseCase::class, $useCase);

        $this->client->request('GET', '/api/v1/shop/products/'.self::PRODUCT_ID, server: ['HTTP_ORIGIN' => $this->shopOrigin]);

        self::assertResponseStatusCodeSame(404);
        self::assertSame($this->shopOrigin, $this->client->getResponse()->headers->get('Access-Control-Allow-Origin'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function otherOrigins(): iterable
    {
        yield 'another site' => ['https://evil.example'];
        yield 'a look-alike domain' => ['https://admin.example.test.evil.example'];
        yield 'a look-alike prefix' => ['https://fakeadmin.example.test'];
        yield 'the right host without TLS' => ['http://admin.example.test'];
        yield 'the right host on another port' => ['https://admin.example.test:8443'];
        // Sandboxed iframes and local files send "null".
        yield 'an opaque origin' => ['null'];
    }

    #[DataProvider('otherOrigins')]
    public function testAnyOtherOriginCannotReadTheAnswers(string $origin): void
    {
        $this->forbid(ListUsersUseCase::class);
        $useCase = $this->createStub(ListProductsUseCase::class);
        $useCase->method('execute')->willReturn(new Page([], 0, new Pagination()));
        static::getContainer()->set(ListProductsUseCase::class, $useCase);

        $this->client->request('GET', '/api/v1/shop/products', server: ['HTTP_ORIGIN' => $origin]);
        self::assertFalse($this->client->getResponse()->headers->has('Access-Control-Allow-Origin'));
        self::assertFalse($this->client->getResponse()->headers->has('Access-Control-Expose-Headers'));

        // A preflight is answered before any controller: the rebuilt container does not matter here.
        $this->preflight($origin, 'GET', '/api/v1/admin/users', 'authorization');
        self::assertFalse($this->client->getResponse()->headers->has('Access-Control-Allow-Origin'));
    }

    /**
     * Same-origin calls (Swagger UI) and server-side calls (the rendering of the shop)
     * send no Origin: they need no CORS header.
     */
    public function testARequestWithoutOriginGetsNoCorsHeader(): void
    {
        $useCase = $this->createStub(ListProductsUseCase::class);
        $useCase->method('execute')->willReturn(new Page([], 0, new Pagination()));
        static::getContainer()->set(ListProductsUseCase::class, $useCase);

        $this->client->request('GET', '/api/v1/shop/products');

        self::assertResponseIsSuccessful();
        self::assertFalse($this->client->getResponse()->headers->has('Access-Control-Allow-Origin'));
    }

    /**
     * Only the API is open to the fronts: any other path allows no origin.
     */
    public function testNothingOutsideTheApiIsOpenToTheFronts(): void
    {
        $this->client->request('GET', '/robots.txt', server: ['HTTP_ORIGIN' => $this->adminOrigin]);

        self::assertResponseStatusCodeSame(404);
        self::assertFalse($this->client->getResponse()->headers->has('Access-Control-Allow-Origin'));
    }

    public function testAMethodTheApiDoesNotUseIsRefused(): void
    {
        $this->preflight($this->adminOrigin, 'PATCH', '/api/v1/admin/products/'.self::PRODUCT_ID);

        self::assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
    }

    public function testAHeaderTheApiDoesNotExpectIsRefused(): void
    {
        $this->preflight($this->adminOrigin, 'GET', '/api/v1/admin/products', 'x-debug-token');

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    private function preflight(string $origin, string $method, string $uri, ?string $headers = null): void
    {
        $server = ['HTTP_ORIGIN' => $origin, 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => $method];
        if (null !== $headers) {
            $server['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] = $headers;
        }

        $this->client->request('OPTIONS', $uri, server: $server);
    }

    /**
     * @param class-string $useCase
     */
    private function forbid(string $useCase): void
    {
        $mock = $this->createMock($useCase);
        $mock->expects(self::never())->method('execute');
        static::getContainer()->set($useCase, $mock);
    }
}
