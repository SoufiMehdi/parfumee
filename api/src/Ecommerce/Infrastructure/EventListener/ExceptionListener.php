<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\EventListener;

use App\Ecommerce\Domain\Exception\AuthenticationExceptionInterface;
use App\Ecommerce\Domain\Exception\ConflictExceptionInterface;
use App\Ecommerce\Domain\Exception\DomainExceptionInterface;
use App\Ecommerce\Domain\Exception\NotFoundExceptionInterface;
use App\Ecommerce\Domain\Exception\ValidationExceptionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * The single place where an exception becomes an HTTP response.
 *
 * Controllers do not catch anything: a business exception tells what kind
 * of failure it is through its interface, and the status code is chosen here.
 *
 * A request that fails validation (#[MapRequestPayload], #[MapQueryString],
 * #[MapUploadedFile]) answers with one entry per invalid field.
 *
 * The message of a server error (5xx) is never sent to the client outside
 * debug mode: it may hold SQL, file paths or class names. It is logged instead.
 */
final class ExceptionListener
{
    public const string UNEXPECTED_ERROR_MESSAGE = 'An unexpected error occurred.';
    public const string VALIDATION_FAILED_MESSAGE = 'Validation failed.';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly NameConverterInterface $nameConverter,
        private readonly bool $debug,
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $statusCode = $this->statusCodeOf($exception);
        $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];

        $validationFailure = $exception->getPrevious();
        if ($exception instanceof HttpExceptionInterface && $validationFailure instanceof ValidationFailedException) {
            $event->setResponse(new JsonResponse([
                'error' => self::VALIDATION_FAILED_MESSAGE,
                'violations' => $this->violationsOf($validationFailure),
            ], $statusCode, $headers));

            return;
        }

        if ($statusCode < Response::HTTP_INTERNAL_SERVER_ERROR) {
            $event->setResponse(new JsonResponse(['error' => $exception->getMessage()], $statusCode, $headers));

            return;
        }

        $this->logger->error('Uncaught exception: {message}', [
            'message' => $exception->getMessage(),
            'exception' => $exception,
        ]);

        $data = ['error' => self::UNEXPECTED_ERROR_MESSAGE];
        if ($this->debug) {
            $data['debug'] = [
                'class' => $exception::class,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile().':'.$exception->getLine(),
            ];
        }

        $event->setResponse(new JsonResponse($data, $statusCode, $headers));
    }

    /**
     * Fields are named as the client sent them (snake_case), not as the DTO
     * properties, so that a front can attach each message to its input.
     *
     * @return list<array{field: string, message: string}>
     */
    private function violationsOf(ValidationFailedException $failure): array
    {
        $violations = [];
        foreach ($failure->getViolations() as $violation) {
            $violations[] = [
                'field' => $this->nameConverter->normalize($violation->getPropertyPath()),
                'message' => (string) $violation->getMessage(),
            ];
        }

        return $violations;
    }

    private function statusCodeOf(\Throwable $exception): int
    {
        return match (true) {
            $exception instanceof NotFoundExceptionInterface => Response::HTTP_NOT_FOUND,
            $exception instanceof ConflictExceptionInterface => Response::HTTP_CONFLICT,
            $exception instanceof ValidationExceptionInterface => Response::HTTP_UNPROCESSABLE_ENTITY,
            $exception instanceof AuthenticationExceptionInterface => Response::HTTP_UNAUTHORIZED,
            // A business exception without a more specific interface.
            $exception instanceof DomainExceptionInterface => Response::HTTP_BAD_REQUEST,
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            default => Response::HTTP_INTERNAL_SERVER_ERROR,
        };
    }
}
