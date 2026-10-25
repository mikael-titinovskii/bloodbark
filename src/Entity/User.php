<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A registered owner. Contact details live here and are relayed to other users; nothing in this
 * class is ever rendered in search output or on a public page.
 *
 * The contact and consent columns are nullable because they are filled after the form has bound
 * its fields: the form's own NotNull constraints reject a missing answer, and RegistrationService
 * is the only writer of the consent record.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[ORM\UniqueConstraint(name: 'uniq_app_user_email', columns: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'register.email.taken')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const string ROLE_MODERATOR = 'ROLE_MODERATOR';
    public const string ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private string $email = '';

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $contactName = '';

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true, enumType: ContactType::class)]
    #[Assert\NotNull]
    private ?ContactType $contactType = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true, enumType: ContactWindow::class)]
    #[Assert\NotNull]
    private ?ContactWindow $contactWindow = null;

    /**
     * Who agreed to what, when — the record the processing register asks for. Written by
     * `acceptPolicy()` at the moment the account is created, never by the form.
     */
    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $consentAt = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $consentPolicyVersion = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $adultConfirmedAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Null is what the form writes for an empty field, and it means the same as an empty one: the
     * NotBlank constraint reports it. Coercing here keeps a half-filled POST a validation error
     * instead of a TypeError.
     */
    public function setEmail(?string $email): static
    {
        // One canonical form on the way in, so the provider's lookup and the unique index agree.
        $this->email = mb_strtolower(trim($email ?? ''));

        return $this;
    }

    public function getUserIdentifier(): string
    {
        // Never empty on a persisted account: the form makes the address required before a row can
        // exist. The assert is there for static analysis and vanishes when assertions are off.
        \assert('' !== $this->email);

        return $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /**
     * @param string $role one of the ROLE_* constants; every account is a ROLE_USER regardless
     */
    public function addRole(string $role): static
    {
        if (!\in_array($role, $this->roles, true)) {
            $this->roles[] = $role;
        }

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getContactName(): string
    {
        return $this->contactName;
    }

    public function setContactName(?string $contactName): static
    {
        $this->contactName = trim($contactName ?? '');

        return $this;
    }

    public function getContactType(): ?ContactType
    {
        return $this->contactType;
    }

    public function setContactType(?ContactType $contactType): static
    {
        $this->contactType = $contactType;

        return $this;
    }

    public function getContactWindow(): ?ContactWindow
    {
        return $this->contactWindow;
    }

    public function setContactWindow(?ContactWindow $contactWindow): static
    {
        $this->contactWindow = $contactWindow;

        return $this;
    }

    public function getConsentAt(): ?\DateTimeImmutable
    {
        return $this->consentAt;
    }

    public function getConsentPolicyVersion(): ?string
    {
        return $this->consentPolicyVersion;
    }

    /**
     * Records the explicit consent checkbox: the timestamp and the policy version it referred to.
     */
    public function acceptPolicy(\DateTimeImmutable $at, string $policyVersion): static
    {
        $this->consentAt = $at;
        $this->consentPolicyVersion = $policyVersion;

        return $this;
    }

    public function getAdultConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->adultConfirmedAt;
    }

    public function confirmAdulthood(\DateTimeImmutable $at): static
    {
        $this->adultConfirmedAt = $at;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
