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

namespace Wlindabla\UserBundle\Infrastructure\Persistance;

use Psr\Log\LoggerInterface;
use Wlindabla\ShareServiceBundle\Messenger\QueueHandler\AsyncMethodDispatcherInterface;
use Wlindabla\ShareServiceBundle\Security\Generator\TokenGeneratorInterface;
use Wlindabla\ShareServiceBundle\Security\Hash\TokenHasherInterface;
use Wlindabla\UserBundle\Domain\EmailChange\Application\Manager\EmailChangeRequestServiceInterface;
use Wlindabla\UserBundle\Domain\EmailChange\Application\Manager\EmailChangeValidatorPolicyInterface;
use Wlindabla\UserBundle\Domain\EmailChange\Enum\EmailChangeCancelReason;
use Wlindabla\UserBundle\Domain\EmailChange\Enum\EmailChangeRequestStatus;
use Wlindabla\UserBundle\Domain\EmailChange\Enum\EmailChangeValidationOutcome;
use Wlindabla\UserBundle\Domain\EmailChange\Model\EmailChangeRequestInterface;
use Wlindabla\UserBundle\Domain\EmailChange\Model\Persistance\EmailChangeRequestRepositoryInterface;
use Wlindabla\UserBundle\Domain\EmailChange\Model\Persistance\EmailChangeValidationRepositoryInterface;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Domain\User\Exception\InvalidTokenException;
use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;
use Wlindabla\UserBundle\Domain\UserSession\Application\Manager\UserSessionManagerInterface;
use Wlindabla\UserBundle\Domain\UserSession\Enum\UserSessionRevokedReason;
use Wlindabla\UserBundle\Infrastructure\Service\EmailChangeRequestMailerService;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final readonly class EmailChangeRequestService implements EmailChangeRequestServiceInterface
{
    public function __construct(
        private UserManagerInterface $userManager,
        private EmailChangeRequestRepositoryInterface $emailChangeRequestRepository,
        private EmailChangeValidationRepositoryInterface $emailChangeValidationRepository,
        private EmailChangeValidatorPolicyInterface $validatorPolicy,
        private UserSessionManagerInterface $userSessionManager,
        private TokenGeneratorInterface $tokenGenerator,
        private TokenHasherInterface $tokenHasher,
        private AsyncMethodDispatcherInterface $asyncMethodDispatcher,
        private LoggerInterface $logger,
        private int $tokenLifetimeSeconds,
        private int $retryCooldownSeconds,
        /** @var list<string> */
        private array $exemptRolesOnSelf,
        private string $recoveryInitiatorRole,
    ) {
    }

    public function requestChange(
        BaseUserInterface $requestedBy, 
        BaseUserInterface $targetUser, 
        string $newEmail): void
    {
        $this->guardNoActiveRequest($targetUser);
        $this->guardCooldownElapsed($targetUser);

        $oldEmail = $targetUser->getEmail();

        if (null === $oldEmail) {
            throw new \DomainException('The target account has no current email address to confirm.');
        }

        $plainOldToken = $this->tokenGenerator->generate(TokenGeneratorInterface::DEFAULT_EMAIL_TOKEN_LENGTH);
        $plainNewToken = $this->tokenGenerator->generate(TokenGeneratorInterface::DEFAULT_EMAIL_TOKEN_LENGTH);

        $request = $this->newRequest(
            requestedBy: $requestedBy,
            targetUser: $targetUser,
            oldEmail: $oldEmail,
            newEmail: $newEmail,
            oldEmailTokenHash: $this->tokenHasher->hash($plainOldToken),
            newEmailTokenHash: $this->tokenHasher->hash($plainNewToken),
            requiresDoubleValidation: $this->calculateRequiresDoubleValidation($requestedBy, $targetUser),
        );

        $this->emailChangeRequestRepository->save($request);

        $this->asyncMethodDispatcher->dispatch(EmailChangeRequestMailerService::class, 'sendOldEmailNotification', [$request->getId(), $plainOldToken]);
        $this->asyncMethodDispatcher->dispatch(EmailChangeRequestMailerService::class, 'sendNewEmailConfirmation', [$request->getId(), $plainNewToken]);

        $this->logger->info('Email change request created.', [
            'request_id' => $request->getId(),
            'requested_by_id' => $requestedBy->getId(),
            'target_user_id' => $targetUser->getId(),
            'requires_double_validation' => $request->requiresDoubleValidation(),
        ]);
    }

    public function requestRecovery(
        BaseUserInterface $requestedBy, 
        BaseUserInterface $targetUser, 
        string $newEmail): void
    {
        if (!$requestedBy->hasRole($this->recoveryInitiatorRole)) {
            $this->logger->warning('Recovery procedure initiation attempted by an unauthorized account.', [
                'requested_by_id' => $requestedBy->getId(),
                'target_user_id' => $targetUser->getId(),
            ]);

            throw new \DomainException('You are not authorized to initiate a recovery procedure.');
        }

        $this->guardNoActiveRequest($targetUser);
        $this->guardCooldownElapsed($targetUser);

        $oldEmail = $targetUser->getEmail();

        if (null === $oldEmail) {
            throw new \DomainException('The target account has no current email address on record.');
        }

        $plainNewToken = $this->tokenGenerator->generate(TokenGeneratorInterface::DEFAULT_EMAIL_TOKEN_LENGTH);

        $request = $this->newRequest(
            requestedBy: $requestedBy,
            targetUser: $targetUser,
            oldEmail: $oldEmail,
            newEmail: $newEmail,
            oldEmailTokenHash: null,
            newEmailTokenHash: $this->tokenHasher->hash($plainNewToken),
            requiresDoubleValidation: true, // never exempt on recovery
            isRecovery: true,
        );

        $this->emailChangeRequestRepository->save($request);

        $this->asyncMethodDispatcher->dispatch(EmailChangeRequestMailerService::class, 'sendNewEmailConfirmation', [$request->getId(), $plainNewToken]);

        $this->logger->warning('Account recovery procedure initiated (lost access to the old address).', [
            'request_id' => $request->getId(),
            'requested_by_id' => $requestedBy->getId(),
            'target_user_id' => $targetUser->getId(),
        ]);
    }

    public function confirmOldEmail(string $rawToken, int $requestId): void
    {
        $request = $this->getConfirmableRequest($requestId);

        if ($request->isRecovery()) {
            throw new \DomainException('This request is a recovery procedure: the old address cannot be confirmed.');
        }

        if (!$this->tokenHasher->verify($rawToken, $request->getOldEmailTokenHash() ?? '')) {
            $this->logger->error('Old-email confirmation attempted with an invalid token.', ['request_id' => $requestId]);

            throw InvalidTokenException::invalidToken();
        }

        $request->confirmOldEmail();
        $this->emailChangeRequestRepository->save($request, flush: false);
        $this->tryFinalize($request);
    }

    public function confirmNewEmail(string $rawToken, int $requestId): void
    {
        $request = $this->getConfirmableRequest($requestId);

        if (!$this->tokenHasher->verify($rawToken, $request->getNewEmailTokenHash() ?? '')) {
            $this->logger->error('New-email confirmation attempted with an invalid token.', ['request_id' => $requestId]);

            throw InvalidTokenException::invalidToken();
        }

        $request->confirmNewEmail();
        $this->emailChangeRequestRepository->save($request, flush: false);
        $this->tryFinalize($request);
    }

    public function cancelByNotMe(string $rawToken, int $requestId): void
    {
        $request = $this->getConfirmableRequest($requestId);

        if ($request->isRecovery()) {
            throw new \DomainException('This request is a recovery procedure: this link does not apply.');
        }

        if (!$this->tokenHasher->verify($rawToken, $request->getOldEmailTokenHash() ?? '')) {
            $this->logger->error('"Not me" cancellation attempted with an invalid token.', ['request_id' => $requestId]);

            throw InvalidTokenException::invalidToken();
        }

        $request->markCancelled(EmailChangeCancelReason::NOT_ME);
        $this->emailChangeRequestRepository->save($request);

        $this->logger->warning('Email change request reported as "not me" — security alert.', [
            'request_id' => $requestId,
            'target_user_id' => $request->getTargetUser()?->getId(),
        ]);

        $this->asyncMethodDispatcher->dispatch(EmailChangeRequestMailerService::class, 'alertOnNotMe', [$request->getId()]);
    }

    public function submitValidation(
        EmailChangeRequestInterface $request,
        BaseUserInterface $validator,
        bool $approved,
        ?string $comment = null,
    ): void {
        if (EmailChangeRequestStatus::AwaitingValidation !== $request->getStatus()) {
            throw new \DomainException('This request is not awaiting validation.');
        }

        if ($this->emailChangeValidationRepository->hasAlreadyVoted($request, $validator)) {
            throw new \DomainException('You have already voted on this request.');
        }

        $role = $this->validatorPolicy->resolveEligibility($request, $validator);

        if (null === $role) {
            $this->logger->warning('Validation attempted by an ineligible account.', [
                'request_id' => $request->getId(),
                'validator_id' => $validator->getId(),
            ]);

            throw new \DomainException('You are not authorized to validate this request.');
        }

        if (!$approved && (null === $comment || '' === trim($comment))) {
            throw new \DomainException('A reason is required when rejecting.');
        }

        $entityClass = $this->emailChangeValidationRepository->getClassName();
        /** @var \Wlindabla\UserBundle\Domain\EmailChange\Model\EmailChangeValidationInterface $validation */
        $validation = new $entityClass($request, $validator, $role, $approved, $comment);
        $this->emailChangeValidationRepository->save($validation);

        $this->logger->info('Validation decision recorded on an email change request.', [
            'request_id' => $request->getId(),
            'validator_id' => $validator->getId(),
            'validator_role' => $role,
            'approved' => $approved,
        ]);

        $outcome = $this->validatorPolicy->evaluate($request, $this->emailChangeValidationRepository->findByRequest($request));

        match ($outcome) {
            EmailChangeValidationOutcome::SATISFIED => $this->applyChange($request),
            EmailChangeValidationOutcome::REJECTED => $this->rejectByValidators($request),
            EmailChangeValidationOutcome::PENDING => null,
        };
    }

    private function newRequest(
        BaseUserInterface $requestedBy,
        BaseUserInterface $targetUser,
        string $oldEmail,
        string $newEmail,
        ?string $oldEmailTokenHash,
        string $newEmailTokenHash,
        bool $requiresDoubleValidation,
        bool $isRecovery = false,
    ): EmailChangeRequestInterface {
        $entityClass = $this->emailChangeRequestRepository->getClassName();

        return new $entityClass(
            $requestedBy,
            $targetUser,
            $oldEmail,
            $newEmail,
            $oldEmailTokenHash,
            $newEmailTokenHash,
            $requiresDoubleValidation,
            (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify(\sprintf('+%d seconds', $this->tokenLifetimeSeconds)),
            $isRecovery,
        );
    }

    private function guardNoActiveRequest(BaseUserInterface $targetUser): void
    {
        $existing = $this->emailChangeRequestRepository->findActiveForTargetUser($targetUser);

        if (null !== $existing) {
            $this->logger->notice('Email change request refused: an active request already exists.', [
                'target_user_id' => $targetUser->getId(),
                'existing_request_id' => $existing->getId(),
            ]);

            throw new \DomainException('An email change request is already in progress for this account.');
        }
    }

    private function guardCooldownElapsed(BaseUserInterface $targetUser): void
    {
        $lastCancelled = $this->emailChangeRequestRepository->findHistoryForTargetUser($targetUser)[0] ?? null;

        if (null === $lastCancelled || null === $lastCancelled->getCancelledReason()) {
            return;
        }

        $cooldownEnd = $lastCancelled->getRequestedAt()->modify(\sprintf('+%d seconds', $this->retryCooldownSeconds));

        if ($cooldownEnd > new \DateTimeImmutable('now', new \DateTimeZone('UTC'))) {
            $this->logger->notice('Email change request refused: cooldown still active.', [
                'target_user_id' => $targetUser->getId(),
            ]);

            throw new \DomainException('Please wait before submitting a new request for this account.');
        }
    }

    private function rejectByValidators(EmailChangeRequestInterface $request): void
    {
        $request->markCancelled(EmailChangeCancelReason::REJECTED_BY_VALIDATOR);
        $this->emailChangeRequestRepository->save($request);

        $this->logger->info('Email change request rejected by a validator.', ['request_id' => $request->getId()]);

        $this->asyncMethodDispatcher->dispatch(EmailChangeRequestMailerService::class, 'notifyRequesterOutcome', [$request->getId(), false]);
    }

    private function tryFinalize(EmailChangeRequestInterface $request): void
    {
        if (!$request->isFullyConfirmed()) {
            return;
        }

        if ($request->requiresDoubleValidation()) {
            $request->markAwaitingValidation();
            $this->emailChangeRequestRepository->save($request);

            $this->logger->info('Email change request awaiting double validation.', ['request_id' => $request->getId()]);

            $this->asyncMethodDispatcher->dispatch(EmailChangeRequestMailerService::class, 'notifyValidatorsPending', [$request->getId()]);

            return;
        }

        $this->applyChange($request);
    }

    private function applyChange(EmailChangeRequestInterface $request): void
    {
        $targetUser = $request->getTargetUser();

        if (null === $targetUser) {
            throw new \DomainException('Target account not found while applying the email change.');
        }

        $oldIdentifier = $targetUser->getUserIdentifier();

        $targetUser->setEmail($request->getNewEmail());
        $this->userManager->save($targetUser);

        $request->markConfirmed();
        $this->emailChangeRequestRepository->save($request);

        // Remember-me needs no explicit invalidation: BaseUserInterface::
        // getUserIdentifier() is the email itself, so a cookie issued
        // under the old address naturally fails to resolve any user on
        // its next use. Only already-open PHP sessions need an explicit
        // revocation.
        $this->userSessionManager->revokeAllActiveSessions($oldIdentifier, UserSessionRevokedReason::SECURITY_EMAIL_CHANGED);

        $this->logger->info('Email change applied, sessions invalidated.', [
            'request_id' => $request->getId(),
            'target_user_id' => $targetUser->getId(),
        ]);

        $this->asyncMethodDispatcher->dispatch(EmailChangeRequestMailerService::class, 'sendChangeAppliedNotification', [$request->getId()]);
        $this->asyncMethodDispatcher->dispatch(EmailChangeRequestMailerService::class, 'notifyRequesterOutcome', [$request->getId(), true]);
    }

    private function getConfirmableRequest(int $requestId): EmailChangeRequestInterface
    {
        $request = $this->emailChangeRequestRepository->find($requestId);

        if (null === $request) {
            throw InvalidTokenException::userNotFound();
        }

        if ($request->isExpired()) {
            $request->markExpired();
            $this->emailChangeRequestRepository->save($request);

            throw InvalidTokenException::expiredToken($request->getExpiresAt());
        }

        if ($request->getStatus()->isFinal()) {
            throw InvalidTokenException::alreadyUsedToken();
        }

        return $request;
    }

    private function calculateRequiresDoubleValidation(BaseUserInterface $requestedBy, BaseUserInterface $targetUser): bool
    {
        if ($requestedBy->getId() !== $targetUser->getId()) {
            return true;
        }

        foreach ($this->exemptRolesOnSelf as $role) {
            if ($targetUser->hasRole($role)) {
                return false;
            }
        }

        return true;
    }
}