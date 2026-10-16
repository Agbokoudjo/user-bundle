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
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Wlindabla\ShareServiceBundle\Security\Generator\MfaCodeGeneratorInterface;
use Wlindabla\ShareServiceBundle\Security\Hash\TokenHasherInterface;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Application\Manager\TwoFactorCodeManagerInterface;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Enum\TwoFactorCodeStatus;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Exception\InvalidTwoFactorCodeException;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Exception\TwoFactorCodeLockedException;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Exception\TwoFactorSendThrottledException;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Model\Persistance\TwoFactorCodeRepositoryInterface;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Model\TwoFactorCodeInterface;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Service\TwoFactorCodeMailerServiceInterface;

/**
 * Orchestrates the full lifecycle of the email MFA code: generation,
 * hashing, persistence, sending, verification.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final class TwoFactorCodeManager implements TwoFactorCodeManagerInterface
{
    public function __construct(
        private readonly TwoFactorCodeRepositoryInterface $repository,
        private readonly MfaCodeGeneratorInterface $codeGenerator,
        private readonly TokenHasherInterface $tokenHasher,
        private readonly TwoFactorCodeMailerServiceInterface $twoFactorCodeService,
        private readonly LoggerInterface $logger,
        private readonly ParameterBagInterface $params,
    ) {}

    /**
     * guardSendRateLimit() is deliberately NOT wrapped in the try/catch
     * below — TwoFactorSendThrottledException must propagate to the
     * caller exactly as this interface's docblock promises. Wrapping it
     * here previously swallowed the exception into a silent null return,
     * which made the 429 response path in TwoFactorAuthController::resend()
     * unreachable.
     */
    public function generateAndSend(string $userIdentifier, ?string $ipAddress, string $context = 'login'): ?TwoFactorCodeInterface
    {
        $this->guardSendRateLimit($userIdentifier);

        return $this->createCodeAndSend($userIdentifier, $ipAddress, $context);
    }

    public function generateAndSendAsync(string $userIdentifier, ?string $ipAddress, string $context = 'login'): void {
        try {
            $this->generateAndSend($userIdentifier, $ipAddress, $context);
        } catch (\Throwable $e) {
            $this->logger->critical('Failed to generate/send the MFA code (periodic revalidation).', [
                'user' => $userIdentifier,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function createCodeAndSend(string $userIdentifier, ?string $ipAddress, string $context = 'login'): ?TwoFactorCodeInterface
    {
        try {
            // Only one active PENDING code at a time: previous ones are invalidated.
            $this->invalidatePending($userIdentifier);

            $plainCode = $this->codeGenerator->generate();

            $code = new ($this->repository->getClass())();
            $code->setUserIdentifier($userIdentifier);
            $code->setCodeHash($this->tokenHasher->hash($plainCode));
            $code->setStatus(TwoFactorCodeStatus::PENDING);
            $code->setExpiresAt(new \DateTimeImmutable(
                '+'.TwoFactorCodeInterface::DEFAULT_TTL_MINUTES.' minutes',
                new \DateTimeZone('UTC'),
            ));
            $code->setIpAddress($ipAddress);
            $code->setCreatedAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            $code->setLastSentAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

            $this->repository->save($code);

            $this->logger->info('MFA code generated and sent.', [
                'user' => $userIdentifier,
                'context' => $context,
                'ip' => $ipAddress,
            ]);

            /** @var string $siteName */
            $siteName = $this->params->get('app.site_name');

            $this->twoFactorCodeService->sendCodeByEmail($userIdentifier, $plainCode, $context, $siteName, $ipAddress);

            return $code;
        } catch (\Throwable $e) {
            $this->logger->critical('Failed to generate/send the MFA code.', [
                'user' => $userIdentifier,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
    
    public function verify(string $userIdentifier, string $submittedCode): void
    {
        $code = $this->repository->findLatestPending($userIdentifier);

        if (null === $code) {
            throw new InvalidTwoFactorCodeException();
        }

        if ($code->isLocked()) {
            throw new TwoFactorCodeLockedException();
        }

        if ($code->isExpired()) {
            $code->setStatus(TwoFactorCodeStatus::EXPIRED);
            $this->repository->save($code);

            throw new InvalidTwoFactorCodeException();
        }

        // Tolerate case and copy-paste spaces: digits + uppercase letters.
        $normalized = strtoupper(str_replace(' ', '', $submittedCode));

        if (!$this->tokenHasher->verify($normalized, $code->getCodeHash())) {
            $code->incrementAttempts();
            $this->repository->save($code);

            $this->logger->warning('Incorrect MFA code attempt.', [
                'user' => $userIdentifier,
                'attempts' => $code->getAttempts(),
            ]);

            if ($code->isLocked()) {
                throw new TwoFactorCodeLockedException();
            }

            throw new InvalidTwoFactorCodeException();
        }

        $code->setStatus(TwoFactorCodeStatus::VERIFIED);
        $this->repository->save($code);

        $this->invalidatePending($userIdentifier);

        $this->logger->info('MFA code validated successfully.', ['user' => $userIdentifier]);
    }

    public function findActivePending(string $userIdentifier): ?TwoFactorCodeInterface
    {
        $code = $this->repository->findLatestPending($userIdentifier);

        return ($code !== null && $code->isPending()) ? $code : null;
    }

    public function invalidatePending(string $userIdentifier): void
    {
        $this->repository->invalidatePending($userIdentifier);
    }

    /**
     * 3 envois maximum par fenêtre de 15 minutes (cf. rate_limiter.yaml
     * pour la limitation par IP ; ceci limite par compte, sur la fenêtre
     * de renvoi métier décrite dans le cahier des charges).
     */
    private function guardSendRateLimit(string $userIdentifier): void
    {
        $since = new \DateTimeImmutable(
            '-'.TwoFactorCodeInterface::DEFAULT_SEND_WINDOW_MINUTES.' minutes',
            new \DateTimeZone('UTC'),
        );

        $sentCount = $this->repository->countSentSince($userIdentifier, $since);

        if ($sentCount >= TwoFactorCodeInterface::DEFAULT_MAX_SEND_PER_WINDOW) {
            $this->logger->warning('MFA code resend limit reached.', ['user' => $userIdentifier]);

            throw new TwoFactorSendThrottledException();
        }
    }
}
