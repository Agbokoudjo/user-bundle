# Entities and migrations

[← Back to the README](../README.md)

The bundle does **not** ship concrete Doctrine entities. It ships *mapped superclasses* (abstract classes
with Doctrine attributes). You create small concrete classes in your application that extend them. This keeps
table names and extra columns under your control.

## Contents

- [Overview](#overview)
- [The User entity](#the-user-entity)
  - [Required interfaces](#required-interfaces)
  - [Example](#example)
  - [Columns provided by `BaseUser`](#columns-provided-by-baseuser)
  - [Things you must add or configure](#things-you-must-add-or-configure)
- [Other entities](#other-entities)
- [Repositories](#repositories)
- [PostgreSQL-specific features](#postgresql-specific-features)
- [Migrations](#migrations)
- [Behaviour to know about](#behaviour-to-know-about)

---

## Overview

| Module | Mapped superclass to extend | Namespace | Notes |
|---|---|---|---|
| User (core) | `BaseUser` | `Wlindabla\UserBundle\Infrastructure\Doctrine` | Required |
| User session | `AbstractUserSession` | same | One row per PHP session |
| Login logger | `AbstractLoggerLoginUser` | same | One row per successful login |
| Two-factor | `BaseTwoFactorCode` | same | One row per issued code |
| Permission | `PermissionRole`, `PermissionGroup`, `UserPermissionRole`, `UserPermissionGroup` | same | All four together |
| Email change | `EmailChangeRequest`, `EmailChangeValidation` | same | Both together |

Each concrete class needs `#[ORM\Entity]` and (usually) `#[ORM\Table(name: '...')]`.

---

## The User entity

### Required interfaces

Your `User` class must implement `Wlindabla\UserBundle\Infrastructure\Model\UserInterface`. Extending
`BaseUser` already does that. The interface combines:

| Interface | From | What it brings |
|---|---|---|
| `Symfony\Component\Security\Core\User\UserInterface` | Symfony | `getRoles()`, `getUserIdentifier()`, `eraseCredentials()` |
| `PasswordAuthenticatedUserInterface`, `LegacyPasswordAuthenticatedUserInterface` | Symfony | `getPassword()`, `getSalt()` |
| `EquatableInterface` | Symfony | `isEqualTo()` |
| `UserIdentityNormalizerInterface` (extends `BaseUserInterface`) | Bundle | `getIdentityNormalized()` / `setIdentityNormalized()` + all of `BaseUserInterface` |
| `TwoFactorCodeForUserInterface` | Bundle | `markTwoFactorValidated()`, `get/setTwoFactorValidatedAt()`, `get/setTwoFactorLastCheckedAt()` |

`BaseUserInterface` (`Wlindabla\UserBundle\Domain\User\Model`) defines the domain contract: id, email and
username (plus canonical versions), password and plain password, `enabled`, `emailVerified`,
`emailVerifiedAt`, roles (`addRole`, `removeRole`, `hasRole`), super admin flag, confirmation token and its
request date, password request date, `isAccountNonLocked()`, `lastLogin`, `createdAt`, `updatedAt`, `slug`.

`getUserIdentifier()` returns the **email** (`AbstractBaseUser::getUserIdentifier()` returns
`(string) $this->email`).

### Example

```php
<?php
// src/Entity/User.php
namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Wlindabla\UserBundle\Infrastructure\Doctrine\BaseUser;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]   // "user" is a reserved word in PostgreSQL: choose another name
class User extends BaseUser
{
    
}
```

```php
<?php
// src/Repository/UserRepository.php
namespace App\Repository;

use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Wlindabla\UserBundle\Infrastructure\Doctrine\Repository\AbstractUserRepository;

class UserRepository extends AbstractUserRepository
{
    public function __construct(ManagerRegistry $registry, TagAwareCacheInterface $cache)
    {
        parent::__construct($registry, User::class, $cache);
    }
}
```

### Columns provided by `BaseUser`

Column names below assume Doctrine's default naming strategy (camelCase property → snake_case column).

| Property | Type | Nullable | Notes |
|---|---|---|---|
| `id` | integer, `IDENTITY` | no | Primary key |
| `username` | string(255), unique | no | Full name; validated: 6–255 chars, letters (all languages), spaces, hyphens, dots |
| `email` | string(200), unique | no | Validated with the HTML5 email mode, 6–200 chars |
| `password` | string | no\* | Hashed password. (\*The property is `?string`, the column is not nullable) |
| `enabled` | boolean, default false | no | Disabled users cannot log in |
| `emailVerified` | boolean, default false | no | |
| `roles` | json (`jsonb: true`) | no | |
| `passwordRequestedAt` | datetime | yes | Password reset request date |
| `emailVerifiedAt` | datetime_immutable | yes | |
| `lastLogin` | datetime | yes | |
| `confirmationToken` | string(255) | yes | Stores the **hash** of the token |
| `tokenRequestedAt` | datetime | yes | Used for the 60 s token cooldown |
| `usernameCanonical` | string(255), unique | yes | Filled by the canonical updater |
| `emailCanonical` | string(200), unique | yes | Filled by the canonical updater |
| `avatarFile` | (not persisted) | — | Vich `UploadableField`, mapping `avatars`, max 2 MB, jpeg/png/webp |
| `avatarUpdatedAt` | datetime | yes | |
| `avatarName` | string(255) | yes | |
| `phone` | `phone_number`, length 80 | yes | Has `#[Assert\NotBlank]`, `#[Assert\NotNull]`, length 8–80 (so the form requires it) |
| `twoFactorValidatedAt` | datetime_immutable | yes | **2FA freshness**: last successful code validation |
| `twoFactorLastCheckedAt` | datetime_immutable | yes | **2FA freshness**: activity heartbeat |
| `identityNormalized` | string, column `identity_normalized` | yes | Used for duplicate detection |
| `salt` | string(255) | yes | |
| `createdAt` | datetime_immutable | no | Filled by `DoctrineTimestampableListener` (see known issues) |
| `updatedAt` | datetime | yes | |

Serializer groups used by `UserProvider` for its cache: `user:cache`, `user:security`, `user:read`.

### Things you must add or configure

1. **`slug`** — `AbstractBaseUser` has the property and `getSlug()`/`setSlug()`, but `BaseUser` has no
   `#[ORM\Column]` for it. Add the column in your entity (see the example above).
   `UpdateUserProfileHandler` sets it once (a hash of the canonical username and id) and never regenerates it.
2. **Vich mapping `avatars`** — see [installation](installation.md#7-avatar-upload-mapping-and-phone-type).
3. **The `phone_number` Doctrine type** — comes from `odolbeau/phone-number-bundle`.
4. **A `createdAt` value** — the column is `NOT NULL`. The bundle's `DoctrineTimestampableListener` is meant to
   fill it (and `updatedAt`), but see [known issues](known-issues.md#wrong-namespaces) — if the listener is not
   active in your project, set `createdAt` yourself or register your own listener.
5. **Extra columns** — add any custom field to your concrete class.

---

## Other entities

All of these are optional and follow the same pattern: extend the mapped superclass, add `#[ORM\Entity]`
and `#[ORM\Table]`.

```php
<?php
// src/Entity/UserSession.php
namespace App\Entity;

use App\Repository\UserSessionRepository;
use Doctrine\ORM\Mapping as ORM;
use Wlindabla\UserBundle\Infrastructure\Doctrine\AbstractUserSession;

#[ORM\Entity(repositoryClass: UserSessionRepository::class)]
#[ORM\Table(name: 'user_sessions')]
#[ORM\Index(columns: ['user_identifier'], name: 'idx_user_session_identifier')]
#[ORM\Index(columns: ['last_activity_at'], name: 'idx_user_session_last_activity')]
#[ORM\Index(columns: ['active'], name: 'idx_user_session_active')]
class UserSession extends AbstractUserSession
{
}
```

```php
<?php
// src/Entity/TwoFactorCode.php
namespace App\Entity;

use App\Repository\TwoFactorCodeRepository;
use Doctrine\ORM\Mapping as ORM;
use Wlindabla\UserBundle\Infrastructure\Doctrine\BaseTwoFactorCode;

#[ORM\Entity(repositoryClass: TwoFactorCodeRepository::class)]
#[ORM\Table(name: 'two_factor_codes')]
#[ORM\Index(columns: ['user_identifier', 'status'], name: 'idx_2fa_user_status')]
class TwoFactorCode extends BaseTwoFactorCode
{
    // The mapped superclass declares an "id" property typed int|string|null but no #[ORM\Id].
    // You must map the identifier yourself:
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected int|string|null $id = null;
}
```

> **Check the `id` of each entity.** `BaseUser`, `AbstractUserSession`, the permission entities (except
> `PermissionGroup`, whose primary key is its string `code`) and the email change entities map their own `id`.
> `BaseTwoFactorCode` inherits a `protected $id` property from a plain domain class that has no Doctrine
> attributes, and `AbstractLoggerLoginUser` / `BaseLoggerLoginUser` declare **no `id` property at all** in the
> code we reviewed. So you map the identifier in your concrete class, as shown above. If Doctrine reports
> "No identifier/primary key specified", this is the reason.

```php
<?php
// src/Entity/LoggerLoginUser.php
namespace App\Entity;

use App\Repository\LoggerLoginUserRepository;
use Doctrine\ORM\Mapping as ORM;
use Wlindabla\UserBundle\Infrastructure\Doctrine\AbstractLoggerLoginUser;

#[ORM\Entity(repositoryClass: LoggerLoginUserRepository::class)]
#[ORM\Table(name: 'logger_login_users')]
class LoggerLoginUser extends AbstractLoggerLoginUser
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected int|string|null $id = null;
}
```

For the **permission** and **email change** entities, the docblocks of the mapped superclasses give these
table names: `permission_role`, `user_permission_group`, `email_change_requests`. Extend them like this:

```php
#[ORM\Entity(repositoryClass: PermissionRoleRepository::class)]
#[ORM\Table(name: 'permission_role')]
class PermissionRole extends \Wlindabla\UserBundle\Infrastructure\Doctrine\PermissionRole {}
```

For `EmailChangeValidation`, declare the unique constraint yourself, as required by the superclass docblock:

```php
#[ORM\Entity(repositoryClass: EmailChangeValidationRepository::class)]
#[ORM\Table(name: 'email_change_validations')]
#[ORM\UniqueConstraint(name: 'uniq_email_change_validator', columns: ['email_change_request_id', 'validator_id'])]
class EmailChangeValidation extends \Wlindabla\UserBundle\Infrastructure\Doctrine\EmailChangeValidation {}
```

> The column names `email_change_request_id` and `validator_id` come from the superclass docblock. Check them
> against the actual mapping with `php bin/console doctrine:schema:validate` and `doctrine:migrations:diff`.

The email change module also needs **your own implementation** of
`Wlindabla\UserBundle\Domain\EmailChange\Application\Manager\EmailChangeValidatorPolicyInterface`
(it decides the validation quorum). The bundle does not ship one.
See [known issues](known-issues.md#email-change-policy).

---

## Repositories

Each module has an abstract repository to extend:

| Abstract repository | Constructor arguments (after `ManagerRegistry`) |
|---|---|
| `AbstractUserRepository` | `string $entityClass`, `TagAwareCacheInterface $tagAwareCache` |
| `AbstractUserSessionRepository` | `string $entityClass`, `CacheItemPoolInterface $cache` (PSR-6) |
| `AbstractTwoFactorCodeRepository` | `string $entityClass` |
| `AbstractLoggerLoginUserRepository` | `string $entityClass` |
| `AbstractEmailChangeRequestRepository`, `AbstractEmailChangeValidationRepository` | see the source |
| `AbstractPermissionRoleRepository`, `AbstractPermissionGroupRepository`, `AbstractUserPermissionRoleRepository`, `AbstractUserPermissionGroupRepository` | see the source |

The bundle's services receive these repositories **by interface** (for example `UserRepositoryInterface`,
`TwoFactorCodeRepositoryInterface`, `UserSessionRepositoryInterface`). With the default Symfony
`App\:` service registration, an interface with a single implementation is autowired automatically. If you
have more than one implementation, add an explicit alias:

```yaml
# config/services.yaml
services:
    Wlindabla\UserBundle\Domain\User\Model\Persistance\UserRepositoryInterface:
        alias: App\Repository\UserRepository
```

> `EmailChangeRequestService` is typed against the **abstract classes**
> `AbstractEmailChangeRequestRepository` and `AbstractEmailChangeValidationRepository`, so your repositories
> for that module must extend them.

---

## PostgreSQL-specific features

Two groups of `AbstractUserRepository` methods need PostgreSQL (the class docblock says so):

**1. Duplicate detection** (`findSimilarCandidates()`, `hasSuspectedDuplicate()`) uses the `pg_trgm`
extension, the `%` operator and `similarity()` on the `identity_normalized` column. Create the extension and
the index in a migration:

```sql
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE INDEX idx_users_identity_normalized_trgm
    ON users USING GIN (identity_normalized gin_trgm_ops);
```

On another database, override `findSimilarCandidates()` or implement `UserFindDuplicatesRepositoryInterface`
yourself.

**2. Role-based finders** (`createQueryBuilderForEnabledUsersExcludingRoles()`, `findByUserWithExcludRole()`,
`findActiveEmailsByRole()`, `findActiveSuperAdminEmails()`) need a `jsonb` roles column and the `JSONB_EXISTS`
DQL function:

```bash
composer require scienta/doctrine-json-functions
```

```yaml
# config/packages/doctrine.yaml
doctrine:
    orm:
        dql:
            string_functions:
                JSONB_EXISTS: Scienta\DoctrineJsonFunctions\Query\AST\Functions\Postgresql\JsonbExists
```

The repository docblock itself warns that the exact class name must be checked against the installed version of
`scienta/doctrine-json-functions` ("it was not possible to confirm it here"). Please verify it.

---

## Migrations

Let Doctrine generate your migration from your mapping; it is the source of truth:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

The example below only shows the *shape* of a migration for the 2FA module on an existing application
(new user columns, the code table, and the trigram index). Names follow the examples on this page. Always
compare with what `doctrine:migrations:diff` generates for your DBAL version.

```php
<?php
// migrations/Version20261016000000.php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'WlindablaUserBundle: 2FA freshness columns, 2FA code table, trigram index';
    }

    public function up(Schema $schema): void
    {
        // 2FA freshness fields on the user account
        $this->addSql('ALTER TABLE users ADD two_factor_validated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD two_factor_last_checked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');

        // One row per issued code. The code itself is never stored, only its hash.
        $this->addSql('CREATE TABLE two_factor_codes (
            id SERIAL NOT NULL,
            user_identifier VARCHAR(255) NOT NULL,
            code_hash VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL,
            expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            attempts SMALLINT NOT NULL,
            max_attempts SMALLINT NOT NULL,
            last_sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            send_count SMALLINT NOT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX idx_2fa_user_status ON two_factor_codes (user_identifier, status)');

        // Duplicate detection (PostgreSQL only)
        $this->addSql('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        $this->addSql('CREATE INDEX idx_users_identity_normalized_trgm ON users USING GIN (identity_normalized gin_trgm_ops)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_users_identity_normalized_trgm');
        $this->addSql('DROP TABLE two_factor_codes');
        $this->addSql('ALTER TABLE users DROP two_factor_validated_at');
        $this->addSql('ALTER TABLE users DROP two_factor_last_checked_at');
    }
}
```

> **Existing accounts.** The freshness check treats a missing `two_factor_validated_at` or
> `two_factor_last_checked_at` as *stale* (fail closed). Right after the migration, every existing user is
> asked for a code on their next request. This is expected.

---

## Behaviour to know about

- **Passwords.** Set `plainPassword` on the entity, then call `UserManagerInterface::save()`. `save()` updates the
  canonical fields, hashes the password (`updatePassword()`) and saves.
- **Password changed from an admin form.** `DoctrineUserEventListener::preUpdate` sees a non-empty
  `plainPassword` on an **update** and dispatches `UpdatePasswordUserServiceInterface::handle()` through the async
  dispatcher **with a 45-second delay** (a constant in the source). The comment says it gives the admin framework
  time to finish its flush and commit. Consequence: the new password becomes effective after the delayed message
  is handled by a worker.
- **Canonical fields and slug.** After a user is persisted, or after `username`/`email` change,
  `UpdateUserProfileHandler` is dispatched asynchronously to recompute canonical fields and set the slug.
- **User cache.** Changing `enabled` or `roles` invalidates the cached user immediately.
- **Blameable.** `DoctrineBlameableListener` sets `createdBy`/`updatedBy` (and `currentManager` for
  `ManagedCatalogInterface`) on any entity that has those methods, using the logged-in user.

Back to: [README](../README.md) · Next: [Routes](routes.md)
