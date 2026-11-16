<?php

declare(strict_types=1);

namespace App\Tests\Integration\Ecommerce\Infrastructure\Security;

use App\Ecommerce\Domain\Service\User\PasswordHasherInterface;
use App\Tests\Factory\User\DoctrineUserFactory;
use App\Tests\Support\DatabaseTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The whole session life cycle over HTTP, against the real database and with
 * real tokens: sign in, call /me, refresh, and what must be refused.
 *
 * Requests go straight through the kernel, so that they share the connection
 * (and the rolled-back transaction) of the test.
 */
final class TokenRefreshTest extends DatabaseTestCase
{
    private const string EMAIL = 'ada@example.test';
    private const string PASSWORD = 'a-long-enough-password';

    protected function setUp(): void
    {
        parent::setUp();
        DoctrineUserFactory::createOne([
            'email' => self::EMAIL,
            'firstName' => 'Ada',
            'password' => self::getContainer()->get(PasswordHasherInterface::class)->hash(self::PASSWORD),
        ]);
    }

    public function testSignInReturnsAJwtAndARefreshToken(): void
    {
        $session = $this->signIn();

        self::assertNotSame('', $session['token']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{128}$/', $session['refresh_token']);
    }

    public function testTheJwtGivesAccessToMyAccountWithoutKnowingItsId(): void
    {
        $session = $this->signIn();

        $response = $this->request('GET', '/api/v1/shop/me', token: $session['token']);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $account = $this->decode($response);
        self::assertSame(self::EMAIL, $account['email']);
        self::assertSame('Ada', $account['firstName']);
        self::assertArrayNotHasKey('password', $account);
    }

    public function testTheBackOfficeSignInIssuesARefreshTokenToo(): void
    {
        $session = $this->signIn('/api/v1/admin/login');

        self::assertSame(200, $this->refresh($session['refresh_token'])->getStatusCode());
    }

    public function testARefreshTokenIsExchangedForANewJwtAndANewRefreshToken(): void
    {
        $session = $this->signIn();

        $response = $this->refresh($session['refresh_token']);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $renewed = $this->decode($response);
        self::assertNotSame($session['refresh_token'], $renewed['refresh_token'], 'A refresh token is single-use: a new one must be issued.');

        $me = $this->request('GET', '/api/v1/shop/me', token: $renewed['token']);
        self::assertSame(200, $me->getStatusCode());
        self::assertSame(self::EMAIL, $this->decode($me)['email']);
    }

    public function testARefreshTokenWorksOnlyOnce(): void
    {
        $session = $this->signIn();
        self::assertSame(200, $this->refresh($session['refresh_token'])->getStatusCode());

        $response = $this->refresh($session['refresh_token']);

        self::assertSame(401, $response->getStatusCode());
    }

    public function testTheNewRefreshTokenWorksInItsTurn(): void
    {
        $session = $this->signIn();
        $renewed = $this->decode($this->refresh($session['refresh_token']));

        self::assertSame(200, $this->refresh($renewed['refresh_token'])->getStatusCode());
    }

    public function testAnUnknownRefreshTokenIsRefused(): void
    {
        self::assertSame(401, $this->refresh(str_repeat('a', 128))->getStatusCode());
    }

    public function testRefreshingWithoutATokenIsRefused(): void
    {
        self::assertSame(401, $this->request('POST', '/api/v1/token/refresh', [])->getStatusCode());
    }

    public function testAJwtIsNotARefreshToken(): void
    {
        $session = $this->signIn();

        self::assertSame(401, $this->refresh($session['token'])->getStatusCode());
    }

    public function testTheRefreshTokenIsStoredHashed(): void
    {
        $session = $this->signIn();

        $stored = $this->entityManager->getConnection()->fetchFirstColumn('SELECT refresh_token FROM refresh_tokens');

        self::assertCount(1, $stored);
        self::assertNotSame($session['refresh_token'], $stored[0]);
        self::assertStringStartsWith('sha256$', $stored[0]);
        // What the database holds must not be usable as a token.
        self::assertSame(401, $this->refresh($stored[0])->getStatusCode());
    }

    public function testTheRefreshTokenOfADeletedAccountIsRefused(): void
    {
        $session = $this->signIn();
        self::assertSame(200, $this->request('DELETE', '/api/v1/shop/me', token: $session['token'])->getStatusCode());

        self::assertSame(401, $this->refresh($session['refresh_token'])->getStatusCode());
    }

    public function testMyAccountRequiresAToken(): void
    {
        self::assertSame(401, $this->request('GET', '/api/v1/shop/me')->getStatusCode());
        self::assertSame(401, $this->request('GET', '/api/v1/shop/me', token: 'not-a-jwt')->getStatusCode());
    }

    /**
     * @return array{token: string, refresh_token: string}
     */
    private function signIn(string $uri = '/api/v1/shop/login'): array
    {
        $response = $this->request('POST', $uri, ['email' => self::EMAIL, 'password' => self::PASSWORD]);
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $session = $this->decode($response);
        self::assertIsString($session['token'] ?? null);
        self::assertIsString($session['refresh_token'] ?? null);

        return ['token' => $session['token'], 'refresh_token' => $session['refresh_token']];
    }

    private function refresh(string $refreshToken): Response
    {
        return $this->request('POST', '/api/v1/token/refresh', ['refresh_token' => $refreshToken]);
    }

    /**
     * @param array<string, mixed>|null $body
     */
    private function request(string $method, string $uri, ?array $body = null, ?string $token = null): Response
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }

        \assert(null !== self::$kernel);

        return self::$kernel->handle(Request::create($uri, $method, server: $server, content: null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR)));
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        $data = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
