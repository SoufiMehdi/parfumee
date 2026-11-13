<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Security\Voter;

use App\Ecommerce\Infrastructure\Persistence\Entity\User\DoctrineUser;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Grants access to a user account to its owner or to an administrator.
 *
 * The subject is the id of the targeted account (the `{id}` route parameter),
 * so the decision is taken before the account is loaded: a customer cannot
 * tell an account that belongs to someone else from one that does not exist.
 *
 * @extends Voter<string, string>
 */
final class UserAccountVoter extends Voter
{
    public const string ACCESS = 'USER_ACCOUNT_ACCESS';

    public function __construct(
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::ACCESS === $attribute && \is_string($subject);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof DoctrineUser) {
            return false;
        }

        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        // UUIDs are case-insensitive: the route may carry an upper-case id.
        return strtolower($user->getId()) === strtolower($subject);
    }
}
