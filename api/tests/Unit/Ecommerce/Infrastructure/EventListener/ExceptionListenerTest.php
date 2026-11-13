<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Infrastructure\EventListener;

use App\Ecommerce\Domain\Exception\AuthenticationExceptionInterface;
use App\Ecommerce\Domain\Exception\ConflictExceptionInterface;
use App\Ecommerce\Domain\Exception\DomainExceptionInterface;
use App\Ecommerce\Domain\Exception\NotFoundExceptionInterface;
use App\Ecommerce\Domain\Exception\ValidationExceptionInterface;
use App\Ecommerce\Infrastructure\EventListener\ExceptionListener;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ExceptionListenerTest extends TestCase
{
    private const string SECRET = 'SQLSTATE[08006] connection to server at "10.0.0.5" failed';

    /**
     * @return iterable<string, array{\Throwable, int}>
     */
    public static function businessExceptions(): iterable
    {
        yield 'not found' => [new class('Product not found.') extends \DomainException implements NotFoundExceptionInterface {}, 404];
        yield 'conflict' => [new class('Email already taken.') extends \DomainException implements ConflictExceptionInterface {}, 409];
        yield 'validation' => [new class('Invalid price.') extends \DomainException implements ValidationExceptionInterface {}, 422];
        yield 'authentication' => [new class('Invalid credentials.') extends \DomainException implements AuthenticationExceptionInterface {}, 401];
        yield 'unspecified business error' => [new class('Not allowed.') extends \DomainException implements DomainExceptionInterface {}, 400];
    }

    #[DataProvider('businessExceptions')]
    public function testBusinessExceptionIsMappedToItsStatusCodeWithItsMessage(\Throwable $exception, int $expectedStatusCode): void
    {
        $response = $this->handle($exception, debug: false);

        self::assertSame($expectedStatusCode, $response->getStatusCode());
        self::assertSame(['error' => $exception->getMessage()], $this->decode($response));
    }

    public function testBusinessExceptionIsNotLogged(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method(self::anything());

        $this->handle(new class('Product not found.') extends \DomainException implements NotFoundExceptionInterface {}, debug: false, logger: $logger);
    }

    public function testHttpExceptionKeepsItsStatusCodeMessageAndHeaders(): void
    {
        $response = $this->handle(new TooManyRequestsHttpException(30, 'Slow down.'), debug: false);

        self::assertSame(429, $response->getStatusCode());
        self::assertSame(['error' => 'Slow down.'], $this->decode($response));
        self::assertSame('30', $response->headers->get('Retry-After'));
    }

    public function testClientHttpExceptionIsNotLogged(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method(self::anything());

        $this->handle(new NotFoundHttpException('No route found.'), debug: false, logger: $logger);
    }

    public function testUnexpectedExceptionMessageIsHiddenOutsideDebug(): void
    {
        $response = $this->handle(new \RuntimeException(self::SECRET), debug: false);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame(['error' => 'An unexpected error occurred.'], $this->decode($response));
        self::assertStringNotContainsString('SQLSTATE', (string) $response->getContent());
    }

    public function testServerHttpExceptionMessageIsHiddenOutsideDebug(): void
    {
        $response = $this->handle(new ServiceUnavailableHttpException(null, self::SECRET), debug: false);

        self::assertSame(503, $response->getStatusCode());
        self::assertSame(['error' => 'An unexpected error occurred.'], $this->decode($response));
    }

    public function testUnexpectedExceptionIsLoggedWithTheException(): void
    {
        $exception = new \RuntimeException(self::SECRET);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(self::isString(), self::callback(
                static fn (array $context): bool => $exception === $context['exception'] && self::SECRET === $context['message'],
            ));

        $this->handle($exception, debug: false, logger: $logger);
    }

    public function testUnexpectedExceptionDetailsAreShownInDebug(): void
    {
        $response = $this->handle(new \RuntimeException(self::SECRET), debug: true);
        $data = $this->decode($response);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('An unexpected error occurred.', $data['error']);
        self::assertSame(\RuntimeException::class, $data['debug']['class']);
        self::assertSame(self::SECRET, $data['debug']['message']);
    }

    private function handle(\Throwable $exception, bool $debug, ?LoggerInterface $logger = null): Response
    {
        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );

        new ExceptionListener($logger ?? new NullLogger(), $debug)->onKernelException($event);

        $response = $event->getResponse();
        self::assertInstanceOf(Response::class, $response);

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
