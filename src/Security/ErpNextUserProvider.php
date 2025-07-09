<?php

namespace App\Security;

use App\Security\User;
use App\Service\ErpNextService;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

class ErpNextUserProvider implements UserProviderInterface
{
    private $erpNextService;

    public function __construct(ErpNextService $erpNextService)
    {
        $this->erpNextService = $erpNextService;
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $userData = $this->erpNextService->findUserByEmail($identifier);

        if ($userData) {
            return new User($userData['email'], $userData['full_name']);
        }

        throw new UserNotFoundException();
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }
}