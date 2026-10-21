<?php

namespace App\Tests\Unit\Ecommerce\Infrastructure\EventListener;

use App\Ecommerce\Domain\Exception\DomainExceptionInterface;
use App\Ecommerce\Infrastructure\EventListener\ExceptionListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ExceptionListenerTest extends TestCase
{
    private ExceptionListener $listener;
    private HttpKernelInterface $kernel;

    protected function setUp(): void
    {
        $this->listener = new ExceptionListener();
        $this->kernel = $this->createMock(HttpKernelInterface::class);
    }

    public function testOnKernelExceptionWithDomainException(): void
    {
        // 1. Arrange
        // On crée une exception anonyme qui implémente ton interface de Domaine
        $domainException = new class('Erreur métier : Solde insuffisant.') extends \Exception implements DomainExceptionInterface {};

        $event = new ExceptionEvent(
            $this->kernel,
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $domainException
        );

        // 2. Act
        $this->listener->onKernelException($event);

        // 3. Assert
        $response = $event->getResponse();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(JsonResponse::HTTP_BAD_REQUEST, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('Erreur métier : Solde insuffisant.', $data['error']);
    }

    public function testOnKernelExceptionWithHttpException(): void
    {
        // 1. Arrange
        // Une exception HTTP standard de Symfony (ici une 404)
        $httpException = new NotFoundHttpException('Cette ressource n\'existe pas.');

        $event = new ExceptionEvent(
            $this->kernel,
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $httpException
        );

        // 2. Act
        $this->listener->onKernelException($event);

        // 3. Assert
        $response = $event->getResponse();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(JsonResponse::HTTP_NOT_FOUND, $response->getStatusCode()); // 404

        $data = json_decode($response->getContent(), true);
        $this->assertSame('Cette ressource n\'existe pas.', $data['error']);
    }

    public function testOnKernelExceptionWithGenericException(): void
    {
        // 1. Arrange
        // Une exception PHP classique (Erreur technique ou de code)
        $genericException = new \Exception('Database connection timeout.');

        $event = new ExceptionEvent(
            $this->kernel,
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $genericException
        );

        // 2. Act
        $this->listener->onKernelException($event);

        // 3. Assert
        $response = $event->getResponse();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode()); // 500

        $data = json_decode($response->getContent(), true);
        // On vérifie que le message d'erreur générique est bien combiné avec le message de l'exception
        $this->assertSame('An unexpected error occurred.Database connection timeout.', $data['error']);
    }
}
