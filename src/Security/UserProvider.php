<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Loads users by e-mail through the repository instead of the entity provider, because a login
 * form hands back whatever the visitor typed — "Ion@Example.com" has to find the stored
 * "ion@example.com" rather than fail the lookup.
 *
 * @implements UserProviderInterface<User>
 */
final readonly class UserProvider implements UserProviderInterface
{
    public function __construct(private UserRepository $users)
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->users->findOneByEmail($identifier);

        if (null === $user) {
            throw new UserNotFoundException(\sprintf('No user found for "%s".', $identifier));
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User || null === $user->getId()) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', get_debug_type($user)));
        }

        return $this->users->find($user->getId())
            ?? throw new UserNotFoundException(\sprintf('User with id "%d" no longer exists.', $user->getId()));
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }
}
