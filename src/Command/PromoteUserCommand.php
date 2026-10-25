<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Registration only ever creates ROLE_USER accounts, and nothing in the backoffice can hand out a
 * role yet, so the first super admin (and every moderator after it) is made from the console.
 */
#[AsCommand(name: 'app:user:promote', description: 'Grant a registered user the moderator or super admin role.')]
final class PromoteUserCommand extends Command
{
    private const array ROLES = [
        'moderator' => User::ROLE_MODERATOR,
        'super_admin' => User::ROLE_SUPER_ADMIN,
    ];

    public function __construct(private readonly UserRepository $users)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'E-mail address the account was registered with.')
            ->addArgument('role', InputArgument::REQUIRED, 'One of: '.implode(', ', array_keys(self::ROLES)));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = (string) $input->getArgument('email');
        $role = (string) $input->getArgument('role');

        if (!isset(self::ROLES[$role])) {
            $io->error(\sprintf('Unknown role "%s"; use one of: %s.', $role, implode(', ', array_keys(self::ROLES))));

            return Command::INVALID;
        }

        $user = $this->users->findOneByEmail($email);

        if (null === $user) {
            $io->error(\sprintf('No account is registered with "%s".', $email));

            return Command::FAILURE;
        }

        $user->addRole(self::ROLES[$role]);
        $this->users->save($user);

        $io->success(\sprintf('%s now holds %s.', $user->getEmail(), self::ROLES[$role]));

        return Command::SUCCESS;
    }
}
