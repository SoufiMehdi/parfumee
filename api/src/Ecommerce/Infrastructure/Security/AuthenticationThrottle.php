<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Http\RateLimiter\DefaultLoginRateLimiter;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * Brakes on the public authentication routes (audit S6): password guessing on the
 * sign-in, mass account creation on the registration. The limits are set in
 * config/packages/rate_limiter.yaml; a refused request answers 429 with Retry-After.
 *
 * Symfony's login_throttling cannot do it here: the sign-in goes through a controller
 * and LoginUserUseCase, not through the firewall. Its rule is reused as such
 * (DefaultLoginRateLimiter): a limit per email and address, which a successful
 * sign-in clears, and a wider one per address, which it does not.
 */
final readonly class AuthenticationThrottle
{
    public const string SIGN_IN_MESSAGE = 'Too many sign-in attempts. Try again later.';
    public const string REGISTRATION_MESSAGE = 'Too many accounts created from this address. Try again later.';

    private DefaultLoginRateLimiter $signIn;

    public function __construct(
        #[Autowire(service: 'limiter.login_per_address')]
        RateLimiterFactory $loginPerAddress,
        #[Autowire(service: 'limiter.login_per_account')]
        RateLimiterFactory $loginPerAccount,
        #[Target('registration_per_address')]
        private RateLimiterFactoryInterface $registrationPerAddress,
        // Hashes the keys, so that the store holds no email nor address. Without an
        // APP_SECRET, the sign-in and the registration fail (500) rather than run.
        #[Autowire('%kernel.secret%')]
        #[\SensitiveParameter]
        private string $secret,
    ) {
        $this->signIn = new DefaultLoginRateLimiter($loginPerAddress, $loginPerAccount, $secret);
    }

    /**
     * Counts a sign-in attempt before the password is checked, and refuses it once a
     * limit is reached: a blocked client can no longer test any password, even the right one.
     */
    public function consumeSignIn(Request $request, string $email): void
    {
        self::ensureAccepted($this->signIn->consume(self::withEmail($request, $email)), self::SIGN_IN_MESSAGE);
    }

    /**
     * After a successful sign-in: clears the limit of this email from this address only.
     */
    public function resetSignIn(Request $request, string $email): void
    {
        $this->signIn->reset(self::withEmail($request, $email));
    }

    /**
     * Counts every registration attempt from the address of the client, accepted or not.
     */
    public function consumeRegistration(Request $request): void
    {
        $key = hash_hmac('sha256', (string) $request->getClientIp(), $this->secret);

        self::ensureAccepted($this->registrationPerAddress->create($key)->consume(), self::REGISTRATION_MESSAGE);
    }

    private static function withEmail(Request $request, string $email): Request
    {
        // DefaultLoginRateLimiter reads the identifier where the firewall would have put it.
        $request->attributes->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        return $request;
    }

    private static function ensureAccepted(RateLimit $limit, string $message): void
    {
        if (!$limit->isAccepted()) {
            // Retry-After, in seconds: what a front reads to tell how long to wait.
            // Rounded up, so that a client waiting that long is never refused again.
            $wait = (float) $limit->getRetryAfter()->format('U.u') - microtime(true);

            throw new TooManyRequestsHttpException(max(1, (int) ceil($wait)), $message);
        }
    }
}
