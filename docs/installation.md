# Installation

[← Back to the README](../README.md)

## Contents

- [Requirements](#requirements)
- [Install with Composer](#install-with-composer)
- [Register the bundles](#register-the-bundles)
- [What your application must provide](#what-your-application-must-provide)
  - [1. The `app.site_name` parameter](#1-the-appsite_name-parameter)
  - [2. A tag-aware cache pool for the user provider](#2-a-tag-aware-cache-pool-for-the-user-provider)
  - [3. Rate limiters](#3-rate-limiters)
  - [4. Messenger and the async dispatcher](#messenger-and-the-async-dispatcher)
  - [5. ShareServiceBundle and MailingBundle configuration](#5-shareservicebundle-and-mailingbundle-configuration)
  - [6. Locks (user session module)](#6-locks-user-session-module)
  - [7. Avatar upload mapping and phone type](#7-avatar-upload-mapping-and-phone-type)
- [Optional extras](#optional-extras)
- [Verify the installation](#verify-the-installation)

---

## Requirements

These values come straight from the bundle's `composer.json`.

| Package | Constraint |
|---|---|
| `php` | `>=8.3` |
| `symfony/http-kernel`, `config`, `dependency-injection`, `security-core`, `security-bundle`, `validator`, `rate-limiter`, `lock`, `twig-bundle`, `translation`, `messenger`, `serializer`, `password-hasher` | `^6.4 \| ^7.4 \| ^8.0` |
| `doctrine/orm` | `^3.0` |
| `doctrine/doctrine-bundle` | `^2.11 \| ^3.0` |
| `wlindabla/share-service-bundle` | `^1.0` |
| `wlindabla/mailing-bundle` | `^1.0` |
| `vich/uploader-bundle` | `^3.0` |
| `odolbeau/phone-number-bundle` | `^4.2` |

Suggested (not required): `symfony/scheduler` — needed to clean expired user sessions automatically.

> **Note.** The source code also uses `symfony/console`, `symfony/form`, `symfony/cache` contracts and the
> `symfony/framework-bundle` controller base class. They are not listed in `composer.json`; in a standard
> Symfony application they are already installed. If you use a very small custom kernel, require them yourself.

## Install with Composer

```bash
composer require wlindabla/user-bundle
```

## Register the bundles

If Symfony Flex does not do it for you, add the bundle to `config/bundles.php`:

```php
<?php
// config/bundles.php
return [
    // ... your other bundles
    Wlindabla\UserBundle\WlindablaUserBundle::class => ['all' => true],
];
```

The bundle's dependencies (`wlindabla/share-service-bundle`, `wlindabla/mailing-bundle`,
`vich/uploader-bundle`, `odolbeau/phone-number-bundle`) must also be registered. Check each package's own
documentation for its bundle class. The bundle only reacts to ShareServiceBundle if that bundle is
registered (see `prependExtension()` — it checks `hasExtension('wlindabla_share_service')`).

---

## What your application must provide

The bundle reads or injects several things that **your application** has to define. If one of them is
missing you will see a container or runtime error, so please go through this list.

### 1. The `app.site_name` parameter

The bundle injects `%app.site_name%` into several controllers and services, and reads it at runtime in the
email services. If the parameter does not exist, the container does not compile.

```yaml
# config/services.yaml
parameters:
    app.site_name: 'My Application'
```

The `wlindabla:user:create` command also reads a parameter called `app.env` (see
[console commands](console-commands.md#wlindablausercreate)). Define it if you plan to use that command:

```yaml
parameters:
    app.env: '%kernel.environment%'
```

### 2. A tag-aware cache pool for the user provider

`UserProvider` needs a `TagAwareCacheInterface` argument named `$userCacheProvider`. Define a pool with
tags enabled:

```yaml
# config/packages/cache.yaml
framework:
    cache:
        pools:
            user_cache_provider:
                adapter: cache.app
                tags: true
```

Symfony creates an autowiring alias based on the pool name. If autowiring still cannot find it in your
Symfony version, bind the argument explicitly:

```yaml
# config/services.yaml
services:
    Wlindabla\UserBundle\Infrastructure\Security\Provider\UserProvider:
        arguments:
            $userCacheProvider: '@user_cache_provider'
```

(Because services you define yourself override bundle services with the same id, this works.)

Your **repositories** also need caches: `AbstractUserRepository` takes a `TagAwareCacheInterface` and
`AbstractUserSessionRepository` takes a PSR-6 `CacheItemPoolInterface`. See
[entities and migrations](entities-and-migrations.md#repositories).

### 3. Rate limiters

Two controllers ask Symfony for a rate limiter by name (`#[Target('…')]`):

| Name | Used by |
|---|---|
| `two_factor_resend` | `TwoFactorAuthController::resend()` (limit per client IP) |
| `password_reset` | `PasswordResetRequestController` (limit per client IP) |

```yaml
# config/packages/rate_limiter.yaml
framework:
    rate_limiter:
        two_factor_resend:
            policy: sliding_window
            limit: 3
            interval: '15 minutes'
        password_reset:
            policy: sliding_window
            limit: 5
            interval: '15 minutes'
```

To make the argument binding deterministic, add these two aliases (they are standard Symfony syntax):

```yaml
# config/services.yaml
services:
    Symfony\Component\RateLimiter\RateLimiterFactoryInterface $twoFactorResend: '@limiter.two_factor_resend'
    Symfony\Component\RateLimiter\RateLimiterFactoryInterface $passwordReset: '@limiter.password_reset'
```

> The limit values above are only examples. The bundle has its own per-account limit for 2FA codes
> (3 sends per 15 minutes) in addition to this per-IP limiter.

### Messenger and the async dispatcher

The bundle sends many operations through `Wlindabla\ShareServiceBundle\Messenger\QueueHandler\AsyncMethodDispatcherInterface`
(login logging, session activity updates, 2FA code generation, profile canonicalisation, delayed password
hashing…). Messages are handled by a Messenger worker:

```bash
php bin/console messenger:consume async_app_transport -vv
```

The transport name is configured in ShareServiceBundle (`wlindabla_share_service.async_method_dispatcher.transport`).
The sample file `config/packages/wlindabla_share_service.yaml` in the bundle uses `async_app_transport`.
That sample file is **not loaded automatically**; it only shows an example.

**You do not need to maintain the `allowed_services` whitelist by hand.** The bundle adds every class it
dispatches to `wlindabla_share_service.async_method_dispatcher.allowed_services` itself (in
`prependExtension()`), and also registers the route parameter names `id` and `userId` for ID encryption.
Your own entries are merged, not replaced.

### 5. ShareServiceBundle and MailingBundle configuration

This documentation does **not** cover the full configuration of `wlindabla/share-service-bundle` and
`wlindabla/mailing-bundle`; they are separate packages. From the code of this bundle we know that:

- ShareServiceBundle provides the async dispatcher, the ID encryption service, the token generator and
  hasher, the MFA code generator, the user-agent parser and the `SerializerFacade`.
- The ID encryption resolver is **not active until you set `encryption_key`** on the host side. The bundle
  cannot supply a secret for you (see the comment in `prependExtension()`).
- MailingBundle provides `MailerManagerInterface`, used to send every email of this bundle.

Please follow those packages' documentation to configure them.

### 6. Locks (user session module)

`UserSessionManager` uses `Symfony\Component\Lock\LockFactory`. A default Symfony Lock configuration is
enough:

```yaml
# config/packages/lock.yaml
framework:
    lock: '%env(LOCK_DSN)%'
```

### 7. Avatar upload mapping and phone type

`BaseUser` contains an avatar field mapped with VichUploader (mapping name `avatars`) and a phone field
using the `phone_number` Doctrine type. Configure both in your application:

```yaml
# config/packages/vich_uploader.yaml
vich_uploader:
    db_driver: orm
    mappings:
        avatars:
            uri_prefix: /uploads/avatars
            upload_destination: '%kernel.project_dir%/public/uploads/avatars'
```

Make sure the `phone_number` Doctrine type of `odolbeau/phone-number-bundle` is registered (follow that
package's installation page).

---

## Optional extras

| Extra | When you need it | How |
|---|---|---|
| `symfony/scheduler` | Automatic expiry of user sessions | `composer require symfony/scheduler`, then run the scheduler worker (see [session control](session-control.md#automatic-cleanup)) |
| Redis | You store PHP sessions in Redis and want revoked sessions removed from Redis immediately | See [session control](session-control.md#redis-session-eviction) |
| `scienta/doctrine-json-functions` | You call the role-based finders of `AbstractUserRepository` | See [entities](entities-and-migrations.md#postgresql-specific-features) |
| PostgreSQL `pg_trgm` extension | Duplicate-account detection | See [entities](entities-and-migrations.md#postgresql-specific-features) |

---

## Verify the installation

```bash
php bin/console cache:clear
php bin/console debug:container --parameters | grep wlindabla_user
php bin/console debug:router | grep wlindabla
php bin/console list wlindabla
```

You should see the `wlindabla_user.*` parameters, the routes of the enabled modules, and the commands
`wlindabla:user:*` (plus `wlindabla:user-session:*` if the session module is enabled).
See also [known issues](known-issues.md) — a few commands and one Doctrine listener may be missing because
of namespace problems in the current source.
