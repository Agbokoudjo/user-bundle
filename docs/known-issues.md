# Known issues and ambiguities

[← Back to the README](../README.md)

This page lists everything that looked **inconsistent, unclear or risky** while the documentation was
written from the source code. Nothing was hidden or worked around silently.

> **How these were found.** By reading the source, not by running it. We did not boot a Symfony kernel, so
> a few points are marked *to verify*. Each item names the file so you can check it quickly.
> Severity: 🔴 likely to break something, 🟠 surprising or insecure behaviour, 🟡 cleanup / documentation.

## Contents

| # | Item | Severity |
|---|---|---|
| 1 | [Wrong namespaces (listener and command)](#wrong-namespaces) | 🔴 |
| 2 | [`LogoutSubscriber` needs the session module](#logoutsubscriber-needs-the-session-module) | 🔴 |
| 3 | [2FA: redirect loop if routes are not allowed](#2fa-redirect-loop) | 🟠 |
| 4 | [Session registered before 2FA](#session-registered-before-2fa) | 🟠 |
| 5 | [Email change: no validator policy provided](#email-change-policy) | 🔴 |
| 6 | [Permission entities: unknown target interface](#permission-entities-unknown-target-interface) | 🔴 |
| 7 | [Missing id mapping, unmapped `slug`](#missing-id-mapping-unmapped-slug) | 🟠 |
| 8 | [Unused configuration keys](#unused-configuration-keys) | 🟡 |
| 9 | [Redis evictor is never wired](#redis-evictor-never-wired) | 🟠 |
| 10 | [Hard-coded 2FA routes and a wrong route name](#hard-coded-2fa-routes-and-a-wrong-route-name) | 🟠 |
| 11 | [Sample file refers to a missing class](#sample-file-refers-to-a-missing-class) | 🟡 |
| 12 | [User provider cache keys](#user-provider-cache-keys) | 🟡 |
| 13 | [Commands: dangerous or misleading behaviour](#commands-dangerous-or-misleading-behaviour) | 🟠 |
| 14 | [2FA lock can be bypassed by resending](#2fa-lock-can-be-bypassed-by-resending) | 🟠 |
| 15 | [Delayed password hashing (45 s)](#delayed-password-hashing-45-s) | 🟠 |
| 16 | [Smaller observations](#smaller-observations) | 🟡 |
| 17 | [Dependencies not declared](#dependencies-not-declared) | 🟡 |

---

## Wrong namespaces

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
**Workaround:** set `createdAt` yourself, or register your own timestamp listener.

## LogoutSubscriber needs the session module

**Files:** `config/services.php`, `src/UI/Http/EventSubscriber/LogoutSubscriber.php`

`LogoutSubscriber` is registered in the **core** services file, but its constructor requires
`UserSessionManagerInterface`, which only exists when `classes.user_session` is set. With the session module
off, the container will probably fail to autowire it. Also inside the subscriber:

- `$session->getId()` is called even when `$session` is `null` (the guard only returns when *both* the session
  is null *and* the user is not a `BaseUserInterface`); the call sits in a `try/catch(\Throwable)` that logs
  `Error revoking session on logout`.

*To verify:* boot a project with only `classes.user` set.
**Workaround:** enable the session module, or exclude the service in your application.

## 2FA redirect loop

**File:** `src/UI/Http/EventSubscriber/TwoFactorAuthListener.php`

`two_factor.allowed_routes_while_pending` defaults to an empty list. With a pending code, the 2FA page itself
is blocked and redirected to the 2FA page again. **You must list** `wlindabla_user_two_factor_show`,
`wlindabla_user_two_factor_verify`, `wlindabla_user_two_factor_resend` and your logout route. The bundle does
not add them for you.

## Session registered before 2FA

**Files:** `TwoFactorAuthListener.php` (priority 7), `SessionControlListener.php` (priority 6)

The comment says no `UserSession` should be created while a code is pending. But routes in
`allowed_routes_while_pending` (the 2FA pages) pass the 2FA listener and then reach the session listener, which
registers the session (and in `takeover` mode revokes the other devices' sessions). Adding the same routes to
`session_control.excluded_routes` avoids this; the registration then happens on the first request after
validation, with the final session id. This is our reading of the code — please test it in your project.

## Email change policy

**Files:** `config/services_email_change.php`, `src/Infrastructure/Persistance/EmailChangeRequestService.php`

`EmailChangeRequestService` requires `EmailChangeValidatorPolicyInterface`, but no implementation exists in the
bundle and `services_email_change.php` does not register one. With the email change module enabled, you must
write and register your own implementation (it decides the validation quorum and who may validate). The
service is also typed against the concrete abstract repositories (`AbstractEmailChangeRequestRepository`,
`AbstractEmailChangeValidationRepository`), so your repositories must extend them.

## Permission entities: unknown target interface

**File:** `src/Infrastructure/Doctrine/PermissionRole.php`

`createdBy`/`updatedBy` are mapped with
`targetEntity: 'Wlindabla\UserBundle\Infrastructure\Model\BaseUserInterface'`. That interface does not exist
in that namespace (`BaseUserInterface` is in `Wlindabla\UserBundle\Domain\User\Model`), and the bundle only
registers `resolve_target_entities` for `Infrastructure\Model\UserInterface`. Doctrine will probably report an
unknown target entity when the Permission module is used. *To verify* with `doctrine:schema:validate`.
**Workaround:** add a `resolve_target_entities` line for that name pointing to your user class, or override the
mapping in your concrete class.

## Missing id mapping, unmapped slug

- `BaseUser` does not map the `slug` property (the domain code uses it). Add the column in your entity.
- `BaseTwoFactorCode` has no `#[ORM\Id]`, and `AbstractLoggerLoginUser`/`BaseLoggerLoginUser` have no `id` at all.
  Map it in your concrete class. See [entities](entities-and-migrations.md#other-entities).
- Docblocks refer to class names that do not exist (`Infrastructure\Doctrine\UserSession`,
  `BaseUserSession extends …UserSession`, `UserToken`). The real class is `AbstractUserSession`.
- `BaseUser` says in comments that `identityNormalized` has a `pg_trgm` GIN index, but no index is declared in the
  mapping. Create it in a migration. The `identity_normalized` property is a non-nullable PHP `string` mapped as a
  nullable column.

## Unused configuration keys

| Key | Finding |
|---|---|
| `classes.user_token` | Accepted, never read. |
| `default_role` | Stored in `wlindabla_user.default_role`; no code reads it. `AbstractBaseUser::ROLE_DEFAULT` (`ROLE_USER`) is what is used. |
| `session_control.redis_session_prefix` | Accepted, never read (only used in a test). |
| `session_control.inactivity_hours` | Read only by `SessionCleanupSchedule`, which exists only with `symfony/scheduler`. |
| `session_control.policy` | Not validated: any value but `block` acts like `takeover`. |

## Redis evictor never wired

**Files:** `config/services_user_session.php`, `RedisSessionStoreEvictor.php`

`SessionStoreEvictorInterface` is always aliased to `NullSessionStoreEvictor`. `RedisSessionStoreEvictor` is
never registered. Its default prefix (`sf_wlindabla_user_s`) differs from Symfony's usual `sess_`. See the
[manual wiring](session-control.md#redis-session-eviction).

## Hard-coded 2FA routes and a wrong route name

- The 2FA paths (`/admin/2fa/…`) and names are fixed. `TwoFactorAuthListener` uses the literal name
  `wlindabla_user_two_factor_show`. Only the login and logout paths/names are configurable.
- `TwoFactorAuthController::resend()` builds its redirect for a **failed CSRF check** with the route name
  `app_two_factor_show`. The bundle does not define it, so that branch raises `RouteNotFoundException` unless your
  application defines a route with that name. (The correct name is `wlindabla_user_two_factor_show`.)
- `PasswordResetRequestController` calls `generateUrl($this->homeRouteName ?? '/')`. If `routes.home` is
  `null`, `'/'` is not a route name and the call throws. Set `routes.home`.
- The route `wlindabla_user.verify.email` uses dots while all other names use underscores (it is also hard-coded
  in a subscriber).
- The `#[Target('two_factor_resend')]` / `#[Target('password_reset')]` rate limiter arguments may not resolve in
  every Symfony version. [Add the two aliases](installation.md#3-rate-limiters).

## Sample file refers to a missing class

`config/packages/wlindabla_share_service.yaml` (not loaded by the bundle; an example only) lists
`Wlindabla\UserBundle\Domain\TwoFactorAuth\Service\TwoFactorCodeServiceInterface`, which does not exist. The
real whitelist is built in `WlindablaUserBundle::prependExtension()`.
Also in `prependExtension()`: the local variable is spelled `$llowed_services` (typo, harmless).

## User provider cache keys

**File:** `src/Infrastructure/Security/Provider/UserProvider.php`

The login cache key is `wlindbala_admin_user_login_cache_<md5>` (misspelled, with a trailing underscore), while
`invalidateUserCache()` deletes `wlindabla_admin_user_login_cache<md5>` (different spelling, no underscore).
The direct delete therefore never matches. Invalidation still works through **cache tags**
(`user_id_<id>`, `ADMIN`, `users`), so behaviour is correct as long as the pool supports tags. With the default
`user_cache_ttl: 0`, Symfony's `expiresAfter(0)` means "already expired", so nothing is effectively cached.

## Commands: dangerous or misleading behaviour

See [console commands](console-commands.md). In short:

- `wlindabla:user:create` sets a **predictable password** in non-production (fixed string in the source +
  username) and reads the parameter `app.env`.
- `wlindabla:user:activate`: answering **no** deactivates the account and prints "activation was cancelled";
  an unknown user is silently ignored but the command prints success.
- `wlindabla:user:reset_password` is probably not registered ([namespace problem](#wrong-namespaces)); its
  text still says "SonataUser".

## 2FA lock can be bypassed by resending

**Files:** `AbstractTwoFactorCode.php`, `TwoFactorCodeManager.php`

After 5 wrong attempts a code becomes `LOCKED`. The manager only looks at `PENDING` codes, so a user can request
a new code and try again. Brute force is limited to about 5 attempts per code, 3 codes per 15 minutes per
account, plus your per-IP limiter. The locked message says "contact an administrator", but no unlock mechanism
exists. Decide whether this limit is enough for your risk level.

## Delayed password hashing (45 s)

`DoctrineUserEventListener` dispatches the hashing of a password set through an **update** (for example from an
admin form) with a fixed 45-second delay through the async dispatcher. The source comments refer to
SonataAdmin timing. It needs a running worker, and the new password is not effective before the message is
handled. `UserManagerInterface::save()` hashes immediately, so code that calls `save()` is not affected.

## Smaller observations

- **README.md of the repository was empty** — this documentation replaces it.
- `block` policy: the class comment says the new login is logged out (`security->logout(false)`), but the code
  creates a revoked row and redirects to the conflict page; the conflict page then revokes that browser's row.
- `TwoFactorCodeManager::createCodeAndSend()` swallows all exceptions (returns `null`), so *resend* can report
  success although no email was sent.
- Several docblocks are in French or mention "Sonata" in a package that has no Sonata dependency.
- `AbstractBaseUser::eraseCredentials()` is marked `#[\Deprecated]`; it is kept for Symfony 6.4/7.x.
- `phone` on `BaseUser` is nullable in the database but has `NotBlank`/`NotNull` validation constraints.
- `Infrastructure\Validator\Constraint\NotDisposableEmail` was not reviewed in depth.
- The modules `Permission`, `LoggerLoginUser` and `EmailChange` have more classes than this documentation
  describes in detail (repositories, mailers, interfaces). The behaviour described here is limited to what we
  read; see the source for the rest.

## Dependencies not declared

`composer.json` does not require `symfony/console`, `symfony/form`, `symfony/cache`, `symfony/framework-bundle`,
`symfony/event-dispatcher`, `symfony/http-foundation`, `symfony/routing`, `symfony/mailer` or
`scienta/doctrine-json-functions`, although the code uses them (some are pulled in by the packages that are
required, some by a standard Symfony application). `symfony/scheduler` is only a `suggest`.

Back to: [README](../README.md)
