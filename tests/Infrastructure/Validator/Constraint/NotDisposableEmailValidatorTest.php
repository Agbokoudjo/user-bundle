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

namespace Wlindabla\UserBundle\Tests\Infrastructure\Validator\Constraint;

use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Wlindabla\UserBundle\Infrastructure\Validator\Constraint\NotDisposableEmail;
use Wlindabla\UserBundle\Infrastructure\Validator\Constraint\NotDisposableEmailValidator;
use Wlindabla\UserBundle\Infrastructure\Validator\EmailValidate;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final class NotDisposableEmailValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): NotDisposableEmailValidator { return new NotDisposableEmailValidator(); }

    public function testNullAndEmptyAreValid(): void
    {
        $this->validator->validate(null, new NotDisposableEmail());
        $this->validator->validate('', new NotDisposableEmail());
        $this->assertNoViolation();
    }

    public function testDisposableDomainIsRejected(): void
    {
        $blocked = EmailValidate::DATA_HOST_BLACKLIST[0];
        $this->validator->validate('user@'.$blocked, new NotDisposableEmail());
        $this->buildViolation((new NotDisposableEmail())->message)
            ->setParameter('{{ value }}', '"user@'.$blocked.'"')->assertRaised();
    }

    public function testDomainIsCaseInsensitive(): void
    {
        $this->validator->validate('user@'.strtoupper(EmailValidate::DATA_HOST_BLACKLIST[0]), new NotDisposableEmail());
        $this->buildViolation((new NotDisposableEmail())->message)
            ->setParameter('{{ value }}', '"user@'.strtoupper(EmailValidate::DATA_HOST_BLACKLIST[0]).'"')->assertRaised();
    }

    public function testNormalDomainIsValid(): void
    {
        $this->validator->validate('user@example.org', new NotDisposableEmail());
        $this->assertNoViolation();
    }
}