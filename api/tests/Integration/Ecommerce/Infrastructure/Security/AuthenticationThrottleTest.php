<?php

declare(strict_types=1);

namespace App\Tests\Integration\Ecommerce\Infrastructure\Security;

use App\Ecommerce\Domain\Service\User\PasswordHasherInterface;
use App\Ecommerce\Infrastructure\Persistence\Entity\User\DoctrineRefreshToken;
use App\Tests\Factory\User\DoctrineUserFactory;
use App\Tests\Support\DatabaseTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * The brakes on authentication against the real database and the real password
 * check. The detailed rules are covered by RateLimitTest.
 *
 * Requests go straight through the kernel, so that they share the connection
 * (and the rolled-back transaction) of the test, and the counts.
 */
final class AuthenticationThrottleTest extends DatabaseTestCase
{
    private const string EMAIL = 'ada@example.test';
    private const string PASSWORD = 'a-long-enough-password';
    private const string ADDRESS = '203.0.113.10';

    protected function setUp(): void
    {
        parent::setUp();
        DoctrineUserFactory::createOne([
            'email' => self::EMAIL,
            'password' => self::getContainer()->get(PasswordHasherInterface::class)->hash(self::PASSWORD),
        ]);
    }

    public function testOnceBlockedAnAddressCannotSignInEvenWithTheRightPassword(): void
    {
        foreach (range(1, 5) as $attempt) {
            self::assertSame(401, $this->signIn('a-wrong-password', self::ADDRESS)->getStatusCode());
        }

        $response = $this->signIn(self::PASSWORD, self::ADDRESS);

        // Otherwise the guessing would go on, and a 200 would tell the right password.
        self::assertSame(429, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(0, $this->entityManager->getRepository(DoctrineRefreshToken::class)->count([]), 'No session may be opened.');
    }

    public function testTheOwnerSigningInFromElsewhereIsNotLockedOut(): void
    {
        foreach (range(1, 6) as $attempt) {
            $this->signIn('a-wrong-password', self::ADDRESS);
        }

        self::assertSame(200, $this->signIn(self::PASSWORD, '198.51.100.20')->getStatusCode());
    }

    public function testTheStoreKeepsNeitherTheEmailNorTheAddress(): void
    {
        $this->signIn('a-wrong-password', self::ADDRESS);
        $this->request('/api/v1/shop/register', self::ADDRESS, [
            'email' => 'grace@example.test',
            'password' => self::PASSWORD,
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
        ]);

        $storage = self::getContainer()->get('test.rate_limiter.storage');
        self::assertInstanceOf(InMemoryStorage::class, $storage);
        $keys = array_keys((fn (): array => $this->buckets)->call($storage));

        // The two sign-in limits and the registration one.
        self::assertCount(3, $keys);
        foreach ($keys as $key) {
            self::assertStringNotContainsString('ada', $key);
            self::assertStringNotContainsString('grace', $key);
            self::assertStringNotContainsString('203.0.113', $key);
        }
    }

    private function signIn(string $password, string $address): Response
    {
        return $this->request('/api/v1/shop/login', $address, ['email' => self::EMAIL, 'password' => $password]);
    }

    /**
     * @param array<string, string> $body
     */
    private function request(string $uri, string $address, array $body): Response
    {
        \assert(null !== self::$kernel);

        return self::$kernel->handle(Request::create(
            $uri,
            'POST',
            server: ['REMOTE_ADDR' => $address, 'CONTENT_TYPE' => 'application/json'],
            content: json_encode($body, \JSON_THROW_ON_ERROR),
        ));
    }
}
