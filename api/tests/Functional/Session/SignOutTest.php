<?php

declare(strict_types=1);

namespace App\Tests\Functional\Session;

use App\Ecommerce\Application\DTO\User\RevokeRefreshTokenRequestDto;
use App\Ecommerce\Application\UseCase\User\RevokeRefreshTokenUseCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * POST /api/v1/token/revoke, without the database: the use case is a double.
 * The real revocation is covered by TokenRefreshTest.
 */
final class SignOutTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testSigningOutRevokesTheRefreshTokenAndAnswersNoContent(): void
    {
        $useCase = $this->createMock(RevokeRefreshTokenUseCase::class);
        $useCase->expects(self::once())->method('execute')->with(new RevokeRefreshTokenRequestDto('the-refresh-token'));
        static::getContainer()->set(RevokeRefreshTokenUseCase::class, $useCase);

        $this->client->request('POST', '/api/v1/token/revoke', server: ['CONTENT_TYPE' => 'application/json'], content: '{"refresh_token": "the-refresh-token"}');

        self::assertResponseStatusCodeSame(204);
        self::assertSame('', $this->client->getResponse()->getContent());
    }

    public function testAnExpiredJwtDoesNotPreventSigningOut(): void
    {
        // A front signing out after its JWT expired may still send it: that must not answer 401.
        static::getContainer()->set(RevokeRefreshTokenUseCase::class, $this->createStub(RevokeRefreshTokenUseCase::class));

        $this->client->request('POST', '/api/v1/token/revoke', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer an-expired-jwt'], content: '{"refresh_token": "the-refresh-token"}');

        self::assertResponseStatusCodeSame(204);
    }

    public function testTheRefreshTokenIsRequired(): void
    {
        $useCase = $this->createMock(RevokeRefreshTokenUseCase::class);
        $useCase->expects(self::never())->method('execute');
        static::getContainer()->set(RevokeRefreshTokenUseCase::class, $useCase);

        $this->client->request('POST', '/api/v1/token/revoke', server: ['CONTENT_TYPE' => 'application/json'], content: '{"refresh_token": " "}');

        self::assertResponseStatusCodeSame(422);
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(['refresh_token'], array_column($body['violations'], 'field'));
    }
}
