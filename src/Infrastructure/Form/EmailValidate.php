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

namespace Wlindabla\UserBundle\Infrastructure\Form;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
class EmailValidate
{
    public const array DATA_HOST_BLACKLIST = [
        '10minemail.com',
        '10minutemail.co.uk',
        '10minutemail.com',
        '10minutemail.de',
        '10minutemail.net',
        '10minutemail.org',
        'anonymbox.com',
        'binkmail.com',
        'bobmail.info',
        'chammy.info',
        'cool.fr.nf',
        'courriel.fr.nf',
        'devnullmail.com',
        'discard.email',
        'discardmail.com',
        'discardmail.de',
        'dispostable.com',
        'fake-box.com',
        'fakeinbox.com',
        'filzmail.com',
        'fudgerub.com',
        'getairmail.com',
        'grr.la',
        'guerrillamail.biz',
        'guerrillamail.com',
        'guerrillamail.de',
        'guerrillamail.info',
        'guerrillamail.net',
        'guerrillamail.org',
        'guerrillamailblock.com',
        'inoutmail.de',
        'inoutmail.eu',
        'inoutmail.info',
        'inoutmail.net',
        'iwi.net',
        'jetable.fr.nf',
        'jetable.net',
        'jetable.org',
        'jobbikmail.com',
        'maildrop.cc',
        'mailinator.com',
        'mailinator.net',
        'mailinator.org',
        'mailnew.com',
        'mailnull.com',
        'mega.zik.dj',
        'meltmail.com',
        'nomail.xl.cx',
        'nospam.ze.tc',
        'spam.la',
        'spam4.me',
        'spamcowboy.com',
        'spamcowboy.net',
        'spamcowboy.org',
        'spamevader.com',
        'spamex.com',
        'spamfree24.de',
        'spamfree24.eu',
        'spamfree24.info',
        'spamfree24.net',
        'spamfree24.org',
        'spamgourmet.com',
        'spamgourmet.net',
        'spamgourmet.org',
        'spamspot.com',
        'spamthisplease.com',
        'speed.1s.fr',
        'sharklasers.com',
        'tempinbox.com',
        'tempmail.com',
        'tempmail.net',
        'tempmail.org',
        'tempr.email',
        'throwam.com',
        'throwaway.email',
        'trashmail.at',
        'trashmail.com',
        'trashmail.io',
        'trashmail.me',
        'trashmail.net',
        'trashmail.org',
        'weg-werf-email.de',
        'wegwerfmail.de',
        'wegwerfmail.net',
        'wegwerfmail.org',
        'yopmail.com',
        'yopmail.fr',
        'yopmail.net',
        'yuurok.com',
    ];
}