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

namespace Wlindabla\UserBundle\Infrastructure\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;
use Wlindabla\UserBundle\Infrastructure\Validator\Constraint\NotDisposableEmail;
use Wlindabla\UserBundle\Infrastructure\Validator\EmailValidate;

/**
 * Public "forgot password" form. No custom validation messages: Symfony's
 * own NotBlank/Email constraints already ship translations for dozens of
 * locales — overriding them with hardcoded strings would mean maintaining
 * our own translations for text the framework already provides.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final class PasswordResetRequestType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('username', EmailType::class, [
            'label' => 'password_reset_request.username_label',
            'mapped' => false,
            'constraints' => [
                new NotBlank(),
                new Email(),
                new NotDisposableEmail(),
            ],
            'attr' => [
                'placeholder' => 'Ex: nom@exemple.com',
                'maxlength' => 200,
                'minlength' => 6,
                'autocomplete' => 'email',
                'autofocus' => true,
                'data-type' => 'email',
                'data-escapestrip-html-and-php-tags' => 'true',
                'data-event-validate-blur' => 'blur',
                'data-event-validate-input' => 'input',
                'data-error-message-input' => $this->translator->trans('password_reset_request.invalid_email', [], 'WlindablaUserBundle'),
                'data-host-blacklist' => json_encode(EmailValidate::DATA_HOST_BLACKLIST),
                'class' => 'login-input',
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => true,
            'csrf_token_id' => 'password_reset_request',
            'translation_domain' => 'WlindablaUserBundle',
        ]);
    }
}