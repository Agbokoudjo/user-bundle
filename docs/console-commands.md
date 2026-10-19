# Console commands

[← Back to the README](../README.md)

## Contents

- [Summary](#summary)
- [User commands](#user-commands)
  - [`wlindabla:user:create`](#wlindablausercreate)
  - [`wlindabla:user:activate`](#wlindablauseractivate)
  - [`wlindabla:user:generate_temporary_password`](#wlindablausergenerate_temporary_password)
  - [`wlindabla:user:reset_password`](#wlindablauserreset_password)
- [Session commands](#session-commands)
  - [`wlindabla:user-session:revoke`](#wlindablauser-sessionrevoke)
  - [`wlindabla:user-session:revoke-all`](#wlindablauser-sessionrevoke-all)
- [Using the commands in scripts](#using-the-commands-in-scripts)

---

## Summary

| Command | Needs module | Arguments | Interactive |
|---|---|---|---|
| `wlindabla:user:create` | core | — | yes (asks username and email) |
| `wlindabla:user:activate` | core | — | yes (asks username, then a confirmation) |
| `wlindabla:user:generate_temporary_password` | core | `email` (required) | no |
| `wlindabla:user:reset_password` | core | `email` (optional) | yes (hidden password prompt) |
| `wlindabla:user-session:revoke` | user_session | `userIdentifier` (required) | confirmation |
| `wlindabla:user-session:revoke-all` | user_session | — | confirmation |

None of the commands has options other than Symfony's standard ones (`-n`, `-v`, …).

Several commands read the user by **username or email** through `UserManagerInterface`.

---

## User commands

### `wlindabla:user:create`

Creates a user. Description in the code: *"Create a user"*.

```bash
php bin/console wlindabla:user:create
# Enter username: Jane Doe
# Enter email: jane@example.com
# Created user "Jane Doe" with email "jane@example.com"
```

It asks for a username and an email (validated with `FILTER_VALIDATE_EMAIL`), creates the entity with
`UserManagerInterface::create()`, sets `createdAt` and saves it.

The behaviour depends on the parameter `app.env`:

| `app.env` | What the command also does |
|---|---|
| anything except `prod` | Enables the account, marks the email as verified (and sets `emailVerifiedAt`), and sets the plain password to a **fixed string followed by the username** (written in the source code). |
| `prod` | Nothing more: the user stays **disabled with no password**. Use `wlindabla:user:activate` next. |

> **Security warning.** In non-production environments the password is predictable (it is a constant in the
> source plus the username). Never run this command against a database that is reachable by others, and
> change the password right away. Also, the parameter `app.env` must exist in your container
> ([installation](installation.md#1-the-appsite_name-parameter)), otherwise the command fails with a
> "parameter not found" error.

### `wlindabla:user:activate`

Activates (or deactivates) an account. Description: *"Activate a user account"*.

```bash
php bin/console wlindabla:user:activate
# Enter username: jane@example.com
# Do you really want to activate "jane@example.com"? (yes/no) [yes]
```

The prompt accepts a **username, an email, or a numeric id** (the handler treats a numeric string as an id).
Behaviour of `ToggleUserAccountHandler`:

- if the account already has the requested state, nothing happens;
- on a **first activation** (no password hash yet), a temporary password is generated and set;
- the account is saved and a `ToggleUserAccountEvent` is dispatched, which sends an email to the user
  (with the temporary password if one was generated).

> **Read the confirmation carefully.** Answering **no** does *not* cancel: the code maps `no` to
> `AccountStatus::INACTIVE` and calls the handler, so the account is **deactivated** (and the final message
> says "activation was cancelled"). Also, an unknown username is silently ignored by the handler, but the command
> still prints a success message.

### `wlindabla:user:generate_temporary_password`

Generates a temporary password for a user and sends it by email.
Description: *"Generates a temporary password for a user based on their email address."*

| Argument | Required | Description |
|---|---|---|
| `email` | yes | The user's email address |

```bash
php bin/console wlindabla:user:generate_temporary_password jane@example.com
# Temporary password generation
# User found : jane@example.com (ID: 12)
# [OK] Temporary password generated and sent for "jane@example.com".
```

Exit code `1` if the user is not found or the handler throws. It calls
`GenerateTemporaryPasswordHandler::process()`, which sets the new password and dispatches an
`UpdatePasswordUserEvent` (the bundle's subscriber then sends the email).

### `wlindabla:user:reset_password`

Sets a new password chosen by an administrator. (Its class used to declare the wrong namespace `App\UI\Console`; this is fixed in the current source, see [known issues](known-issues.md#wrong-namespaces).) Description: *"Resets the password of a SonataUser using their
email address."* (the text mentions Sonata, but the command uses the bundle's `UserManagerInterface`).

| Argument | Required | Description |
|---|---|---|
| `email` | no | If omitted, the command asks for it |

```bash
php bin/console wlindabla:user:reset_password jane@example.com
# New password: ********        (hidden, 8 to 80 characters)
# Confirm new password: ********
# Confirm password reset for "jane@example.com"? (yes/no) [no]
```

If the two passwords differ, or you do not confirm, nothing is changed.

---

## Session commands

Registered only when `classes.user_session` is set.

### `wlindabla:user-session:revoke`

Revokes **all active sessions of one user** (reason `manual_logout`). The user is logged out everywhere.

| Argument | Required | Description |
|---|---|---|
| `userIdentifier` | yes | The user identifier, as stored in the session table (the **email**, since `getUserIdentifier()` returns the email) |

```bash
php bin/console wlindabla:user-session:revoke jane@example.com
# Revoke all active sessions for "jane@example.com"? They will be logged out everywhere. (yes/no) [no]
```

### `wlindabla:user-session:revoke-all`

Revokes **every active session of every user** (a global logout), reason `manual_logout`. It logs a warning with
the number of revoked sessions.

```bash
php bin/console wlindabla:user-session:revoke-all
# This will revoke EVERY active session, for EVERY user. Continue? (yes/no) [no]
# [OK] 17 session(s) revoked.
```

---

## Using the commands in scripts

The two session commands and `reset_password` ask for confirmation with a default of **no**. With
`--no-interaction` (`-n`) the default is used, so they stop without doing anything. Answer the prompt (for
example by piping `yes`) if you need to automate them, and be careful: `revoke-all` logs out the whole
application.

```bash
echo "yes" | php bin/console wlindabla:user-session:revoke jane@example.com
```

`wlindabla:user:activate` has a default of **yes**; with `-n` it activates.

Back to: [README](../README.md) · Next: [Events and extensibility](events-and-extensibility.md)
