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
use Wlindabla\UserBundle\Infrastructure\Form\EmailValidate;

/**
 * Formulaire public "Mot de passe oublié" — reprend les mêmes conventions
 * data-* (form_validator JS) que le formulaire de login et le formulaire
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final class PasswordResetRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('username', EmailType::class, [
            'label' => 'Adresse email',
            'mapped' => false,
            'constraints' => [
                new NotBlank(message: 'Veuillez saisir votre adresse email.'),
                new Email(message: 'Veuillez saisir une adresse email valide.'),
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
                'data-error-message-input' => 'Veuillez saisir une adresse email valide.',
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
        ]);
    }
}
