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

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final class NotDisposableEmailValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): NotDisposableEmailValidator
    {
        return new NotDisposableEmailValidator();
    }

    public function testRejectsAKnownDisposableDomain(): void
    {
        $constraint = new NotDisposableEmail();
        $this->validator->validate('someone@mailinator.com', $constraint);

        $this->buildViolation($constraint->message)
            ->setParameter('{{ value }}', '"someone@mailinator.com"')
            ->assertRaised();
    }

    public function testAcceptsAnOrdinaryEmail(): void
    {
        $this->validator->validate('franck@example.com', new NotDisposableEmail());

        $this->assertNoViolation();
    }

    public function testIgnoresEmptyValues(): void
    {
        $this->validator->validate('', new NotDisposableEmail());
        $this->validator->validate(null, new NotDisposableEmail());

        $this->assertNoViolation();
    }

    public function testIgnoresMalformedInputAndLeavesItToTheEmailConstraint(): void
    {
        $this->validator->validate('not-an-email', new NotDisposableEmail());

        $this->assertNoViolation();
    }
}