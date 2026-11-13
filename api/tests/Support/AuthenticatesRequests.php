<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Ecommerce\Infrastructure\Persistence\Entity\User\DoctrineUser;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Sends the requests of a WebTestCase client with a real JWT.
 *
 * The token goes through the real firewall (JWT authenticator, access_control,
 * voters). Only the user provider is swapped for an in-memory one.
 *
 * The container is rebuilt between two requests of the same client, which
 * drops the swapped provider: authenticate again before each request, or
 * send a single request per test.
 */
trait AuthenticatesRequests
{
    private const string USER_PROVIDER_SERVICE = 'security.user.provider.concrete.app_user_provider';

    /**
     * @param string[] $roles
     */
    private function authenticateAs(KernelBrowser $client, string $id, array $roles = []): DoctrineUser
    {
        $user = new DoctrineUser($id, $id.'@example.test', 'not-used', 'Test', 'User');
        $user->setRoles($roles);

        $provider = new InMemoryUserProvider();
        $provider->add($user);
        static::getContainer()->set(self::USER_PROVIDER_SERVICE, $provider);

        $token = static::getContainer()->get(JWTTokenManagerInterface::class)->create($user);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);

        return $user;
    }
}
