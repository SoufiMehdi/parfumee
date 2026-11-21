<?php

declare(strict_types=1);

namespace App\Tests\Functional\Validation;

use App\Ecommerce\Application\DTO\Catalog\CreateProductDto;
use App\Ecommerce\Application\DTO\Catalog\ListProductsQueryDto;
use App\Ecommerce\Application\DTO\User\ListUsersQueryDto;
use App\Ecommerce\Application\DTO\User\RegisterUserRequestDto;
use App\Ecommerce\Application\UseCase\Catalog\CreateProductUseCase;
use App\Ecommerce\Application\UseCase\Catalog\GetProductUseCase;
use App\Ecommerce\Application\UseCase\Catalog\ListProductsUseCase;
use App\Ecommerce\Application\UseCase\Catalog\UpdateProductUseCase;
use App\Ecommerce\Application\UseCase\User\DeleteUserUseCase;
use App\Ecommerce\Application\UseCase\User\GetUserUseCase;
use App\Ecommerce\Application\UseCase\User\ListUsersUseCase;
use App\Ecommerce\Application\UseCase\User\LoginUserUseCase;
use App\Ecommerce\Application\UseCase\User\RegisterUserUseCase;
use App\Ecommerce\Application\UseCase\User\UpdateUserUseCase;
use App\Ecommerce\Domain\Repository\Page;
use App\Ecommerce\Domain\Repository\Pagination;
use App\Tests\Support\AuthenticatesRequests;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Covers the input side of B8, B9, B16 and S11: a request is mapped to its
 * DTO and validated before the use case runs.
 *
 * Use cases are mocks. For a refused request they expect zero calls; for an
 * accepted one they capture the DTO they received.
 */
final class InputValidationTest extends WebTestCase
{
    use AuthenticatesRequests;

    private const string ID = '11111111-1111-4111-8111-111111111111';
    private const array USER = ['email' => 'a@example.test', 'password' => 'long-enough', 'first_name' => 'Ada', 'last_name' => 'Lovelace'];
    private const array PRODUCT = ['name' => 'Amber candle', 'price' => 24.9, 'category_id' => self::ID, 'attributes' => ['weight' => 180]];

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->authenticateAs($this->client, self::ID, ['ROLE_ADMIN']);
    }

    // --- Accepted requests ---------------------------------------------------

    public function testRegistrationBodyIsMappedFromSnakeCase(): void
    {
        $dto = $this->captureDto(RegisterUserUseCase::class);

        $this->sendJson('POST', '/api/v1/shop/register', self::USER + ['phone_number' => '+33 6 12 34 56 78']);

        self::assertResponseStatusCodeSame(201);
        self::assertInstanceOf(RegisterUserRequestDto::class, $dto->value);
        self::assertSame('a@example.test', $dto->value->email);
        self::assertSame('Ada', $dto->value->firstName);
        self::assertSame('Lovelace', $dto->value->lastName);
        self::assertSame('+33 6 12 34 56 78', $dto->value->phoneNumber);
    }

    public function testRegistrationAddressIsMapped(): void
    {
        $dto = $this->captureDto(RegisterUserUseCase::class);

        $this->sendJson('POST', '/api/v1/shop/register', self::USER + [
            'address' => ['street' => '1 rue de la Paix', 'city' => 'Paris', 'postal_code' => '75002', 'country' => 'France'],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertInstanceOf(RegisterUserRequestDto::class, $dto->value);
        self::assertNotNull($dto->value->address);
        self::assertSame('75002', $dto->value->address->postalCode);
        self::assertSame('shipping', $dto->value->address->type);
    }

    public function testProductWithoutAttributesIsAccepted(): void
    {
        $dto = $this->captureDto(CreateProductUseCase::class, self::ID);

        $this->sendJson('POST', '/api/v1/admin/products', array_diff_key(self::PRODUCT, ['attributes' => 0]));

        self::assertResponseStatusCodeSame(201);
        self::assertInstanceOf(CreateProductDto::class, $dto->value);
        self::assertSame([], $dto->value->attributes);
    }

    public function testUnknownAttributesAreIgnored(): void
    {
        $this->captureDto(CreateProductUseCase::class, self::ID);

        $this->sendJson('POST', '/api/v1/admin/products', ['attributes' => ['wax' => 'soy', 'weight' => 180.5]] + self::PRODUCT);

        self::assertResponseStatusCodeSame(201);
    }

    public function testUnknownFieldsAreIgnored(): void
    {
        $dto = $this->captureDto(RegisterUserUseCase::class);

        $this->sendJson('POST', '/api/v1/shop/register', self::USER + ['roles' => ['ROLE_ADMIN']]);

        self::assertResponseStatusCodeSame(201);
        self::assertObjectNotHasProperty('roles', $dto->value);
    }

    public function testProductBodyIsMapped(): void
    {
        $dto = $this->captureDto(CreateProductUseCase::class, self::ID);

        // An integer price is what a JSON client sends for a round amount.
        $this->sendJson('POST', '/api/v1/admin/products', ['price' => 25] + self::PRODUCT);

        self::assertResponseStatusCodeSame(201);
        self::assertInstanceOf(CreateProductDto::class, $dto->value);
        self::assertSame('Amber candle', $dto->value->name);
        self::assertSame(25.0, $dto->value->price);
        self::assertSame(self::ID, $dto->value->categoryId);
        self::assertSame(['weight' => 180], $dto->value->attributes);
    }

    public function testFilterIsMappedFromTheQueryString(): void
    {
        $dto = $this->captureDto(ListProductsUseCase::class, self::emptyPage());

        $this->client->request('GET', '/api/v1/shop/products?category_id='.self::ID.'&min_price=10&max_price=30.5&fragrance=amber');

        self::assertResponseStatusCodeSame(200);
        self::assertInstanceOf(ListProductsQueryDto::class, $dto->value);
        self::assertSame(self::ID, $dto->value->categoryId);
        self::assertSame(10.0, $dto->value->minPrice);
        self::assertSame(30.5, $dto->value->maxPrice);
        self::assertSame('amber', $dto->value->fragrance);
        self::assertNull($dto->value->size);
    }

    public function testPaginationAndSortAreMappedFromTheQueryString(): void
    {
        $dto = $this->captureDto(ListProductsUseCase::class, self::emptyPage());

        $this->client->request('GET', '/api/v1/shop/products?page=3&per_page=50&sort=price&order=desc');

        self::assertResponseStatusCodeSame(200);
        self::assertInstanceOf(ListProductsQueryDto::class, $dto->value);
        self::assertSame(3, $dto->value->page);
        self::assertSame(50, $dto->value->perPage);
        self::assertSame('price', $dto->value->sort);
        self::assertSame('desc', $dto->value->order);
    }

    public function testFilterIsOptional(): void
    {
        $dto = $this->captureDto(ListProductsUseCase::class, self::emptyPage());

        $this->client->request('GET', '/api/v1/shop/products');

        self::assertResponseStatusCodeSame(200);
        self::assertEquals(new ListProductsQueryDto(), $dto->value);
    }

    public function testUserListQueryIsMapped(): void
    {
        $dto = $this->captureDto(ListUsersUseCase::class, self::emptyPage());

        $this->client->request('GET', '/api/v1/admin/users?q=ada&page=2&per_page=10&sort=last_name&order=desc');

        self::assertResponseStatusCodeSame(200);
        self::assertEquals(new ListUsersQueryDto(q: 'ada', page: 2, perPage: 10, sort: 'last_name', order: 'desc'), $dto->value);
    }

    // --- Refused bodies ------------------------------------------------------

    /**
     * @return iterable<string, array{string, string, array<string, mixed>, string}>
     */
    public static function invalidBodies(): iterable
    {
        $user = self::USER;
        $product = self::PRODUCT;

        yield 'registration without email' => ['POST', '/api/v1/shop/register', array_diff_key($user, ['email' => 0]), 'email'];
        yield 'registration with a malformed email' => ['POST', '/api/v1/shop/register', ['email' => 'nope'] + $user, 'email'];
        yield 'registration with a number as email' => ['POST', '/api/v1/shop/register', ['email' => 123] + $user, 'email'];
        yield 'registration with a short password' => ['POST', '/api/v1/shop/register', ['password' => 'short'] + $user, 'password'];
        yield 'registration with a blank first name' => ['POST', '/api/v1/shop/register', ['first_name' => ' '] + $user, 'first_name'];
        yield 'registration with a 256-character last name' => ['POST', '/api/v1/shop/register', ['last_name' => str_repeat('a', 256)] + $user, 'last_name'];
        yield 'registration with an empty object' => ['POST', '/api/v1/shop/register', [], 'email'];

        yield 'login without password' => ['POST', '/api/v1/shop/login', ['email' => 'a@example.test'], 'password'];

        // B9: a partial PUT used to erase the missing names.
        yield 'account update without last name' => ['PUT', '/api/v1/shop/me', ['first_name' => 'Ada'], 'last_name'];
        yield 'account update with a blank first name' => ['PUT', '/api/v1/shop/me', ['first_name' => '', 'last_name' => 'Lovelace'], 'first_name'];

        yield 'product without category' => ['POST', '/api/v1/admin/products', array_diff_key($product, ['category_id' => 0]), 'category_id'];
        yield 'product with a category that is not a UUID' => ['POST', '/api/v1/admin/products', ['category_id' => 'candles'] + $product, 'category_id'];
        yield 'product with a negative price' => ['POST', '/api/v1/admin/products', ['price' => -1] + $product, 'price'];
        yield 'product with a price too large for the column' => ['POST', '/api/v1/admin/products', ['price' => 100000000] + $product, 'price'];
        yield 'product with a text price' => ['POST', '/api/v1/admin/products', ['price' => 'cheap'] + $product, 'price'];
        yield 'product with a blank name' => ['POST', '/api/v1/admin/products', ['name' => ''] + $product, 'name'];
        yield 'product with a 256-character name' => ['POST', '/api/v1/admin/products', ['name' => str_repeat('a', 256)] + $product, 'name'];

        // B5: "180g" used to be read as 180 without a word.
        yield 'product with a text weight' => ['POST', '/api/v1/admin/products', ['attributes' => ['weight' => '180g']] + $product, 'attributes[weight]'];
        yield 'product with a negative weight' => ['POST', '/api/v1/admin/products', ['attributes' => ['weight' => -1]] + $product, 'attributes[weight]'];
        yield 'product with a number as fragrance' => ['POST', '/api/v1/admin/products', ['attributes' => ['fragrance' => 12]] + $product, 'attributes[fragrance]'];

        // B7: phone number and address are now part of a registration.
        yield 'registration with a malformed phone number' => ['POST', '/api/v1/shop/register', ['phone_number' => 'call me'] + $user, 'phone_number'];
        yield 'registration with an address without postal code' => ['POST', '/api/v1/shop/register', ['address' => ['street' => '1 rue de la Paix', 'city' => 'Paris', 'country' => 'France']] + $user, 'address.postal_code'];
        yield 'registration with an unknown address type' => ['POST', '/api/v1/shop/register', ['address' => ['street' => '1 rue de la Paix', 'city' => 'Paris', 'postal_code' => '75002', 'country' => 'France', 'type' => 'home']] + $user, 'address.type'];
        yield 'account update with a malformed phone number' => ['PUT', '/api/v1/shop/me', ['first_name' => 'Ada', 'last_name' => 'Lovelace', 'phone_number' => 'x'], 'phone_number'];

        // B16: an empty body used to create a product named '' at price 0.
        yield 'product update with an empty object' => ['PUT', '/api/v1/admin/products/'.self::ID, [], 'name'];
        yield 'product update with a negative price' => ['PUT', '/api/v1/admin/products/'.self::ID, ['price' => -5] + $product, 'price'];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('invalidBodies')]
    public function testInvalidBodyIsRefusedWithTheFaultyField(string $method, string $uri, array $body, string $expectedField): void
    {
        $this->forbidUseCases();

        $this->sendJson($method, $uri, $body);

        self::assertResponseStatusCodeSame(422);
        $data = $this->decodedResponse();
        self::assertSame('Validation failed.', $data['error']);
        self::assertContains($expectedField, array_column($data['violations'], 'field'));
        foreach ($data['violations'] as $violation) {
            self::assertNotSame('', $violation['message']);
        }
    }

    public function testEveryFaultyFieldIsReportedAtOnce(): void
    {
        $this->forbidUseCases();

        $this->sendJson('POST', '/api/v1/shop/register', ['email' => 'nope', 'password' => 'short', 'first_name' => '', 'last_name' => 'Lovelace']);

        self::assertResponseStatusCodeSame(422);
        $fields = array_column($this->decodedResponse()['violations'], 'field');
        sort($fields);
        self::assertSame(['email', 'first_name', 'password'], $fields);
    }

    public function testMalformedJsonIsABadRequest(): void
    {
        $this->forbidUseCases();

        $this->client->request('POST', '/api/v1/shop/register', server: ['CONTENT_TYPE' => 'application/json'], content: '{not json');

        self::assertResponseStatusCodeSame(400);
        self::assertArrayHasKey('error', $this->decodedResponse());
    }

    public function testEmptyBodyIsABadRequest(): void
    {
        $this->forbidUseCases();

        $this->client->request('POST', '/api/v1/admin/products', server: ['CONTENT_TYPE' => 'application/json'], content: '');

        self::assertResponseStatusCodeSame(400);
        self::assertArrayHasKey('error', $this->decodedResponse());
    }

    /**
     * Without "Content-Type: application/json" the body is not read as JSON.
     */
    public function testJsonBodyWithoutItsContentTypeIsABadRequest(): void
    {
        $this->forbidUseCases();

        $this->client->request('POST', '/api/v1/shop/register', content: (string) json_encode(self::USER));

        self::assertResponseStatusCodeSame(400);
        self::assertArrayHasKey('error', $this->decodedResponse());
    }

    // --- Refused query strings and ids --------------------------------------

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidFilters(): iterable
    {
        $products = '/api/v1/shop/products?';
        yield 'category that is not a UUID' => [$products.'category_id=candles', 'category_id'];
        yield 'text price' => [$products.'min_price=cheap', 'min_price'];
        yield 'negative price' => [$products.'max_price=-1', 'max_price'];
        yield 'maximum below minimum' => [$products.'min_price=30&max_price=10', 'max_price'];
        yield '256-character fragrance' => [$products.'fragrance='.str_repeat('a', 256), 'fragrance'];
        yield 'page 0' => [$products.'page=0', 'page'];
        yield 'negative page' => [$products.'page=-1', 'page'];
        yield 'text page' => [$products.'page=two', 'page'];
        // Symfony turns it into the largest integer, which the range refuses.
        yield 'page beyond the integers' => [$products.'page=99999999999999999999', 'page'];
        yield 'page after the last one allowed' => [$products.'page=1000001', 'page'];
        yield 'no product per page' => [$products.'per_page=0', 'per_page'];
        yield 'too many products per page' => [$products.'per_page=101', 'per_page'];
        yield 'unknown product sort' => [$products.'sort=stock', 'sort'];
        // React-admin sorts by id unless told otherwise: its data provider must send a real field.
        yield 'sort by id' => [$products.'sort=id', 'sort'];
        yield 'unknown order' => [$products.'order=sideways', 'order'];
        $users = '/api/v1/admin/users?';
        yield '256-character account search' => [$users.'q='.str_repeat('a', 256), 'q'];
        yield 'sort accounts by password' => [$users.'sort=password', 'sort'];
        yield 'too many accounts per page' => [$users.'per_page=1000', 'per_page'];
        yield 'unknown account order' => [$users.'order=up', 'order'];
    }

    #[DataProvider('invalidFilters')]
    public function testInvalidFilterIsRefused(string $uri, string $expectedField): void
    {
        $this->forbidUseCases();
        $this->client->request('GET', $uri);

        self::assertResponseStatusCodeSame(422);
        self::assertContains($expectedField, array_column($this->decodedResponse()['violations'], 'field'));
    }

    /**
     * The answer is right, but Symfony passes the raw "1.5" to the constructor of
     * the DTO before reporting the error, and PHP warns while converting it to an int.
     * The warning is expected here, and only here.
     */
    #[IgnoreDeprecations]
    public function testADecimalPageIsRefused(): void
    {
        $this->forbidUseCases();
        $this->client->request('GET', '/api/v1/shop/products?page=1.5');

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['page'], array_column($this->decodedResponse()['violations'], 'field'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function routesWithAnIdThatIsNotAUuid(): iterable
    {
        yield 'read product' => ['GET', '/api/v1/shop/products/abc'];
        yield 'update product' => ['PUT', '/api/v1/admin/products/abc'];
        yield 'upload picture' => ['POST', '/api/v1/admin/products/abc/pictures'];
        yield 'read user' => ['GET', '/api/v1/admin/users/abc'];
        yield 'update user' => ['PUT', '/api/v1/admin/users/abc'];
        yield 'delete user' => ['DELETE', '/api/v1/admin/users/abc'];
        // Upper case is refused too: ids are always produced in lower case.
        yield 'upper-case id' => ['GET', '/api/v1/shop/products/AAAAAAAA-AAAA-4AAA-8AAA-AAAAAAAAAAAA'];
    }

    #[DataProvider('routesWithAnIdThatIsNotAUuid')]
    public function testIdThatIsNotAUuidIsNotFound(string $method, string $uri): void
    {
        $this->forbidUseCases();

        $this->sendJson($method, $uri, self::PRODUCT);

        self::assertResponseStatusCodeSame(404);
        self::assertArrayHasKey('error', $this->decodedResponse());
    }

    // --- Helpers -------------------------------------------------------------

    /**
     * @param array<string, mixed> $body
     */
    private function sendJson(string $method, string $uri, array $body): void
    {
        $this->client->request($method, $uri, server: ['CONTENT_TYPE' => 'application/json'], content: (string) json_encode((object) $body));
    }

    /**
     * Replaces a use case by a mock that records the DTO it is called with.
     *
     * @param class-string $useCase
     *
     * @return object{value: mixed}
     */
    private function captureDto(string $useCase, mixed $return = null): object
    {
        $captured = new \stdClass();
        $captured->value = null;

        $mock = $this->createMock($useCase);
        $invocation = $mock->expects(self::once())->method('execute')->willReturnCallback(
            static function (mixed ...$arguments) use ($captured, $return): mixed {
                foreach ($arguments as $argument) {
                    if (\is_object($argument)) {
                        $captured->value = $argument;
                    }
                }

                return $return;
            },
        );
        static::getContainer()->set($useCase, $mock);

        return $captured;
    }

    private function forbidUseCases(): void
    {
        $useCases = [
            RegisterUserUseCase::class, LoginUserUseCase::class, UpdateUserUseCase::class, GetUserUseCase::class, DeleteUserUseCase::class,
            CreateProductUseCase::class, UpdateProductUseCase::class, GetProductUseCase::class, ListProductsUseCase::class, ListUsersUseCase::class,
        ];
        foreach ($useCases as $useCase) {
            $mock = $this->createMock($useCase);
            $mock->expects(self::never())->method('execute');
            static::getContainer()->set($useCase, $mock);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodedResponse(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @return Page<mixed>
     */
    private static function emptyPage(): Page
    {
        return new Page([], 0, new Pagination());
    }
}
