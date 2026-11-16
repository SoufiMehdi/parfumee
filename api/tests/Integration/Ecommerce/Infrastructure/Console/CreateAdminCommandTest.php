<?php

declare(strict_types=1);

namespace App\Tests\Integration\Ecommerce\Infrastructure\Console;

use App\Ecommerce\Application\DTO\User\LoginUserRequestDto;
use App\Ecommerce\Application\UseCase\User\LoginUserUseCase;
use App\Ecommerce\Domain\Model\User\Role;
use App\Ecommerce\Domain\Model\User\User;
use App\Ecommerce\Domain\Repository\User\UserRepositoryInterface;
use App\Tests\Factory\User\DoctrineUserFactory;
use App\Tests\Support\DatabaseTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Runs the real command against the real database: it is the only way to
 * create a back-office account, so it must work end to end.
 */
final class CreateAdminCommandTest extends DatabaseTestCase
{
    private const string PASSWORD = 'a-long-enough-password';

    private CommandTester $command;

    protected function setUp(): void
    {
        parent::setUp();
        \assert(null !== self::$kernel);
        $this->command = new CommandTester((new Application(self::$kernel))->find('app:user:create-admin'));
    }

    public function testCreatesAnAdministratorWhoCanSignIn(): void
    {
        $exitCode = $this->command->execute([
            'email' => 'admin@example.test',
            '--first-name' => 'Ada',
            '--last-name' => 'Admin',
            '--password' => self::PASSWORD,
        ], ['interactive' => false]);

        self::assertSame(Command::SUCCESS, $exitCode, $this->command->getDisplay());
        self::assertStringContainsString('created as administrator', $this->command->getDisplay());

        $user = $this->user('admin@example.test');
        self::assertSame('Ada', $user->getFirstName());
        self::assertSame('Admin', $user->getLastName());
        self::assertContains(Role::ADMIN, $user->getRoles());
        self::assertNotSame(self::PASSWORD, $user->getPasswordHash(), 'The password must be stored hashed.');

        $token = self::getContainer()->get(LoginUserUseCase::class)->execute(new LoginUserRequestDto('admin@example.test', self::PASSWORD));
        self::assertNotEmpty($token);
    }

    public function testCreatesAnEditorWithTheEditorOption(): void
    {
        $exitCode = $this->command->execute([
            'email' => 'editor@example.test',
            '--first-name' => 'Eve',
            '--last-name' => 'Editor',
            '--password' => self::PASSWORD,
            '--editor' => true,
        ], ['interactive' => false]);

        self::assertSame(Command::SUCCESS, $exitCode, $this->command->getDisplay());
        $roles = $this->user('editor@example.test')->getRoles();
        self::assertContains(Role::EDITOR, $roles);
        self::assertNotContains(Role::ADMIN, $roles);
    }

    public function testAsksForWhatIsMissing(): void
    {
        $this->command->setInputs(['admin@example.test', 'Ada', 'Admin', self::PASSWORD, self::PASSWORD]);

        $exitCode = $this->command->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode, $this->command->getDisplay());
        self::assertContains(Role::ADMIN, $this->user('admin@example.test')->getRoles());
        self::assertStringNotContainsString(self::PASSWORD, $this->command->getDisplay(), 'The password must not be echoed.');
    }

    public function testRefusesTwoDifferentPasswords(): void
    {
        $this->command->setInputs(['admin@example.test', 'Ada', 'Admin', self::PASSWORD, 'another-long-password']);

        $exitCode = $this->command->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('do not match', $this->command->getDisplay());
        self::assertNull($this->findUser('admin@example.test'));
    }

    public function testRefusesAShortPasswordAndAnInvalidEmail(): void
    {
        $exitCode = $this->command->execute([
            'email' => 'not-an-email',
            '--first-name' => 'Ada',
            '--last-name' => 'Admin',
            '--password' => 'short',
        ], ['interactive' => false]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('email:', $this->command->getDisplay());
        self::assertStringContainsString('password:', $this->command->getDisplay());
        self::assertNull($this->findUser('not-an-email'));
    }

    public function testRefusesAnEmailAlreadyTakenAndLeavesTheAccountUntouched(): void
    {
        DoctrineUserFactory::createOne(['email' => 'customer@example.test', 'password' => 'customer-hash']);
        $this->entityManager->clear();

        $exitCode = $this->command->execute([
            'email' => 'customer@example.test',
            '--first-name' => 'Ada',
            '--last-name' => 'Admin',
            '--password' => self::PASSWORD,
        ], ['interactive' => false]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('already taken', $this->command->getDisplay());
        $user = $this->user('customer@example.test');
        self::assertSame('customer-hash', $user->getPasswordHash());
        self::assertNotContains(Role::ADMIN, $user->getRoles());
    }

    public function testPromotesAnExistingAccountWithoutChangingItsPassword(): void
    {
        DoctrineUserFactory::createOne(['email' => 'customer@example.test', 'password' => 'customer-hash']);
        $this->entityManager->clear();

        $exitCode = $this->command->execute(['email' => 'customer@example.test', '--promote' => true], ['interactive' => false]);

        self::assertSame(Command::SUCCESS, $exitCode, $this->command->getDisplay());
        $user = $this->user('customer@example.test');
        self::assertContains(Role::ADMIN, $user->getRoles());
        self::assertSame('customer-hash', $user->getPasswordHash());
    }

    public function testPromotingAnUnknownAccountFails(): void
    {
        $exitCode = $this->command->execute(['email' => 'nobody@example.test', '--promote' => true], ['interactive' => false]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('nobody@example.test', $this->command->getDisplay());
        self::assertStringContainsString('not found', $this->command->getDisplay());
    }

    public function testFailsWithoutAPasswordWhenNotInteractive(): void
    {
        $exitCode = $this->command->execute([
            'email' => 'admin@example.test',
            '--first-name' => 'Ada',
            '--last-name' => 'Admin',
        ], ['interactive' => false]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertNull($this->findUser('admin@example.test'));
    }

    private function user(string $email): User
    {
        $user = $this->findUser($email);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function findUser(string $email): ?User
    {
        $this->entityManager->clear();

        return self::getContainer()->get(UserRepositoryInterface::class)->findByEmail($email);
    }
}
