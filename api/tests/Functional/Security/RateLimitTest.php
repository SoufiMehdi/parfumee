<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Ecommerce\Application\DTO\User\LoginUserRequestDto;
use App\Ecommerce\Application\UseCase\User\LoginUserUseCase;
use App\Ecommerce\Application\UseCase\User\RegisterUserUseCase;
use App\Ecommerce\Domain\Exception\User\DuplicateEmailException;
use App\Ecommerce\Domain\Exception\User\InvalidCredentialsException;
use App\Ecommerce\Infrastructure\Security\AuthenticationThrottle;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * The brakes on the public authentication routes (audit S6), as configured in
 * config/packages/rate_limiter.yaml: 5 sign-in attempts per email and address,
 * 25 per address, 10 registrations per address.
 *
 * The kernel is kept from one request to the next (disableReboot): the counts live
 * in memory, and so do the stubs of the use cases. No request reaches the database.
 */
final class RateLimitTest extends WebTestCase
{
    private const string ADDRESS = '203.0.113.10';
    private const string OTHER_ADDRESS = '198.51.100.20';
    private const string PER_ACCOUNT_WINDOW = '15 minutes';
    private const string REGISTRATION_WINDOW = '1 hour';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
    }

    public function testTheSixthAttemptOnAnAccountIsRefusedBeforeThePasswordIsChecked(): void
    {
        $useCase = $this->createMock(LoginUserUseCase::class);
        $useCase->expects(self::exactly(5))->method('execute')->willThrowException(new InvalidCredentialsException());
        static::getContainer()->set(LoginUserUseCase::class, $useCase);

        self::assertSame([401, 401, 401, 401, 401], $this->signInTimes(5, 'ada@example.test'));
        $response = $this->signIn('ada@example.test');

        $this->assertTooManyRequests($response, AuthenticationThrottle::SIGN_IN_MESSAGE, self::PER_ACCOUNT_WINDOW);
    }

    public function testASuccessfulSignInClearsTheCountOfItsAccount(): void
    {
        $this->stubSignIn(rightPassword: 'right');

        self::assertSame([401, 401, 401, 401], $this->signInTimes(4, 'ada@example.test'));
        self::assertSame(200, $this->signIn('ada@example.test', 'right')->getStatusCode());

        // Five new attempts, as if nothing had happened before the success.
        self::assertSame([401, 401, 401, 401, 401], $this->signInTimes(5, 'ada@example.test'));
        self::assertSame(429, $this->signIn('ada@example.test')->getStatusCode());
    }

    public function testTheCountIsPerAccount(): void
    {
        $this->stubSignIn();
        $this->signInTimes(5, 'ada@example.test');

        self::assertSame(429, $this->signIn('ada@example.test')->getStatusCode());
        self::assertSame(401, $this->signIn('grace@example.test')->getStatusCode());
    }

    public function testTheCountIsPerAddress(): void
    {
        $this->stubSignIn();
        $this->signInTimes(5, 'ada@example.test');

        self::assertSame(429, $this->signIn('ada@example.test')->getStatusCode());
        // The real owner, signing in from elsewhere, is not locked out.
        self::assertSame(401, $this->signIn('ada@example.test', address: self::OTHER_ADDRESS)->getStatusCode());
    }

    public function testTheCaseOfTheEmailDoesNotOpenANewCount(): void
    {
        $this->stubSignIn();
        $this->signInTimes(5, 'Ada@Example.test');

        self::assertSame(429, $this->signIn('ada@example.test')->getStatusCode());
    }

    public function testOneAddressCannotTryMoreThanTwentyFiveSignIns(): void
    {
        $this->stubSignIn();
        foreach (range(1, 25) as $i) {
            self::assertSame(401, $this->signIn('customer'.$i.'@example.test')->getStatusCode());
        }

        $response = $this->signIn('customer26@example.test');

        $this->assertTooManyRequests($response, AuthenticationThrottle::SIGN_IN_MESSAGE, self::PER_ACCOUNT_WINDOW);
    }

    public function testASuccessDoesNotClearTheCountOfTheAddress(): void
    {
        $this->stubSignIn(rightPassword: 'right');
        foreach (range(1, 24) as $i) {
            $this->signIn('customer'.$i.'@example.test');
        }

        // A client signing in to its own account between two guesses gains nothing:
        // the address keeps counting.
        self::assertSame(200, $this->signIn('me@example.test', 'right')->getStatusCode());
        self::assertSame(429, $this->signIn('customer25@example.test')->getStatusCode());
    }

    public function testTheShopAndTheBackOfficeShareTheCount(): void
    {
        $this->stubSignIn();
        $this->signInTimes(3, 'ada@example.test');
        $this->signInTimes(2, 'ada@example.test', route: '/api/v1/admin/login');

        self::assertSame(429, $this->signIn('ada@example.test', route: '/api/v1/admin/login')->getStatusCode());
    }

    public function testAForwardedAddressIsNotBelievedWithoutATrustedProxy(): void
    {
        $this->stubSignIn();
        // Without SYMFONY_TRUSTED_PROXIES, X-Forwarded-For is ignored: a client cannot
        // pose as five different addresses to get five times the attempts.
        foreach (range(1, 5) as $i) {
            $this->signIn('ada@example.test', server: ['HTTP_X_FORWARDED_FOR' => '192.0.2.'.$i]);
        }

        self::assertSame(429, $this->signIn('ada@example.test', server: ['HTTP_X_FORWARDED_FOR' => '192.0.2.99'])->getStatusCode());
    }

    public function testAnInvalidRequestIsNotCounted(): void
    {
        $this->stubSignIn();
        foreach (range(1, 6) as $i) {
            $this->client->request('POST', '/api/v1/shop/login', server: ['REMOTE_ADDR' => self::ADDRESS, 'CONTENT_TYPE' => 'application/json'], content: '{"email": "ada@example.test", "password": " "}');
            self::assertSame(422, $this->client->getResponse()->getStatusCode());
        }

        // Refused before any password is checked: nothing was counted.
        self::assertSame(401, $this->signIn('ada@example.test')->getStatusCode());
    }

    public function testTheEleventhRegistrationFromAnAddressIsRefused(): void
    {
        $useCase = $this->createMock(RegisterUserUseCase::class);
        // Ten from the first address, then one from another: never the refused one.
        $useCase->expects(self::exactly(11))->method('execute');
        static::getContainer()->set(RegisterUserUseCase::class, $useCase);

        foreach (range(1, 10) as $i) {
            self::assertSame(201, $this->register('customer'.$i.'@example.test')->getStatusCode());
        }
        $response = $this->register('customer11@example.test');

        $this->assertTooManyRequests($response, AuthenticationThrottle::REGISTRATION_MESSAGE, self::REGISTRATION_WINDOW);
        self::assertSame(201, $this->register('customer12@example.test', self::OTHER_ADDRESS)->getStatusCode());
    }

    public function testATakenEmailCountsAsARegistrationAttempt(): void
    {
        // Probing which emails have an account (409) is slowed down as much as creating accounts.
        $useCase = $this->createStub(RegisterUserUseCase::class);
        $useCase->method('execute')->willThrowException(new DuplicateEmailException('taken@example.test'));
        static::getContainer()->set(RegisterUserUseCase::class, $useCase);

        foreach (range(1, 10) as $i) {
            self::assertSame(409, $this->register('customer'.$i.'@example.test')->getStatusCode());
        }

        self::assertSame(429, $this->register('customer11@example.test')->getStatusCode());
    }

    /**
     * Wrong passwords are refused (401); $rightPassword, if any, signs in.
     */
    private function stubSignIn(?string $rightPassword = null): void
    {
        $useCase = $this->createStub(LoginUserUseCase::class);
        $useCase->method('execute')->willReturnCallback(static function (LoginUserRequestDto $dto) use ($rightPassword): array {
            if (null === $rightPassword || $dto->password !== $rightPassword) {
                throw new InvalidCredentialsException();
            }

            return ['token' => 'a-jwt', 'refresh_token' => 'a-refresh-token'];
        });
        static::getContainer()->set(LoginUserUseCase::class, $useCase);
    }

    /**
     * @return list<int> the status of each attempt
     */
    private function signInTimes(int $times, string $email, string $route = '/api/v1/shop/login'): array
    {
        $statuses = [];
        foreach (range(1, $times) as $i) {
            $statuses[] = $this->signIn($email, route: $route)->getStatusCode();
        }

        return $statuses;
    }

    /**
     * @param array<string, string> $server
     */
    private function signIn(string $email, string $password = 'wrong', string $address = self::ADDRESS, string $route = '/api/v1/shop/login', array $server = []): Response
    {
        $this->client->request('POST', $route, server: $server + ['REMOTE_ADDR' => $address, 'CONTENT_TYPE' => 'application/json'], content: json_encode(['email' => $email, 'password' => $password], \JSON_THROW_ON_ERROR));

        return $this->client->getResponse();
    }

    private function register(string $email, string $address = self::ADDRESS): Response
    {
        $this->client->request('POST', '/api/v1/shop/register', server: ['REMOTE_ADDR' => $address, 'CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => $email,
            'password' => 'a-long-enough-password',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
        ], \JSON_THROW_ON_ERROR));

        return $this->client->getResponse();
    }

    private function assertTooManyRequests(Response $response, string $message, string $window): void
    {
        self::assertSame(429, $response->getStatusCode());
        self::assertSame(['error' => $message], json_decode((string) $response->getContent(), true));
        $retryAfter = (int) $response->headers->get('Retry-After');
        $windowInSeconds = (new \DateTimeImmutable('@0'))->modify('+'.$window)->getTimestamp();
        self::assertGreaterThan(0, $retryAfter);
        // The sliding window frees a slot just after its end: a second more, rounded up.
        self::assertLessThanOrEqual($windowInSeconds + 1, $retryAfter);
    }
}
