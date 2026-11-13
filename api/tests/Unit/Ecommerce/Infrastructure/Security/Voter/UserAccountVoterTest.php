<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Infrastructure\Security\Voter;

use App\Ecommerce\Infrastructure\Persistence\Entity\User\DoctrineUser;
use App\Ecommerce\Infrastructure\Security\Voter\UserAccountVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class UserAccountVoterTest extends TestCase
{
    private const string OWNER_ID = '11111111-1111-4111-8111-111111111111';
    private const string OTHER_ID = '22222222-2222-4222-8222-222222222222';

    public function testOwnerIsGranted(): void
    {
        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->vote($this->tokenFor(self::OWNER_ID), self::OWNER_ID),
        );
    }

    public function testOwnerIsGrantedWhateverTheCaseOfTheId(): void
    {
        $id = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->vote($this->tokenFor($id), strtoupper($id)),
        );
    }

    public function testAnotherCustomerIsDenied(): void
    {
        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->vote($this->tokenFor(self::OWNER_ID), self::OTHER_ID),
        );
    }

    public function testAdminIsGrantedOnAnyAccount(): void
    {
        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->vote($this->tokenFor(self::OWNER_ID), self::OTHER_ID, isAdmin: true),
        );
    }

    public function testAnonymousIsDenied(): void
    {
        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->vote(new NullToken(), self::OWNER_ID),
        );
    }

    public function testAUserThatIsNotAnAccountIsDeniedEvenWithTheAdminRole(): void
    {
        $user = new InMemoryUser('someone', null, ['ROLE_ADMIN']);
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->vote($token, self::OWNER_ID, isAdmin: true),
        );
    }

    public function testAbstainsOnAnotherAttribute(): void
    {
        $voter = new UserAccountVoter($this->createStub(AccessDecisionManagerInterface::class));

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $voter->vote($this->tokenFor(self::OWNER_ID), self::OWNER_ID, ['ROLE_USER']),
        );
    }

    public function testAbstainsWhenTheSubjectIsNotAnId(): void
    {
        $voter = new UserAccountVoter($this->createStub(AccessDecisionManagerInterface::class));

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $voter->vote($this->tokenFor(self::OWNER_ID), null, [UserAccountVoter::ACCESS]),
        );
    }

    private function vote(TokenInterface $token, string $accountId, bool $isAdmin = false): int
    {
        $decisionManager = $this->createStub(AccessDecisionManagerInterface::class);
        $decisionManager->method('decide')->willReturn($isAdmin);

        return new UserAccountVoter($decisionManager)->vote($token, $accountId, [UserAccountVoter::ACCESS]);
    }

    private function tokenFor(string $userId): TokenInterface
    {
        $user = new DoctrineUser($userId, 'customer@example.test', 'hash', 'Test', 'User');

        return new UsernamePasswordToken($user, 'main', $user->getRoles());
    }
}
