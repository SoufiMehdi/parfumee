<?php

declare(strict_types=1);

namespace App\Tests\Functional\Validation;

use App\Ecommerce\Application\DTO\Catalog\CreateProductDto;
use App\Ecommerce\Application\DTO\Catalog\GetProductsFilterDto;
use App\Ecommerce\Application\DTO\User\RegisterUserRequestDto;
use App\Ecommerce\Application\UseCase\Catalog\CreateProductUseCase;
use App\Ecommerce\Application\UseCase\Catalog\GetFilteredProductsUseCase;
use App\Ecommerce\Application\UseCase\Catalog\GetProductUseCase;
use App\Ecommerce\Application\UseCase\Catalog\UpdateProductUseCase;
use App\Ecommerce\Application\UseCase\User\DeleteUserUseCase;
use App\Ecommerce\Application\UseCase\User\GetUserUseCase;
use App\Ecommerce\Application\UseCase\User\LoginUserUseCase;
use App\Ecommerce\Application\UseCase\User\RegisterUserUseCase;
use App\Ecommerce\Application\UseCase\User\UpdateUserUseCase;
use App\Tests\Support\AuthenticatesRequests;
use PHPUnit\Framework\Attributes\DataProvider;
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

        $this->sendJson('POST', '/users/create', self::USER + ['phone_number' => '+33 6 12 34 56 78']);

        self::assertResponseStatusCodeSame(201);
        self::assertInstanceOf(RegisterUserRequestDto::class, $dto->value);
        self::assertSame('a@example.test', $dto->value->email);
        self::assertSame('Ada', $dto->value->firstName);
        self::assertSame('Lovelace', $dto->value->lastName);
        self::assertSame('+33 6 12 34 56 78', $dto->value->phoneNumber);
    }

    public function testUnknownFieldsAreIgnored(): void
    {
        $dto = $this->captureDto(RegisterUserUseCase::class);

        $this->sendJson('POST', '/users/create', self::USER + ['roles' => ['ROLE_ADMIN']]);

        self::assertResponseStatusCodeSame(201);
        self::assertObjectNotHasProperty('roles', $dto->value);
    }

    public function testProductBodyIsMapped(): void
    {
        $dto = $this->captureDto(CreateProductUseCase::class, self::ID);

        // An integer price is what a JSON client sends for a round amount.
        $this->sendJson('POST', '/create-product', ['price' => 25] + self::PRODUCT);

        self::assertResponseStatusCodeSame(201);
        self::assertInstanceOf(CreateProductDto::class, $dto->value);
        self::assertSame('Amber candle', $dto->value->name);
        self::assertSame(25.0, $dto->value->price);
        self::assertSame(self::ID, $dto->value->categoryId);
        self::assertSame(['weight' => 180], $dto->value->attributes);
    }

    public function testFilterIsMappedFromTheQueryString(): void
    {
        $dto = $this->captureDto(GetFilteredProductsUseCase::class, []);

        $this->client->request('GET', '/products/filter?category_id='.self::ID.'&min_price=10&max_price=30.5&scent=amber');

        self::assertResponseStatusCodeSame(200);
        self::assertInstanceOf(GetProductsFilterDto::class, $dto->value);
        self::assertSame(self::ID, $dto->value->categoryId);
        self::assertSame(10.0, $dto->value->minPrice);
        self::assertSame(30.5, $dto->value->maxPrice);
        self::assertSame('amber', $dto->value->scent);
        self::assertNull($dto->value->size);
    }

    public function testFilterIsOptional(): void
    {
        $dto = $this->captureDto(GetFilteredProductsUseCase::class, []);

        $this->client->request('GET', '/products/filter');

        self::assertResponseStatusCodeSame(200);
        self::assertEquals(new GetProductsFilterDto(), $dto->value);
    }

    // --- Refused bodies ------------------------------------------------------

    /**
     * @return iterable<string, array{string, string, array<string, mixed>, string}>
     */
    public static function invalidBodies(): iterable
    {
        $user = self::USER;
        $product = self::PRODUCT;

        yield 'registration without email' => ['POST', '/users/create', array_diff_key($user, ['email' => 0]), 'email'];
        yield 'registration with a malformed email' => ['POST', '/users/create', ['email' => 'nope'] + $user, 'email'];
        yield 'registration with a number as email' => ['POST', '/users/create', ['email' => 123] + $user, 'email'];
        yield 'registration with a short password' => ['POST', '/users/create', ['password' => 'short'] + $user, 'password'];
        yield 'registration with a blank first name' => ['POST', '/users/create', ['first_name' => ' '] + $user, 'first_name'];
        yield 'registration with a 256-character last name' => ['POST', '/users/create', ['last_name' => str_repeat('a', 256)] + $user, 'last_name'];
        yield 'registration with an empty object' => ['POST', '/users/create', [], 'email'];

        yield 'login without password' => ['POST', '/users/login', ['email' => 'a@example.test'], 'password'];

        // B9: a partial PUT used to erase the missing names.
        yield 'account update without last name' => ['PUT', '/users/'.self::ID, ['first_name' => 'Ada'], 'last_name'];
        yield 'account update with a blank first name' => ['PUT', '/users/'.self::ID, ['first_name' => '', 'last_name' => 'Lovelace'], 'first_name'];

        yield 'product without category' => ['POST', '/create-product', array_diff_key($product, ['category_id' => 0]), 'category_id'];
        yield 'product with a category that is not a UUID' => ['POST', '/create-product', ['category_id' => 'candles'] + $product, 'category_id'];
        yield 'product with a negative price' => ['POST', '/create-product', ['price' => -1] + $product, 'price'];
        yield 'product with a price too large for the column' => ['POST', '/create-product', ['price' => 100000000] + $product, 'price'];
        yield 'product with a text price' => ['POST', '/create-product', ['price' => 'cheap'] + $product, 'price'];
        yield 'product with a blank name' => ['POST', '/create-product', ['name' => ''] + $product, 'name'];
        yield 'product with a 256-character name' => ['POST', '/create-product', ['name' => str_repeat('a', 256)] + $product, 'name'];

        // B16: an empty body used to create a product named '' at price 0.
        yield 'product update with an empty object' => ['PUT', '/edit-product/'.self::ID, [], 'name'];
        yield 'product update with a negative price' => ['PUT', '/edit-product/'.self::ID, ['price' => -5] + $product, 'price'];
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

        $this->sendJson('POST', '/users/create', ['email' => 'nope', 'password' => 'short', 'first_name' => '', 'last_name' => 'Lovelace']);

        self::assertResponseStatusCodeSame(422);
        $fields = array_column($this->decodedResponse()['violations'], 'field');
        sort($fields);
        self::assertSame(['email', 'first_name', 'password'], $fields);
    }

    public function testMalformedJsonIsABadRequest(): void
    {
        $this->forbidUseCases();

        $this->client->request('POST', '/users/create', server: ['CONTENT_TYPE' => 'application/json'], content: '{not json');

        self::assertResponseStatusCodeSame(400);
        self::assertArrayHasKey('error', $this->decodedResponse());
    }

    public function testEmptyBodyIsABadRequest(): void
    {
        $this->forbidUseCases();

        $this->client->request('POST', '/create-product', server: ['CONTENT_TYPE' => 'application/json'], content: '');

        self::assertResponseStatusCodeSame(400);
        self::assertArrayHasKey('error', $this->decodedResponse());
    }

    /**
     * Without "Content-Type: application/json" the body is not read as JSON.
     */
    public function testJsonBodyWithoutItsContentTypeIsABadRequest(): void
    {
        $this->forbidUseCases();

        $this->client->request('POST', '/users/create', content: (string) json_encode(self::USER));

        self::assertResponseStatusCodeSame(400);
        self::assertArrayHasKey('error', $this->decodedResponse());
    }

    // --- Refused query strings and ids --------------------------------------

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidFilters(): iterable
    {
        yield 'category that is not a UUID' => ['category_id=candles', 'category_id'];
        yield 'text price' => ['min_price=cheap', 'min_price'];
        yield 'negative price' => ['max_price=-1', 'max_price'];
        yield 'maximum below minimum' => ['min_price=30&max_price=10', 'max_price'];
        yield '256-character scent' => ['scent='.str_repeat('a', 256), 'scent'];
    }

    #[DataProvider('invalidFilters')]
    public function testInvalidFilterIsRefused(string $query, string $expectedField): void
    {
        $this->forbidUseCases();
        $this->client->request('GET', '/products/filter?'.$query);

        self::assertResponseStatusCodeSame(422);
        self::assertContains($expectedField, array_column($this->decodedResponse()['violations'], 'field'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function routesWithAnIdThatIsNotAUuid(): iterable
    {
        yield 'read product' => ['GET', '/product/abc'];
        yield 'update product' => ['PUT', '/edit-product/abc'];
        yield 'upload picture' => ['POST', '/upload-picture/abc'];
        yield 'read account' => ['GET', '/users/abc'];
        yield 'update account' => ['PUT', '/users/abc'];
        yield 'delete account' => ['DELETE', '/users/abc'];
        // Upper case is refused too: ids are always produced in lower case.
        yield 'upper-case id' => ['GET', '/product/AAAAAAAA-AAAA-4AAA-8AAA-AAAAAAAAAAAA'];
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
            CreateProductUseCase::class, UpdateProductUseCase::class, GetProductUseCase::class, GetFilteredProductsUseCase::class,
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
}
