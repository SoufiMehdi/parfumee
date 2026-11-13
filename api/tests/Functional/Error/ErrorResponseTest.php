<?php

declare(strict_types=1);

namespace App\Tests\Functional\Error;

use App\Ecommerce\Application\UseCase\Catalog\CreateProductUseCase;
use App\Ecommerce\Application\UseCase\Catalog\GetProductUseCase;
use App\Ecommerce\Application\UseCase\Catalog\UpdateProductUseCase;
use App\Ecommerce\Application\UseCase\Catalog\UploadProductImageUseCase;
use App\Ecommerce\Application\UseCase\User\GetUserUseCase;
use App\Ecommerce\Application\UseCase\User\LoginUserUseCase;
use App\Ecommerce\Application\UseCase\User\RegisterUserUseCase;
use App\Ecommerce\Domain\Exception\Catalog\CategoryNotFoundException;
use App\Ecommerce\Domain\Exception\Catalog\InvalidPriceException;
use App\Ecommerce\Domain\Exception\Catalog\ProductNotFoundException;
use App\Ecommerce\Domain\Exception\User\DuplicateEmailException;
use App\Ecommerce\Domain\Exception\User\InvalidCredentialsException;
use App\Ecommerce\Domain\Exception\User\InvalidEmailException;
use App\Ecommerce\Domain\Exception\User\UserNotFoundException;
use App\Tests\Support\AuthenticatesRequests;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Covers B8 and S3: every failure leaves the API as `{"error": "..."}` with
 * the right status code, whichever controller it went through.
 *
 * Use cases are replaced by stubs that throw, so the whole path from the
 * controller to ExceptionListener is the real one.
 */
final class ErrorResponseTest extends WebTestCase
{
    use AuthenticatesRequests;

    private const string ID = '11111111-1111-4111-8111-111111111111';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->authenticateAs($this->client, self::ID, ['ROLE_ADMIN']);
    }

    /**
     * @return iterable<string, array{class-string, \Throwable, string, string, int}>
     */
    public static function businessFailures(): iterable
    {
        yield 'read unknown product' => [GetProductUseCase::class, new ProductNotFoundException(self::ID), 'GET', '/product/'.self::ID, 404];
        yield 'update unknown product' => [UpdateProductUseCase::class, new ProductNotFoundException(self::ID), 'PUT', '/edit-product/'.self::ID, 404];
        yield 'update product with unknown category' => [UpdateProductUseCase::class, new CategoryNotFoundException(self::ID), 'PUT', '/edit-product/'.self::ID, 404];
        yield 'create product with unknown category' => [CreateProductUseCase::class, new CategoryNotFoundException(self::ID), 'POST', '/create-product', 404];
        yield 'create product with negative price' => [CreateProductUseCase::class, new InvalidPriceException(-1.0), 'POST', '/create-product', 422];
        yield 'read unknown user' => [GetUserUseCase::class, new UserNotFoundException(self::ID), 'GET', '/users/'.self::ID, 404];
        yield 'register with a taken email' => [RegisterUserUseCase::class, new DuplicateEmailException('a@example.test'), 'POST', '/users/create', 409];
        yield 'register with an invalid email' => [RegisterUserUseCase::class, new InvalidEmailException('nope'), 'POST', '/users/create', 422];
        yield 'login with wrong credentials' => [LoginUserUseCase::class, new InvalidCredentialsException(), 'POST', '/users/login', 401];
    }

    /**
     * @param class-string $useCase
     */
    #[DataProvider('businessFailures')]
    public function testBusinessFailure(string $useCase, \Throwable $failure, string $method, string $uri, int $expectedStatusCode): void
    {
        $this->useCaseThrows($useCase, $failure);

        $this->client->request($method, $uri, content: self::validBodyFor($uri));

        self::assertResponseStatusCodeSame($expectedStatusCode);
        self::assertSame(['error' => $failure->getMessage()], $this->decodedResponse());
    }

    public function testUploadOnUnknownProductIsNotFound(): void
    {
        $this->useCaseThrows(UploadProductImageUseCase::class, new ProductNotFoundException(self::ID));
        $path = (string) tempnam(sys_get_temp_dir(), 'error_test_');
        imagejpeg(imagecreatetruecolor(8, 8), $path);

        try {
            $this->client->request('POST', '/upload-picture/'.self::ID, files: [
                'file' => new UploadedFile($path, 'candle.jpg', 'image/jpeg', test: true),
            ]);
        } finally {
            @unlink($path);
        }

        self::assertResponseStatusCodeSame(404);
        self::assertSame(['error' => 'Product with id "'.self::ID.'" not found.'], $this->decodedResponse());
    }

    public function testInvalidJsonBodyIsABadRequest(): void
    {
        $this->client->request('POST', '/users/login', content: '{not json');

        self::assertResponseStatusCodeSame(400);
        self::assertArrayHasKey('error', $this->decodedResponse());
    }

    public function testUnknownRouteIsNotFound(): void
    {
        $this->client->request('GET', '/not-a-route');

        self::assertResponseStatusCodeSame(404);
        self::assertArrayHasKey('error', $this->decodedResponse());
    }

    public function testUnexpectedFailureIsAGenericServerError(): void
    {
        $this->useCaseThrows(GetProductUseCase::class, new \RuntimeException('SQLSTATE[08006] secret detail'));

        $this->client->request('GET', '/product/'.self::ID);

        self::assertResponseStatusCodeSame(500);
        // The test kernel runs in debug mode, which adds a "debug" key: the
        // release behaviour (no detail at all) is covered by ExceptionListenerTest.
        self::assertSame('An unexpected error occurred.', $this->decodedResponse()['error']);
    }

    /**
     * @param class-string $useCase
     */
    private function useCaseThrows(string $useCase, \Throwable $failure): void
    {
        $stub = $this->createStub($useCase);
        $stub->method('execute')->willThrowException($failure);
        static::getContainer()->set($useCase, $stub);
    }

    private static function validBodyFor(string $uri): string
    {
        return (string) json_encode(match (true) {
            str_starts_with($uri, '/users/create') => ['email' => 'a@example.test', 'password' => 'secret', 'first_name' => 'A', 'last_name' => 'B'],
            str_starts_with($uri, '/users/login') => ['email' => 'a@example.test', 'password' => 'secret'],
            default => ['name' => 'Candle', 'price' => 10, 'category_id' => self::ID],
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function decodedResponse(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
