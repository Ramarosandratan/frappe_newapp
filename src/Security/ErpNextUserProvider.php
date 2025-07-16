<?php

namespace App\Security;

use App\Security\User;
use App\Service\ErpNextService;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Psr\Log\LoggerInterface;

class ErpNextUserProvider implements UserProviderInterface
{
    private $erpNextService;
    private $logger;

    public function __construct(ErpNextService $erpNextService, LoggerInterface $logger)
    {
        $this->erpNextService = $erpNextService;
        $this->logger = $logger;
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $this->logger->info('Loading user by identifier', ['identifier' => $identifier]);
        
        // Gérer le cas spécial de l'utilisateur Administrator
        if ($identifier === 'Administrator') {
            $this->logger->info('Loading Administrator user');
            return new User('Administrator', 'Administrator');
        }
        
        // Essayer de trouver l'utilisateur par email
        $userData = $this->erpNextService->findUserByEmail($identifier);

        if ($userData) {
            $this->logger->info('User found by email', ['email' => $userData['email'] ?? 'N/A']);
            return new User($userData['email'] ?? $userData['name'], $userData['full_name'] ?? $userData['name']);
        }

        // Si pas trouvé par email, essayer de récupérer directement par nom
        try {
            $userData = $this->erpNextService->getResource('User', $identifier);
            if ($userData) {
                $this->logger->info('User found by name', ['name' => $userData['name'] ?? 'N/A']);
                return new User($userData['email'] ?? $userData['name'], $userData['full_name'] ?? $userData['name']);
            }
        } catch (\Exception $e) {
            $this->logger->warning('Failed to load user by name', ['identifier' => $identifier, 'error' => $e->getMessage()]);
        }

        $this->logger->error('User not found', ['identifier' => $identifier]);
        throw new UserNotFoundException("User not found: $identifier");
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        $this->logger->info('Refreshing user', ['identifier' => $user->getUserIdentifier()]);
        
        // Pour le refresh, on peut simplement retourner l'utilisateur tel quel
        // sans faire d'appel API, car il a déjà été validé lors de l'authentification
        if ($user instanceof User) {
            $this->logger->info('User refreshed successfully', ['identifier' => $user->getUserIdentifier()]);
            return $user;
        }
        
        // Fallback vers loadUserByIdentifier si nécessaire
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }
}