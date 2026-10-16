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

namespace Wlindabla\UserBundle\Domain\TwoFactorAuth\Service;

/**
 * Contract for the Email-based MFA orchestration service.
 *
 * Note: Email dispatch is delegated asynchronously by the underlying mailer component
 * (e.g., via SystemMailer::send -> sendAsync).
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 */
interface TwoFactorCodeMailerServiceInterface
{
    /**
     * Generates a new code, invalidates any existing PENDING codes for the user,
     * persists the hashed token, and triggers the email notification dispatch.
     *
     * @param string      $userIdentifier The unique identifier of the user target.
     * @param string      $plainCode      The plain-text MFA code to be hashed and delivered.
     * @param string      $context        The operational context triggering the code dispatch.
     * @param string      $siteName       The application or tenant name displayed in the message.
     * @param string|null $ipAddress      The client IP address requesting the MFA code, if available.
     */
    public function sendCodeByEmail(
        string $userIdentifier, 
        string $plainCode, 
        string $context,
        string $siteName,
        ?string $ipAddress
    ): void;
}