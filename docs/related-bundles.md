# Related bundles: ShareServiceBundle and MailingBundle

[← Back to the README](../README.md)

`wlindabla/user-bundle` cannot work alone. It needs two other bundles by the same author. This page explains
what each one does for the user bundle and how to configure it. The facts come from the two projects' README
files and from the [example application](example-application.md).

> The two bundles have their own repositories and documentation. If something here differs from them, trust
> their repositories:
> - ShareServiceBundle: <https://github.com/Agbokoudjo/share_service_bundle>
> - MailingBundle: <https://github.com/Agbokoudjo/mailing-bundle>

## Contents

- [What each bundle gives to the user bundle](#what-each-bundle-gives-to-the-user-bundle)
- [ShareServiceBundle](#shareservicebundle)
- [MailingBundle](#mailingbundle)
- [A note on versions](#a-note-on-versions)

---

## What each bundle gives to the user bundle

| Needed by the user bundle | Comes from | Used for |
|---|---|---|
| `AsyncMethodDispatcherInterface` | ShareServiceBundle | Background work: login log, session activity, 2FA codes, delayed password hashing |
| `IdEncryptionInterface` + argument resolver | ShareServiceBundle | Encrypted `{id}` / `{userId}` in URLs |
| `TokenGeneratorInterface`, `TokenHasherInterface` | ShareServiceBundle | Email and password reset tokens; hashing of tokens and 2FA codes |
| `MfaCodeGeneratorInterface` | ShareServiceBundle | The 2FA code (see below) |
| `GenerateTemporaryPasswordService` | ShareServiceBundle | Temporary passwords (20 characters) |
| `EventBusInterface` | ShareServiceBundle | Dispatching the user events |
| `UserAgentParserInterface`, `DeviceFingerprintUserAgent` | ShareServiceBundle | Login log label and session device fingerprint |
| `SerializerFacade` | ShareServiceBundle | Caching the user in `UserProvider` |
| `IdentityNormalizer` | ShareServiceBundle | `identityNormalized` field and duplicate detection |
| `MailerManagerInterface`, `PriorityInterface` | MailingBundle | Every email (verification, 2FA code, password, account status…) |

---

## ShareServiceBundle

### Requirements

PHP 8.3+, Symfony 6.4 / 7.x / 8.0, and the PHP extension **`ext-sodium`** (used for ID encryption).

### Generate the encryption key (once)

```bash
php -r "require 'vendor/autoload.php'; echo \Wlindabla\ShareServiceBundle\Security\Encryption\IdEncryptionService::generateEncryptionKey();"
```

Store the result as `APP_ID_ENCRYPTION_KEY` in `.env.local` or in your secrets vault. **Never commit it.**
If you change it later, every previously sent link (email verification, password reset…) stops working.

### Configuration

```yaml
# config/packages/wlindabla_share_service.yaml
wlindabla_share_service:
    id_encryption:
        encryption_key: '%env(APP_ID_ENCRYPTION_KEY)%'      # secret; the service is only registered when this is set
        identity_key_collection: ['id', 'userId']           # route parameters decrypted automatically

    token_hasher:
        algorithm: !php/const PASSWORD_ARGON2ID             # null = Argon2id if available, else bcrypt
        options:
            memory_cost: 65536                               # 64 MB
            time_cost: 4
            threads: 2

    identity_similarity_threshold: 88.0                      # score (0–100) that flags two names as similar

    trusted_ips: ['127.0.0.1', '::1']                        # IPs trusted by the browser request validator

    async_method_dispatcher:
        transport: async_app_transport                       # Messenger transport name
        allowed_services: []                                 # YOUR OWN services; the user bundle adds its own
```

| Key | Default | Meaning |
|---|---|---|
| `id_encryption.encryption_key` | `null` | Secret key. Without it, `IdEncryptionInterface` and its resolver are **not registered**. |
| `id_encryption.identity_key_collection` | `['id', 'code', 'slug']` | Route parameter names that are decrypted. The user bundle adds `id` and `userId` for you. |
| `token_hasher.algorithm` | `null` | Hash algorithm for `password_hash()`. |
| `token_hasher.options` | `[]` | Options for `password_hash()`. Secure defaults apply when empty. |
| `identity_similarity_threshold` | `88.0` | Similarity threshold. |
| `trusted_ips` | `['127.0.0.1', '::1']` | Trusted IPs for the browser request validator. |
| `async_method_dispatcher.transport` | — | Messenger transport used for dispatched calls. |
| `async_method_dispatcher.allowed_services` | — | Strict whitelist of service names that a queued message may call. |

You do **not** need to list the user bundle's services in `allowed_services`: it registers them itself
(see [configuration](configuration.md#settings-added-automatically-by-the-bundle)). You only list your own
(for example your own command handlers).

### Declaring the ID encryption services yourself (optional)

When `id_encryption.encryption_key` is set, ShareServiceBundle registers `IdEncryptionService` and
`IdEncryptionRequestArgumentValueResolver` for you. If you prefer to control them (for example the priority of the
argument resolver), declare them in your own `services.yaml`. Use the **same service ids** as the bundle:

```yaml
# config/services.yaml
services:
    # Decrypts the route attributes listed in identity_key_collection (id, userId, …) before your controller runs.
    Wlindabla\ShareServiceBundle\ArgumentResolver\IdEncryptionRequestArgumentValueResolver:
        autowire: true
        autoconfigure: false              # the tag below is declared by hand
        public: true
        tags:
            - name: 'controller.argument_value_resolver'
              priority: 999               # high priority: runs before Symfony's default resolvers

    # The service that encrypts and decrypts the ids.
    Wlindabla\ShareServiceBundle\Security\Encryption\IdEncryptionService:
        autowire: true
        autoconfigure: false
        public: false
        arguments:
            $encryptionKey: '%env(APP_ID_ENCRYPTION_KEY)%'   # the secret, never committed
```

Rules to follow:

1. `APP_ID_ENCRYPTION_KEY` must be defined in `.env.local` or your secrets vault (see
   [generate the encryption key](#generate-the-encryption-key-once)). Keep the **same** value in
   `wlindabla_share_service.id_encryption.encryption_key` if you also set it there.
2. The argument name must be exactly `$encryptionKey`.
3. With `autoconfigure: false`, the `controller.argument_value_resolver` tag is **not** added automatically:
   keep the `tags` block.
4. A higher `priority` runs earlier. A value of 999 makes sure the id is decrypted before the default
   `RequestAttributeValueResolver` reads it (our reading of how Symfony orders resolvers; test it in your project).
5. The route parameters that get decrypted are the names in `id_encryption.identity_key_collection`
   (the user bundle adds `id` and `userId`). A route using another name, such as `{slug}`, is decrypted only if you
   add that name to the list.
   
### Messenger routing for the dispatcher

Route the dispatcher's message to the transport you named, then run a worker:

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            async_app_transport:
                dsn: '%env(MESSENGER_TRANSPORT_APP_DSN)%'
                retry_strategy:
                    max_retries: 4
                    multiplier: 2
        routing:
            Wlindabla\ShareServiceBundle\Messenger\Message\ServiceMethodMessage: async_app_transport
```

```bash
php bin/console messenger:consume async_app_transport -vv
```

The dispatcher can delay a call (the user bundle uses this, for example, to hash an admin-form password after
45 seconds):

```php
$dispatcher->dispatch(
    service: SomeService::class,
    method: 'someMethod',
    params: ['id' => 42],
    date: new \DateTimeImmutable('+3 days'),   // optional
);
```

### The 2FA code format

`MfaCodeGenerator` makes a **20-character code: 13 digits and 7 uppercase letters, shuffled**. It is emailed
to the user and stored only as a hash. (The user bundle's 2FA form accepts spaces and lower-case letters
because it normalises the input.)

### Other things the bundle provides (not used by the user bundle directly)

Password generator, bot/browser request validator, signed browser challenge cookie, similarity checker,
form-error extractor. See its README.

---

## MailingBundle

### What it does

It centralises sending email in two families:

- **`system`** — automatic, one-way notifications (verification, password reset, 2FA code, account alerts).
  The user bundle uses only this family, through `MailerManagerInterface::notifyBySystem()`.
- **`support`** — correspondence with clients or the team (Reply-To handling, attachments, inline images).

### 1. Define the two Symfony mailer transports

```yaml
# config/packages/mailer.yaml
framework:
    mailer:
        transports:
            system: '%env(SYSTEM_MAILER_DSN)%'
            support: '%env(SUPPORT_MAILER_DSN)%'
        dkim_signer:                                          # optional: Symfony's own DKIM signing
            key: 'file://%kernel.project_dir%/var/certificates/dkim.pem'
            domain: 'example.com'
            select: 's1'
```

### 2. Configure the bundle

```yaml
# config/packages/wlindabla_mailing.yaml
wlindabla_mailing:
    from_addresses:
        system:
            address: '%env(APP_MAIL_FROM_SYSTEM)%'
            name: '%env(APP_MAIL_FROM_SYSTEM_NAME)%'
        support:
            address: '%env(APP_MAIL_FROM_SUPPORT)%'
            name: '%env(APP_MAIL_FROM_SUPPORT_NAME)%'
    dkim:                                                     # optional
        private_key: 'file://%kernel.project_dir%/var/certificates/dkim.pem'
        domain: '%env(APP_DKIM_DOMAIN)%'
        selector: 's1'
```

| Key | Meaning |
|---|---|
| `from_addresses.<type>.address` / `.name` | Sender of each family. Any key name is accepted; each service reads its own type. |
| `dkim.private_key`, `dkim.domain`, `dkim.selector` | The bundle's own DKIM settings. The README says the bundle never applies them automatically (`MailerFactory::applyDkimSignature()` must be called by you). Symfony's `dkim_signer` above is what signs normal traffic. |

### The API the user bundle calls

```php
$mailerManager->notifyBySystem(
    string|array $recipientAddress,
    string $subject,
    string $templatePath,      // a Twig template
    array $context,
    int $priority = PriorityInterface::PRIORITY_NORMAL,
): void;
```

Priorities (`PriorityInterface`, 1 to 5): `PRIORITY_HIGHEST` = 1 … `PRIORITY_LOWEST` = 5. The user bundle uses
`PRIORITY_HIGH` for the verification email and `PRIORITY_NORMAL` for the temporary password and account status
emails.

### Sending in the background

According to the MailingBundle README, `sendAsync()` only calls `sendNow()`; whether emails are queued
depends on **Messenger routing for `SendEmailMessage`**. To queue them, route that message:

```yaml
framework:
    messenger:
        routing:
            Symfony\Component\Mailer\Messenger\SendEmailMessage: email_transport
```

---

## A note on versions

The example application requires `"wlindabla/mailing-bundle": ">=1.0.6"` and
`"wlindabla/share-service-bundle": "*"`, while the user bundle asks for `^1.0` of both. Use the versions your
own `composer.lock` resolves, and check the two projects' changelogs when you upgrade.

Back to: [README](../README.md) · Next: [Example application](example-application.md)
