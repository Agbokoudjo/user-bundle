# Events and extensibility

[← Back to the README](../README.md)

## Contents

- [Events dispatched by the bundle](#events-dispatched-by-the-bundle)
- [Writing your own listener](#writing-your-own-listener)
- [Other hooks you can use](#other-hooks-you-can-use)
- [Templates](#templates)
- [Translations](#translations)
- [Replacing services](#replacing-services)
- [Permissions](#permissions)
- [Validator: disposable emails](#validator-disposable-emails)

---

## Events dispatched by the bundle

The bundle dispatches three domain events. They are plain objects; the event **class name** is the event
name. The bundle already has one subscriber for each of them, which sends an email through
`MailerManagerInterface` (MailingBundle). Your application can add its own listeners next to them.

| Event class | Dispatched by | Carries | Built-in subscriber |
|---|---|---|---|
| `Wlindabla\UserBundle\Domain\User\Event\UserAccountEmailVerificationEvent` | `SecureTokenService::generateEmailConfirmationToken()` | `getUsername()`, `getEmail()`, `getUserId()`, `getRawToken()` (the **plain** token, only available here) | `UserAccountEmailVerificationSubscriber` — builds the verification link and sends the `email_verification` template |
| `Wlindabla\UserBundle\Domain\User\Event\ToggleUserAccountEvent` | `ToggleUserAccountHandler` | `getUser()`, `getTemporaryPassword()` (`null` unless one was generated) | `ToggleUserAccountSubscriber` — sends the `toggle_user_account` email and clears the user cache |
| `Wlindabla\UserBundle\Domain\User\Event\UpdatePasswordUserEvent` | `GenerateTemporaryPasswordHandler` | `getUser()`, `getGeneratePassword()` (the plain temporary password) | `UpdatePasswordUserSubscriber` — sends the `temporary_password` email |

`ToggleUserAccountEvent` and `UpdatePasswordUserEvent` are dispatched through `EventBusInterface`
(ShareServiceBundle). `UserAccountEmailVerificationEvent` is dispatched through Symfony's
`EventDispatcherInterface`.

> **Treat these events as sensitive.** They carry plain tokens or plain temporary passwords. Do not log them.

---

## Writing your own listener

```php
<?php
// src/EventListener/AccountActivatedListener.php
namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Wlindabla\UserBundle\Domain\User\Event\ToggleUserAccountEvent;

#[AsEventListener]   // the event class is read from the argument type
final class AccountActivatedListener
{
    public function __invoke(ToggleUserAccountEvent $event): void
    {
        $user = $event->getUser();

        if ($user->isEnabled()) {
            // e.g. create a welcome task, notify an administrator, call a CRM…
        }
    }
}
```

Everything is autoconfigured in a standard Symfony application. Set a `priority` on the attribute if you must
run before or after the bundle's own subscriber.

---

## Other hooks you can use

These are not "events of the bundle", but they are real extension points in the code.

| Hook | What it gives you |
|---|---|
| `WorkerMessageHandledEvent` | `UpdateProfileSuccessListener` listens to it. After the async `UpdateUserProfileHandler::handle` message is handled, it generates the email confirmation token (if the email is not yet verified) and so triggers `UserAccountEmailVerificationEvent`. That is how new accounts get their verification email. |
| `SecurityEvents::INTERACTIVE_LOGIN` | Symfony event used by `LoginSubscriber`. Add your own listener (priority below 10 if you want to run after the 2FA flag is set). |
| `LogoutEvent` | Symfony event used by `LogoutSubscriber` (priority 10). |
| Doctrine listeners | `DoctrineUserEventListener`, `DoctrineBlameableListener`, `DoctrineTimestampableListener`, `UserIdentityNormalizerDoctrineLifecycleListener` (all priority 100 for Doctrine). |

---

## Templates

Every template is replaceable through `wlindabla_user.templates.<key>` (see
[configuration](configuration.md#templates)). Inside your Twig template you can extend or ignore the bundle's
version. Variables passed by the code we reviewed:

| Key | Variables |
|---|---|
| `login` | `error`, `last_username`, `csrf_token` (id `authenticate`), `site_name` |
| `two_factor_show` | `username`, `logout_url` (may be `null`) |
| `session_conflict` | `site_name`, `logout_url` (may be `null`) |
| `access_denied` | `message` |
| `email_verification` (email) | `recipientEmail`, `username`, `confirmationUrl`, `siteName` |
| `temporary_password` (email) | `username`, `url_login`, `site_name`, `password_user` |
| `toggle_user_account` (email) | `status` (bool), `username`, `url_login`, `site_name`, `password_user` (only if a temporary password was generated) |
| other keys | Read the controller or service that renders them (`password_reset_request`, `password_reset_email`, `two_factor_code_email`, `email_change_*`, `outcome_page`, `email_verification_result`) |

Example override:

```yaml
wlindabla_user:
    templates:
        login: 'security/login.html.twig'
```

```twig
{# templates/security/login.html.twig #}
{% extends '@WlindablaUser/security/login.html.twig' %}
{# override the blocks you want #}
```

(The block names depend on the bundle's template. Open `templates/security/login.html.twig` in the bundle to see
them.) You can also override a bundle template by placing a file at
`templates/bundles/WlindablaUserBundle/…` — this is standard Symfony behaviour for namespaced templates.

---

## Translations

Messages use the translation domain **`WlindablaUserBundle`**. Files provided:
`translations/WlindablaUserBundle.en.yaml` and `translations/WlindablaUserBundle.fr.yaml`.

Main key groups: `invalid_token`, `verification_email`, `email_verification`, `account_confirmation`, `login`,
`toggle_account_email`, `temporary_password_email`, `password_reset`, `password_reset_request`,
`password_reset_email`, `access_denied`, `entity_in_use`, `session_revoked`, `two_factor`, `session_conflict`,
`two_factor_email`, `user_action`, `email_change`.

To change a message, create `translations/WlindablaUserBundle.en.yaml` in your own application with the keys
you want to override (your files win over the bundle's).

---

## Replacing services

Services you define in your application replace bundle services with the same id. The main extension points
are interfaces, so you can provide your own implementation and alias the interface to it:

| Interface | Default | Typical reason to replace |
|---|---|---|
| `Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface` | `DoctrineUserManager` | Custom user storage |
| `Wlindabla\UserBundle\Infrastructure\Templating\TemplateRegistryInterface` | `TemplateRegistry` | Dynamic templates (themes) |
| `Wlindabla\UserBundle\Domain\User\Service\CanonicalFieldsUpdaterInterface` | `CanonicalFieldsUpdater` | Your own canonical form of username/email |
| `Wlindabla\UserBundle\Domain\TwoFactorAuth\Application\Manager\TwoFactorCodeManagerInterface` | `TwoFactorCodeManager` | Other delivery channel (SMS…) |
| `Wlindabla\UserBundle\Domain\TwoFactorAuth\Service\TwoFactorCodeMailerServiceInterface` | `TwoFactorCodeMailerService` | Custom email content |
| `Wlindabla\UserBundle\Domain\UserSession\Application\Manager\UserSessionManagerInterface` | `UserSessionManager` | Custom session storage |
| `Wlindabla\UserBundle\Domain\UserSession\Application\Service\SessionStoreEvictorInterface` | `NullSessionStoreEvictor` | Redis eviction ([how](session-control.md#redis-session-eviction)) |
| `Wlindabla\UserBundle\Infrastructure\Security\SecurityContextInterface` | `SymfonySecurityContextAdapter` | Custom "current user" resolution |
| `Wlindabla\UserBundle\Domain\EmailChange\Application\Manager\EmailChangeValidatorPolicyInterface` | **none** — you must provide it | Required for the email change module |

If you replace a service that is dispatched asynchronously (the whitelist in `prependExtension()` is by
class/interface name), keep the interface name the same.

---

## Permissions

When the Permission module is enabled, `AuthorizationCheckerForUserInterface` is the single entry point to
check rights. It answers for both Symfony roles and business permissions (direct or inherited through a
group):

```php
<?php
use Wlindabla\UserBundle\Domain\Permission\Application\Security\AuthorizationCheckerForUserInterface;

final class InvoiceController
{
    public function __construct(private AuthorizationCheckerForUserInterface $permissions) {}

    public function approve($user): void
    {
        if (!$this->permissions->isGrantedForUser($user, 'invoices:approve')) {
            throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException();
        }
        // ...
    }
}
```

| Method | Meaning |
|---|---|
| `isGrantedForUser(BaseUserInterface $user, string $permission): bool` | True if the user has the Symfony role, or the business permission directly or through a group |
| `isMemberOfGroupForUser(BaseUserInterface $user, string $groupCode): bool` | True if the user is a **direct** member of the group |

The bundle ships **no** predefined permissions: the catalog is entirely yours
(`PermissionRoleInterface::getName()` is the stable identifier, for example `invoices:approve`).

---

## Validator: disposable emails

The bundle provides the constraint `Wlindabla\UserBundle\Infrastructure\Validator\Constraint\NotDisposableEmail`
(target: property, method or parameter) with its validator, to refuse addresses from disposable email
providers. Default message: *"This email provider is not allowed. Please use a different email address."*

```php
use Wlindabla\UserBundle\Infrastructure\Validator\Constraint\NotDisposableEmail;

#[NotDisposableEmail]
private string $email;
```

(We did not review how the validator decides that a provider is disposable; see
`NotDisposableEmailValidator`.)

Back to: [README](../README.md) · Next: [Known issues](known-issues.md)
