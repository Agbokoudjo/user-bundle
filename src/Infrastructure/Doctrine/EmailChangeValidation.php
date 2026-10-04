<?php

declare(strict_types=1);

/*
 * This file is part of the project by AGBOKOUDJO Franck.
 *
 * (c) AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * Phone: +229 01 67 25 18 86
 * LinkedIn: https://www.linkedin.com/in/internationales-web-apps-services-120520193/
 * Github: https://github.com/Agbokoudjo/
 * Company: INTERNATIONALES WEB APPS & SERVICES
 *
 * For more information, please feel free to contact the author.
 */

namespace Wlindabla\UserBundle\Infrastructure\Doctrine;

use Doctrine\ORM\Mapping as ORM;
use Wlindabla\UserBundle\Domain\EmailChange\Model\EmailChangeRequestInterface;
use Wlindabla\UserBundle\Domain\EmailChange\Model\EmailChangeValidationInterface;
use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;

/**
 * Doctrine mapping for a validator's decision. Extend it in your
 * application. Uniqueness (one validator votes once per request) is
 * enforced by a UNIQUE(email_change_request_id, validator_id) constraint
 * on the concrete table — declare it in your own subclass's
 * #[ORM\UniqueConstraint] so it stays true under concurrent calls, not
 * just checked in PHP.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
#[ORM\MappedSuperclass]
abstract class EmailChangeValidation implements EmailChangeValidationInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected ?int $id = null;

    #[ORM\ManyToOne(targetEntity: EmailChangeRequestInterface::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    protected ?EmailChangeRequestInterface $emailChangeRequest = null;

    #[ORM\ManyToOne(targetEntity: 'Wlindabla\UserBundle\Infrastructure\Model\UserInterface')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    protected ?BaseUserInterface $validator = null;

    #[ORM\Column(type: 'string', length: 60)]
    protected string $validatorRole;

    #[ORM\Column(type: 'boolean')]
    protected bool $approved;

    #[ORM\Column(type: 'text', nullable: true)]
    protected ?string $comment = null;

    #[ORM\Column(type: 'datetime_immutable')]
    protected \DateTimeImmutable $decidedAt;

    public function __construct(
        EmailChangeRequestInterface $emailChangeRequest,
        BaseUserInterface $validator,
        string $validatorRole,
        bool $approved,
        ?string $comment = null,
    ) {
        $this->emailChangeRequest = $emailChangeRequest;
        $this->validator = $validator;
        $this->validatorRole = $validatorRole;
        $this->approved = $approved;
        $this->comment = $comment;
        $this->decidedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmailChangeRequest(): ?EmailChangeRequestInterface
    {
        return $this->emailChangeRequest;
    }

    public function getValidator(): ?BaseUserInterface
    {
        return $this->validator;
    }

    public function getValidatorRole(): string
    {
        return $this->validatorRole;
    }

    public function isApproved(): bool
    {
        return $this->approved;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getDecidedAt(): \DateTimeImmutable
    {
        return $this->decidedAt;
    }
}