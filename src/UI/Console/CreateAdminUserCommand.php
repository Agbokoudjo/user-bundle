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

namespace Wlindabla\UserBundle\UI\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Infrastructure\Model\UserInterface;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
#[AsCommand(name: 'wlindabla:user:create', description: 'Create a user')]
final class CreateAdminUserCommand extends Command
{
     private UserPasswordHasherInterface $passwordHasher;
     
    public function __construct(
         private readonly ParameterBagInterface $parameterBag ,
         UserPasswordHasherInterface $passwordHasher,
        private readonly UserManagerInterface $userManager,
        )
    {
        parent::__construct('sonata:user:create');
        $this->passwordHasher = $passwordHasher;
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $helper = new QuestionHelper();

        $question = new Question('Enter username: ');
        $username = $helper->ask($input, $output, $question);

        $question = new Question('Enter email: ');
        $question->setValidator(function ($answer) {
            if (!filter_var($answer, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Invalid email address.');
            }
            return $answer;
        });
        $email = $helper->ask($input, $output, $question);

        /**
         * @var UserInterface $user_create
         */
        $user_create = new $this->userManager->create() ;
        $user_create->setUsername($username) ;
        $user_create->setEmail($email);

         
       
        if($this->parameterBag->get("app.env") !=='prod'){
            $user_create->setEnabled(true);
           $user_create->setEmailVerified(true);
           $user_create->setEmailVerifiedAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
           $hashedPassword = $this->passwordHasher->hashPassword($user_create, 'iwas-@#Demoprojet-dev-2026!!');
           $user_create->setPassword($hashedPassword);
        }
        
        $user_create->setCreatedAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

        try {
            $this->userManager->save($user_create) ;
        } catch (\Throwable $th) {
            throw $th;
        }

        $output->writeln(\sprintf(
            'Created user "%s" with email "%s"',
            $username,
            $email
        ));

        return Command::SUCCESS;
    }
}
