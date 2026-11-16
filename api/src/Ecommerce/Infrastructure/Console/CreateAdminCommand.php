<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Console;

use App\Ecommerce\Application\DTO\User\CreateBackOfficeUserDto;
use App\Ecommerce\Application\UseCase\User\CreateBackOfficeUserUseCase;
use App\Ecommerce\Application\UseCase\User\GrantRoleUseCase;
use App\Ecommerce\Domain\Exception\DomainExceptionInterface;
use App\Ecommerce\Domain\Model\User\Role;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The only way to create a back-office account: no HTTP route can grant a role.
 */
#[AsCommand(
    name: 'app:user:create-admin',
    description: 'Create a back-office account (administrator or editor), or promote an existing account',
)]
final class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly CreateBackOfficeUserUseCase $createUser,
        private readonly GrantRoleUseCase $grantRole,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::OPTIONAL, 'Email of the account')
            ->addOption('first-name', null, InputOption::VALUE_REQUIRED, 'First name')
            ->addOption('last-name', null, InputOption::VALUE_REQUIRED, 'Last name')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Password. Prefer the interactive prompt: an option ends up in the shell history')
            ->addOption('editor', null, InputOption::VALUE_NONE, 'Give the editor role (catalogue only) instead of the administrator role')
            ->addOption('promote', null, InputOption::VALUE_NONE, 'Give the role to an account that already exists, without changing its password')
            ->setHelp(<<<'HELP'
                Create an administrator, answering the questions:

                    <info>php %command.full_name%</info>

                Create an editor, who can manage the catalogue but not the customer accounts:

                    <info>php %command.full_name% editor@example.com --editor</info>

                Turn an existing customer account into an administrator:

                    <info>php %command.full_name% customer@example.com --promote</info>
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $role = $input->getOption('editor') ? Role::EDITOR : Role::ADMIN;
        $roleLabel = Role::EDITOR === $role ? 'editor' : 'administrator';

        $email = $this->ask($io, $input->getArgument('email'), 'Email');
        if (null === $email) {
            return $this->missing($io, 'email');
        }

        try {
            if ($input->getOption('promote')) {
                $this->grantRole->execute($email, $role);
                $io->success(\sprintf('%s is now %s.', $email, $roleLabel));

                return Command::SUCCESS;
            }

            $firstName = $this->ask($io, $input->getOption('first-name'), 'First name');
            $lastName = $this->ask($io, $input->getOption('last-name'), 'Last name');
            $password = $input->getOption('password') ?? $this->askPassword($io, $input);
            foreach (['first name' => $firstName, 'last name' => $lastName, 'password' => $password] as $name => $value) {
                if (null === $value) {
                    return $this->missing($io, $name);
                }
            }

            $dto = new CreateBackOfficeUserDto($email, (string) $password, (string) $firstName, (string) $lastName, $role);

            $violations = $this->validator->validate($dto);
            if (\count($violations) > 0) {
                foreach ($violations as $violation) {
                    $io->error(\sprintf('%s: %s', $violation->getPropertyPath(), $violation->getMessage()));
                }

                return Command::FAILURE;
            }

            $id = $this->createUser->execute($dto);
        } catch (DomainExceptionInterface $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('Account %s created as %s (id %s).', $email, $roleLabel, $id));

        return Command::SUCCESS;
    }

    private function ask(SymfonyStyle $io, mixed $given, string $question): ?string
    {
        if (\is_string($given) && '' !== trim($given)) {
            return trim($given);
        }

        $answer = $io->ask($question);

        return \is_string($answer) && '' !== trim($answer) ? trim($answer) : null;
    }

    private function askPassword(SymfonyStyle $io, InputInterface $input): ?string
    {
        if (!$input->isInteractive()) {
            return null;
        }

        $password = $io->askHidden(\sprintf('Password (%d characters minimum)', CreateBackOfficeUserDto::PASSWORD_MIN_LENGTH));
        if (!\is_string($password)) {
            return null;
        }

        if ($password !== $io->askHidden('Password again')) {
            $io->error('The two passwords do not match.');

            return null;
        }

        return $password;
    }

    private function missing(SymfonyStyle $io, string $name): int
    {
        $io->error(\sprintf('The %s is required.', $name));

        return Command::FAILURE;
    }
}
