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

namespace Wlindabla\UserBundle\Infrastructure\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Wlindabla\ShareServiceBundle\Messenger\Message\ServiceMethodMessage;
use Wlindabla\UserBundle\Domain\User\Application\CommandHandler\UpdateUserProfileHandler;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Domain\User\Exception\EmailAlreadyVerifiedException;
use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;
use Wlindabla\UserBundle\Infrastructure\Service\SecureTokenService;

/**
 * Subscriber handling post-profile update logic via async Messenger worker events.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final readonly class UpdateProfileSuccessListener implements EventSubscriberInterface
{
    public function __construct(
        private UserManagerInterface $userManager,
        private SecureTokenService $secureTokenService,
        private LoggerInterface $logger
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageHandledEvent::class => 'onWorkerMessageHandled',
        ];
    }

    public function onWorkerMessageHandled(WorkerMessageHandledEvent $event): void
    {
        // Check if the processed message is the target service message
        $message = $event->getEnvelope()->getMessage();

        if (!$message instanceof ServiceMethodMessage) {
            return;
        }

        // Check if the executed message corresponds to the profile CommandHandler
        if ($message->getServiceName() !== UpdateUserProfileHandler::class 
            || $message->getMethod() !== 'handle') {
            return;
        }

        // Extract the target profile command parameter (User ID)
        $params = $message->getParams();
        if (empty($params) || (!\is_string($params[0]) && !\is_numeric($params[0]))) {
            // Log missing expected identifier parameter
            $this->logger->error(
                'Async execution failed: Profile CommandHandler was invoked without the expected identifier parameter.',
                [
                    'expected_command' => 'id',
                    'service' => $message->getServiceName(),
                    'method' => $message->getMethod(),
                    'params_count' => \count($params),
                    'params_type' => empty($params) ? 'none' : get_debug_type($params[0]),
                ]
            );
            return;
        }

        /** @var string|int $command */
        $command = $params[0];

        try {
            /** @var BaseUserInterface|null $user */
            $user = $this->userManager->find($command);
        } catch (\Exception $e) {
            $this->logAndThrowCriticalException($e, $command);
        }

        // Check if the user exists and is not yet verified
        if (null === $user || $user->isEmailVerified()) {
            $this->logIgnoredSend($user, $command);
            return;
        }

        try {
            // Service handles generation, hashing, persistence, and dispatching the confirmation email event.
            $this->secureTokenService->generateEmailConfirmationToken($user);
        } catch (EmailAlreadyVerifiedException $e) {
            // Edge case: User became verified between state check and token generation.
            // Log and halt execution without retrying.
            $this->logger->warning(
                'Attempted to generate a token for an already verified email after async check.',
                [
                    'exception' => $e,
                    'user_id' => $command,
                    'message' => $e->getMessage(),
                ]
            );

            return;
        } catch (\RuntimeException $e) {
            // Domain rate-limiting error (e.g., Cool-down period active).
            // Non-retryable error as the cool-down window would expire during subsequent worker retries.
            $this->logger->notice(
                'Email sending skipped due to cool-down / rate limiting.',
                [
                    'exception' => $e,
                    'user_id' => $command,
                    'type' => 'Cool-down',
                    'message' => $e->getMessage(),
                ]
            );

            return;
        } catch (\InvalidArgumentException $e) {
            // Developer logic/configuration error (e.g., empty email, invalid length).
            // Treated as a critical error.
            $this->logAndThrowCriticalException($e, $command);
        } catch (\Exception $e) {
            // Unexpected infrastructure or system failure (e.g., DB failure, hashing failure).
            // The worker must fail and trigger retry mechanisms.
            $this->logAndThrowCriticalException($e, $command, 'Unexpected error occurred during token generation.');
        }
    }

    /**
     * Handles critical exception logging and rethrows the exception for worker failure processing.
     *
     * @throws \Throwable
     */
    private function logAndThrowCriticalException(
        \Throwable $e,
        int|string $command,
        string $message = 'Critical development error.'
    ): never {
        $this->logger->critical($message, [
            'exception' => $e,
            'user_id' => $command,
            'code' => 'CRIT_002',
            'message' => $e->getMessage(),
        ]);

        throw $e;
    }

    /**
     * Logs redundant or skipped verification email requests.
     */
    private function logIgnoredSend(?BaseUserInterface $user, string|int $command): void
    {
        $reason = null === $user ? 'user not found' : 'user already verified';

        $this->logger->notice(
            'Verification email delivery skipped: async processing is no longer required.',
            [
                'reason' => $reason,
                'user_id' => $command,
            ]
        );
    }
}