<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'change_history')]
#[ORM\Index(columns: ['entity_type', 'entity_id'], name: 'idx_entity')]
#[ORM\Index(columns: ['changed_at'], name: 'idx_changed_at')]
#[ORM\Index(columns: ['user_id'], name: 'idx_user')]
class ChangeHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    private string $entityType;

    #[ORM\Column(type: 'string', length: 255)]
    private string $entityId;

    #[ORM\Column(type: 'string', length: 100)]
    private string $fieldName;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $oldValue = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $newValue = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $action; // CREATE, UPDATE, DELETE

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $userId = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $userName = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $changedAt;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reason = null;

    public function __construct()
    {
        $this->changedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): self
    {
        $this->entityType = $entityType;
        return $this;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function setEntityId(string $entityId): self
    {
        $this->entityId = $entityId;
        return $this;
    }

    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    public function setFieldName(string $fieldName): self
    {
        $this->fieldName = $fieldName;
        return $this;
    }

    public function getOldValue(): ?string
    {
        return $this->oldValue;
    }

    public function setOldValue(?string $oldValue): self
    {
        $this->oldValue = $oldValue;
        return $this;
    }

    public function getNewValue(): ?string
    {
        return $this->newValue;
    }

    public function setNewValue(?string $newValue): self
    {
        $this->newValue = $newValue;
        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;
        return $this;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    public function getUserName(): ?string
    {
        return $this->userName;
    }

    public function setUserName(?string $userName): self
    {
        $this->userName = $userName;
        return $this;
    }

    public function getChangedAt(): \DateTime
    {
        return $this->changedAt;
    }

    public function setChangedAt(\DateTime $changedAt): self
    {
        $this->changedAt = $changedAt;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): self
    {
        $this->reason = $reason;
        return $this;
    }

    /**
     * Méthodes utilitaires pour formater les valeurs
     */
    public function getFormattedOldValue(): string
    {
        if ($this->oldValue === null) {
            return 'N/A';
        }
        
        // Si c'est un nombre, le formater
        if (is_numeric($this->oldValue)) {
            return number_format((float)$this->oldValue, 2, ',', ' ') . ' €';
        }
        
        return $this->oldValue;
    }

    public function getFormattedNewValue(): string
    {
        if ($this->newValue === null) {
            return 'N/A';
        }
        
        // Si c'est un nombre, le formater
        if (is_numeric($this->newValue)) {
            return number_format((float)$this->newValue, 2, ',', ' ') . ' €';
        }
        
        return $this->newValue;
    }

    public function getActionLabel(): string
    {
        return match($this->action) {
            'CREATE' => 'Création',
            'UPDATE' => 'Modification',
            'DELETE' => 'Suppression',
            default => $this->action
        };
    }

    public function getActionBadgeClass(): string
    {
        return match($this->action) {
            'CREATE' => 'bg-success',
            'UPDATE' => 'bg-warning',
            'DELETE' => 'bg-danger',
            default => 'bg-secondary'
        };
    }
}