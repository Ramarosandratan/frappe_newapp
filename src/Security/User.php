<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface
{
    private ?string $email = null;
    private ?string $fullName = null;
    private array $roles = [];
    private ?string $password = null;

    public function __construct(string $email, string $fullName)
    {
        $this->email = $email;
        $this->fullName = $fullName;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    /**
     * @see UserInterface
     */
    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * Sérialisation pour la session
     */
    public function __serialize(): array
    {
        return [
            'email' => $this->email,
            'fullName' => $this->fullName,
            'roles' => $this->roles,
        ];
    }

    /**
     * Désérialisation depuis la session
     */
    public function __unserialize(array $data): void
    {
        $this->email = $data['email'];
        $this->fullName = $data['fullName'];
        $this->roles = $data['roles'] ?? [];
    }
}