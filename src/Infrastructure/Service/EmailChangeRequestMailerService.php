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

namespace Wlindabla\UserBundle\Infrastructure\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Wlindabla\MailingBundle\Mailing\MailerManagerInterface;
use Wlindabla\MailingBundle\Mailing\PriorityInterface;
use Wlindabla\ShareServiceBundle\Security\Encryption\IdEncryptionInterface;
use Wlindabla\UserBundle\Domain\EmailChange\Application\Manager\EmailChangeValidatorPolicyInterface;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Infrastructure\Doctrine\Repository\AbstractEmailChangeRequestRepository;
use Wlindabla\UserBundle\Infrastructure\Templating\TemplateRegistryInterface;

/**
 * @internal meant to be called asynchronously via
 *           AsyncMethodDispatcherInterface, never directly during the
 *           HTTP request.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final readonly class EmailChangeRequestMailerService
{
    public function __construct(
        private AbstractEmailChangeRequestRepository $emailChangeRequestRepository,
        private UserManagerInterface $userManager,
        private EmailChangeValidatorPolicyInterface $validatorPolicy,
        private MailerManagerInterface $mailerManager,
        private UrlGeneratorInterface $urlGenerator,
        private ParameterBagInterface $parameterBag,
        private IdEncryptionInterface $idEncryptionService,
        private TemplateRegistryInterface $templateRegistry,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
    ) {
    }

    public function sendOldEmailNotification(int $requestId, string $plainToken): void
    {
        $request = $this->emailChangeRequestRepository->find($requestId);

        if (null === $request) {
            $this->logger->warning('Old-email notification cancelled: request not found.', ['request_id' => $requestId]);

            return;
        }

        $encryptedId = $this->idEncryptionService->encryptId($request->getId());
        $siteName = $this->parameterBag->get('app.site_name');

        $confirmationUrl = $this->urlGenerator->generate(
            'app_email_change_confirm_old',
            ['token' => $plainToken, 'id' => $encryptedId],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $notMeUrl = $this->urlGenerator->generate(
            'app_email_change_not_me',
            ['token' => $plainToken, 'id' => $encryptedId],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $this->mailerManager->notifyBySystem(
            $request->getOldEmail(),
            $this->translator->trans('email_change.old_notification_subject', ['%site_name%' => $siteName], 'WlindablaUserBundle'),
            $this->templateRegistry->get('email_change_old_notification'),
            [
                'target_username' => $request->getTargetUser()?->getUsername(),
                'requested_by_username' => $request->getRequestedBy()?->getUsername(),
                'new_email' => $this->maskEmail($request->getNewEmail()),
                'confirmation_url' => $confirmationUrl,
                'not_me_url' => $notMeUrl,
                'expires_at' => $request->getExpiresAt(),
                'site_name' => $siteName,
            ],
            PriorityInterface::PRIORITY_HIGHEST,
        );

        $this->logger->info('Old-email change notification sent.', ['request_id' => $requestId]);
    }

    public function sendNewEmailConfirmation(int $requestId, string $plainToken): void
    {
        $request = $this->emailChangeRequestRepository->find($requestId);

        if (null === $request) {
            $this->logger->warning('New-email confirmation cancelled: request not found.', ['request_id' => $requestId]);

            return;
        }

        $encryptedId = $this->idEncryptionService->encryptId($request->getId());
        $siteName = $this->parameterBag->get('app.site_name');

        $confirmationUrl = $this->urlGenerator->generate(
            'app_email_change_confirm_new',
            ['token' => $plainToken, 'id' => $encryptedId],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $this->mailerManager->notifyBySystem(
            $request->getNewEmail(),
            $this->translator->trans('email_change.new_confirmation_subject', ['%site_name%' => $siteName], 'WlindablaUserBundle'),
            $this->templateRegistry->get('email_change_new_confirmation'),
            [
                'target_username' => $request->getTargetUser()?->getUsername(),
                'confirmation_url' => $confirmationUrl,
                'expires_at' => $request->getExpiresAt(),
                'site_name' => $siteName,
            ],
            PriorityInterface::PRIORITY_HIGHEST,
        );

        $this->logger->info('New-email confirmation sent.', ['request_id' => $requestId]);
    }

    public function sendChangeAppliedNotification(int $requestId): void
    {
        $request = $this->emailChangeRequestRepository->find($requestId);

        if (null === $request) {
            $this->logger->warning('"Change applied" notification cancelled: request not found.', ['request_id' => $requestId]);

            return;
        }

        $siteName = $this->parameterBag->get('app.site_name');

        $this->mailerManager->notifyBySystem(
            $request->getOldEmail(),
            $this->translator->trans('email_change.applied_subject', ['%site_name%' => $siteName], 'WlindablaUserBundle'),
            $this->templateRegistry->get('email_change_applied'),
            [
                'target_username' => $request->getTargetUser()?->getUsername(),
                'new_email' => $this->maskEmail($request->getNewEmail()),
                'applied_at' => $request->getAppliedAt(),
                'site_name' => $siteName,
            ],
            PriorityInterface::PRIORITY_HIGHEST,
        );

        $this->logger->info('"Change applied" notification sent.', ['request_id' => $requestId]);
    }

    public function alertOnNotMe(int $requestId): void
    {
        $request = $this->emailChangeRequestRepository->find($requestId);

        if (null === $request) {
            $this->logger->warning('"Not me" alert cancelled: request not found.', ['request_id' => $requestId]);

            return;
        }

        // Who to alert on a "not me" report is, like validator eligibility,
        // entirely owned by the consuming application — reuse the same
        // policy rather than invent a second notion of "who's in charge".
        $recipients = $this->validatorPolicy->listEligibleValidatorEmails($request);

        if ([] === $recipients) {
            $this->logger->critical('No one to alert following a "not me" report.', ['request_id' => $requestId]);

            return;
        }

        $siteName = $this->parameterBag->get('app.site_name');

        $this->mailerManager->notifyBySystem(
            $recipients,
            $this->translator->trans('email_change.not_me_alert_subject', ['%site_name%' => $siteName], 'WlindablaUserBundle'),
            $this->templateRegistry->get('email_change_not_me_alert'),
            [
                'target_username' => $request->getTargetUser()?->getUsername(),
                'target_user_id' => $request->getTargetUser()?->getId(),
                'requested_by_username' => $request->getRequestedBy()?->getUsername(),
                'old_email' => $request->getOldEmail(),
                'new_email' => $request->getNewEmail(),
                'requested_at' => $request->getRequestedAt(),
                'site_name' => $siteName,
            ],
            PriorityInterface::PRIORITY_HIGHEST,
        );

        $this->logger->info('"Not me" alert sent.', ['request_id' => $requestId, 'recipient_count' => \count($recipients)]);
    }

    public function notifyValidatorsPending(int $requestId): void
    {
        $request = $this->emailChangeRequestRepository->find($requestId);

        if (null === $request) {
            $this->logger->warning('Validator notification cancelled: request not found.', ['request_id' => $requestId]);

            return;
        }

        $recipients = $this->validatorPolicy->listEligibleValidatorEmails($request);

        if ([] === $recipients) {
            $this->logger->critical('No eligible validator found for a request awaiting double validation.', ['request_id' => $requestId]);

            return;
        }

        $siteName = $this->parameterBag->get('app.site_name');

        $this->mailerManager->notifyBySystem(
            $recipients,
            $this->translator->trans('email_change.validation_pending_subject', ['%site_name%' => $siteName], 'WlindablaUserBundle'),
            $this->templateRegistry->get('email_change_validation_pending'),
            [
                'target_username' => $request->getTargetUser()?->getUsername(),
                'requested_by_username' => $request->getRequestedBy()?->getUsername(),
                'new_email' => $this->maskEmail($request->getNewEmail()),
                'requested_at' => $request->getRequestedAt(),
                'is_recovery' => $request->isRecovery(),
                'site_name' => $siteName,
            ],
            PriorityInterface::PRIORITY_HIGH,
        );

        $this->logger->info('Validators notified of a request awaiting double validation.', [
            'request_id' => $requestId,
            'recipient_count' => \count($recipients),
        ]);
    }

    public function notifyRequesterOutcome(int $requestId, bool $approved): void
    {
        $request = $this->emailChangeRequestRepository->find($requestId);

        if (null === $request) {
            $this->logger->warning('Requester notification cancelled: request not found.', ['request_id' => $requestId]);

            return;
        }

        $requestedBy = $request->getRequestedBy();
        $targetUser = $request->getTargetUser();

        if (null === $requestedBy || null === $requestedBy->getEmail()) {
            return;
        }

        // The account holder is already notified elsewhere (applyChange()
        // or rejectByValidators()) — avoid a duplicate email.
        if (null !== $targetUser && $requestedBy->getId() === $targetUser->getId()) {
            return;
        }

        $siteName = $this->parameterBag->get('app.site_name');

        $this->mailerManager->notifyBySystem(
            $requestedBy->getEmail(),
            $this->translator->trans(
                $approved ? 'email_change.outcome_approved_subject' : 'email_change.outcome_rejected_subject',
                ['%site_name%' => $siteName],
                'WlindablaUserBundle',
            ),
            $this->templateRegistry->get('email_change_validation_outcome'),
            [
                'requested_by_username' => $requestedBy->getUsername(),
                'target_username' => $targetUser?->getUsername(),
                'approved' => $approved,
                'site_name' => $siteName,
            ],
            PriorityInterface::PRIORITY_NORMAL,
        );

        $this->logger->info('Requester notified of the double-validation outcome.', ['request_id' => $requestId, 'approved' => $approved]);
    }

    private function maskEmail(?string $email): string
    {
        if (null === $email || !\str_contains($email, '@')) {
            return '***';
        }

        [$local, $domain] = \explode('@', $email, 2);

        return \sprintf('%s***@%s', mb_substr($local, 0, 1), $domain);
    }
}