# Known issues and design notes

[← Back to the README](../README.md)

This page lists everything that looked **inconsistent, unclear or risky** while the documentation was
written from the source code, and what the author answered. Nothing was hidden or worked around silently.

> **How these were found.** By reading the source, not by running it. We did not boot a Symfony kernel, so
> a few points are marked *to verify*. Each item names the file so you can check it quickly.
>
> Legend: ✅ fixed in the current source · 🟦 **by design** (a deliberate choice, explained so you can plan for
> it) · 🗓️ planned · 🔴 likely to break something · 🟠 surprising or insecure behaviour · 🟡 cleanup.

## Contents

| # | Item | Status |
|---|---|---|
| 1 | [Wrong namespaces (listener and command)](#wrong-namespaces) | ✅ |
| 2 | [`LogoutSubscriber` and the session module](#logoutsubscriber-and-the-session-module) | ✅ |
| 3 | [2FA redirect loop](#2fa-redirect-loop) | ✅ |
| 4 | [Session registered before 2FA](#session-registered-before-2fa) | ✅ |
| 5 | [Email change: the policy is yours](#email-change-the-policy-is-yours) | 🟦 (see the [guide](email-change.md)) |
| 6 | [Permission entities: unknown target interface](#permission-entities-unknown-target-interface) | ✅ |
| 7 | [Missing id mapping](#missing-id-mapping-unmapped-slug) (`slug` is ✅ fixed) | 🟦 id · 🟡 docblocks and index |
| 8 | [Unused configuration keys](#unused-configuration-keys) | ✅ |
| 9 | [Redis evictor is opt-in](#redis-evictor-is-opt-in) | 🟦 |
| 10 | [Hard-coded 2FA routes and a wrong route name](#hard-coded-2fa-routes-and-a-wrong-route-name) | ✅ · 🟦 fixed paths |
| 11 | [Sample file refers to a missing class](#sample-file-refers-to-a-missing-class) | ✅ |
| 12 | [User provider cache keys](#user-provider-cache-keys) | ✅ |
| 13 | [Commands: behaviour to know about](#commands-behaviour-to-know-about) | 🟦 |
| 14 | [2FA lock can be bypassed by resending](#2fa-lock-can-be-bypassed-by-resending) | 🗓️ 2.0.0 |
| 15 | [Delayed password hashing (45 s)](#delayed-password-hashing-45-s) | 🟦 |
| 16 | [Smaller observations](#smaller-observations) | ✅ / 🟡 |
| 17 | [Dependencies not declared](#dependencies-not-declared) | 🟦 |

---

## Wrong namespaces

> ✅ **Fixed in the current source.** `DoctrineTimestampableListener` now uses the namespace
> `Wlindabla\UserBundle\Infrastructure\EventSubscriber`, `ResetUserPasswordCommand` uses
> `Wlindabla\UserBundle\UI\Console`, and `config/services.php` imports both with the correct names.
> The description below is kept as a record of the problem. If you installed an older version, update.

**Files:** `config/services.php`, `src/Infrastructure/EventSubscriber/DoctrineTimestampableListener.php`,
`src/UI/Console/ResetUserPasswordCommand.php`

Two classes that live inside the bundle declare an `App\…` namespace:

| File in the bundle | Declared namespace |
|---|---|
| `src/Infrastructure/EventSubscriber/DoctrineTimestampableListener.php` | `App\Infrastructure\EventSubscriber` |
| `src/UI/Console/ResetUserPasswordCommand.php` | `App\UI\Console` |

and `config/services.php` imports them with `use App\Infrastructure\EventSubscriber\DoctrineTimestampableListener;`
and `use App\UI\Console\ResetUserPasswordCommand;`.

The bundle's Composer autoloading maps `Wlindabla\UserBundle\` to `src/`, so these two classes **cannot be
autoloaded** from the bundle. Depending on your project, the services are probably skipped without an error,
or the container reports a missing class. The consequences:

- the command `wlindabla:user:reset_password` is probably not available;
- `DoctrineTimestampableListener` (it fills `createdAt` on persist and `updatedAt` on update) is probably not
  active, but `BaseUser::$createdAt` is a `NOT NULL` column.

*To verify:* `php bin/console list wlindabla` and `php bin/console debug:container DoctrineTimestampableListener`.
(It can look fine in the author's own application if that application contains classes with these same
`App\…` names.)

**Fix:** change both namespaces to `Wlindabla\UserBundle\Infrastructure\EventSubscriber` and
`Wlindabla\UserBundle\UI\Console`, and fix the two `use` lines in `config/services.php`.
**Workaround (older versions only):** set `createdAt` yourself, or register your own timestamp listener.


## LogoutSubscriber and the session module

**Files:** `config/services_user_session.php`, `src/UI/Http/EventSubscriber/LogoutSubscriber.php`

✅ **Fixed.** Two changes:

- The subscriber returns immediately when there is no session, and then only continues for a `BaseUserInterface`
  user.
- It is now registered in `config/services_user_session.php` (line 56), next to the other session services, so it
  exists only when `classes.user_session` is set. Its constructor needs `UserSessionManagerInterface`, which is in
  the same file. A project with only `classes.user` no longer registers it.

(Make sure the line `$services->set(LogoutSubscriber::class);` was **removed** from `config/services.php`, otherwise
the service is declared twice and the core module is again required to provide the session manager.)

## 2FA redirect loop

**Files:** `src/WlindablaUserBundle.php`, `src/UI/Http/EventSubscriber/TwoFactorAuthListener.php`

✅ **Fixed.** `loadExtension()` now merges your `two_factor.allowed_routes_while_pending` with a built-in list:
`wlindabla_user_two_factor_show`, `wlindabla_user_two_factor_verify`, `wlindabla_user_two_factor_resend`,
`wlindabla_user_password_reset_request`, `wlindabla_user_password_reset_confirm`, `routes.login` and
`routes.logout`. You only list **your own extra routes**. The option itself still defaults to `[]`.


## Session registered before 2FA

**Files:** `src/WlindablaUserBundle.php`, `TwoFactorAuthListener.php` (priority 7), `SessionControlListener.php` (priority 6)

✅ **Fixed.** `loadExtension()` now merges your `session_control.excluded_routes` with a built-in list:
`wlindabla_user_two_factor_show`, `wlindabla_user_two_factor_verify`, `wlindabla_user_two_factor_resend`,
`routes.login` and `routes.logout`. The 2FA pages are still allowed through the 2FA listener while a code is
pending, but the session listener now skips them, so no `UserSession` is created (and no other device is revoked
in `takeover` mode) before the code is validated. The registration happens on the first other request, with the
final session id. You only list **your own** extra routes. (This is our reading of the code; please test it in
your project.)

## Email change: the policy is yours

**Files:** `config/services_email_change.php`, `src/Infrastructure/Persistance/EmailChangeRequestService.php`

🟦 **By design.** `EmailChangeRequestService` needs an `EmailChangeValidatorPolicyInterface`, and the bundle
deliberately ships no implementation: who may validate, and how many votes are needed, is a business rule of the
host application. The full how-to (entities, a complete policy class, service registration, controllers) is in
[Email change module](email-change.md). Two practical reminders: the service is typed against the abstract
repository classes, so alias them to your repositories, and the mails need a Messenger worker.


## Permission entities: unknown target interface

**File:** `src/Infrastructure/Doctrine/PermissionRole.php`

- ✅ **Fixed.** `createdBy` and `currentManager` now use
  `targetEntity: 'Wlindabla\UserBundle\Infrastructure\Model\UserInterface'`. The bundle already registers
  `resolve_target_entities` for that interface, so Doctrine maps it to your user class. (The old string
  `Infrastructure\Model\BaseUserInterface` pointed to an interface that does not exist: `BaseUserInterface` lives
  in `Domain\User\Model`.)
- ✅ **Fixed, `deletedBy`.** It used to have no type and no `targetEntity`; it is now
  `#[ORM\ManyToOne(targetEntity: 'Wlindabla\UserBundle\Infrastructure\Model\UserInterface')] protected ?BaseUserInterface $deletedBy`,
  like the two other relations.

## Missing id mapping, unmapped slug

- ✅ ~~`BaseUser` does not map the `slug` property~~ — **fixed**: `BaseUser` now maps it
  (`string(255)`, unique, nullable).
- 🟦 **By design:** `BaseTwoFactorCode` has no `#[ORM\Id]`, and `AbstractLoggerLoginUser`/`BaseLoggerLoginUser` have
  no `id` at all. You choose the identifier type in your concrete class (auto-increment integer, UUID, your own
  generator). See [entities](entities-and-migrations.md#other-entities).
- Docblocks refer to class names that do not exist (`Infrastructure\Doctrine\UserSession`,
  `BaseUserSession extends …UserSession`, `UserToken`). The real class is `AbstractUserSession`.
- `BaseUser` says in comments that `identityNormalized` has a `pg_trgm` GIN index, but no index is declared in the
  mapping. Create it in a migration. The `identity_normalized` property is a non-nullable PHP `string` mapped as a
  nullable column.


## Unused configuration keys

✅ **Handled.**

| Key | Now |
|---|---|
| `classes.user_token` | Marked **deprecated** (`setDeprecated`, "will be removed in 2.0"). A notice appears only if you set it. |
| `default_role` | Marked **deprecated**: not read by any code. The default role is the constant `ROLE_DEFAULT` of your entity (the [example application](example-application.md) overrides it). |
| `session_control.redis_session_prefix` | Not used by the bundle (the Redis evictor is opt-in), but now **exposed** as the parameter `wlindabla_user.session_control.redis_session_prefix`, so your own `services.yaml` can reuse it. |
| `session_control.inactivity_hours` | Its description now says it is read only by the cleanup schedule (requires `symfony/scheduler`). |
| `session_control.policy` | Now an **enum** (`takeover`, `block`): a typo stops the container build instead of silently acting like `takeover`. |

## Redis evictor is opt-in

**Files:** `config/services_user_session.php`, `RedisSessionStoreEvictor.php`

🟦 **By design.** `SessionStoreEvictorInterface` is aliased to `NullSessionStoreEvictor`, so the bundle works
without Redis. A project that stores PHP sessions in Redis registers `RedisSessionStoreEvictor` and the alias in
its own `services.yaml`, with the same key prefix as its session handler. See the
[manual wiring](session-control.md#redis-session-eviction) and the
[example application](example-application.md#services-wiring). The evictor's own default prefix
(`sf_wlindabla_user_s`) differs from Symfony's usual `sess_`, which is why you pass your prefix explicitly.


## Hard-coded 2FA routes and a wrong route name

- 🟦 **Fixed paths.** The 2FA paths (`/admin/2fa/…`) and names are fixed in the controller attributes.
  `TwoFactorAuthListener` uses the literal name `wlindabla_user_two_factor_show`. Only the login and logout
  paths/names are configurable.
- ✅ **Wrong route name fixed.** In `TwoFactorAuthController::resend()`, the redirect used when the CSRF check fails
  now uses `'wlindabla_user_two_factor_show'` instead of `'app_two_factor_show'`.
- ✅ **`routes.home` is `null`.** `PasswordResetRequestController` no longer calls `generateUrl('/')`: it redirects to
  `'/'` directly when `routes.home` is not set.
- ✅ **Route with dots renamed.** `wlindabla_user.verify.email` is now `wlindabla_user_verify_email` (controller and
  `UserAccountEmailVerificationSubscriber`). This is a breaking change for code that generates the old name.
  **Still to do:** `tests/Infrastructure/Routing/WlindablaRouteLoaderTest.php` (lines 48 and 86) still uses the old
  name and will fail.
- The `#[Target('two_factor_resend')]` / `#[Target('password_reset')]` rate limiter arguments resolve without
  extra aliases in the [example application](example-application.md) (Symfony 7.4). If they do not resolve in
  your project, [add the two aliases](installation.md#3-rate-limiters).

## Sample file refers to a missing class

**File:** `config/packages/wlindabla_share_service.yaml` **inside the bundle**. It is an example file at the bundle
root; the bundle does not load it (the real whitelist is built in `WlindablaUserBundle::prependExtension()`).

- ✅ **Fixed.** The line with the non-existent `TwoFactorCodeServiceInterface` is gone; the file now lists
  `TwoFactorCodeManagerInterface`.
- ✅ **Duplicate removed:** `LoggerLoginUserManagerInterface` is now listed once.

Also in `prependExtension()`: the local variable is spelled `$llowed_services` (typo, harmless).

## User provider cache keys

**File:** `src/Infrastructure/Security/Provider/UserProvider.php`

✅ **Fixed.** All keys now use the correct spelling `wlindabla_admin_user_login_cache_…`, and each key is deleted with
exactly the string it was created with: `…_cache_<md5(identifier)>` for the login cache (lines 72 and 207) and
`…_cache_<id>_<md5(identifier)>_` for the refresh cache (lines 138 and 210). Invalidation also works through
**cache tags** (`user_id_<id>`, `ADMIN`, `users`) as long as the pool supports tags. With the default
`user_cache_ttl: 0`, Symfony's `expiresAfter(0)` means "already expired", so nothing is effectively cached.
(The first run after the upgrade starts with a cold cache, because the key names changed.)

## Commands: behaviour to know about

🟦 **By design**, according to the author. See [console commands](console-commands.md). In short:

- `wlindabla:user:create` sets a **predictable password** in non-production (fixed string in the source +
  username) and reads the parameter `app.env`. Never run it with a non-production `app.env` on a real server.
- `wlindabla:user:activate`: answering **no** deactivates the account and prints "activation was cancelled";
  an unknown user is silently ignored but the command prints success.
- `wlindabla:user:reset_password` is now correctly namespaced ([item 1](#wrong-namespaces)); its text still says
  "SonataUser".


## 2FA lock can be bypassed by resending

🗓️ **Planned:** the author is working on it for version **2.0.0** of the bundle.

**Files:** `AbstractTwoFactorCode.php`, `TwoFactorCodeManager.php`

After 5 wrong attempts a code becomes `LOCKED`. The manager only looks at `PENDING` codes, so a user can request
a new code and try again. Brute force is limited to about 5 attempts per code, 3 codes per 15 minutes per
account, plus your per-IP limiter. The locked message says "contact an administrator", but no unlock mechanism
exists. Until 2.0.0, decide whether this limit is enough for your risk level.


## Delayed password hashing (45 s)

🟦 **By design** (the author confirms it is intended).

`DoctrineUserEventListener` dispatches the hashing of a password set through an **update** (for example from an
admin form) with a fixed 45-second delay through the async dispatcher. The source comments refer to
SonataAdmin timing. It needs a running worker, and the new password is not effective before the message is
handled. `UserManagerInterface::save()` hashes immediately, so code that calls `save()` is not affected.


## Smaller observations

| Observation | Status |
|---|---|
| The repository `README.md` was empty. | ✅ This documentation replaces it. |
| `block` policy: the class comment did not match the code. | ✅ The comment now describes the real behaviour (revoked row + redirect to the conflict page). |
| *Resend* could report success although no email was sent. | ✅ `resend()` checks the result of `generateAndSend()` and answers with `two_factor.send_failed` (HTTP `503` for AJAX); the translation exists in English and French. `createCodeAndSend()` itself still catches exceptions and returns `null`. |
| Several docblocks are in French or mention "Sonata". | 🟡 Cosmetic: translate to English and say "admin back-office". |
| `AbstractBaseUser::eraseCredentials()` is `#[\Deprecated]`. | 🟡 Kept on purpose for Symfony 6.4/7.x. Remove it when you drop those versions. |
| `phone` was nullable with `NotBlank`/`NotNull`. | ✅ The two constraints are removed: the phone is optional (`Length` 8–80 only). |
| `NotDisposableEmail` was not reviewed. | ✅ A test file now exists (`tests/Infrastructure/Validator/Constraint/NotDisposableEmailValidatorTest.php`). Note: the check compares the exact domain, so a sub-domain of a listed provider is not rejected. |
| The `Permission`, `LoggerLoginUser` and `EmailChange` modules have more classes than the docs detail. | The [email change](email-change.md) module has its own guide. For the others, read the source. |

## Dependencies not declared

🟦 **By design.** `composer.json` does not require `symfony/console`, `symfony/form`, `symfony/cache`,
`scienta/doctrine-json-functions` and similar packages. You install them **only if you use the feature that needs
them**. In particular, the bundle's repository code was written for PostgreSQL: a project on another database
overrides those methods in its own repository. The table of features, packages and alternatives is in
[installation](installation.md#dependencies-you-add-yourself-and-postgresql).

Back to: [README](../README.md)
