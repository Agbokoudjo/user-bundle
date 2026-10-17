# Session control (single-session policy)

[← Back to the README](../README.md)

This **optional module** makes sure a user has only **one active session** at a time, and detects a second
login from another device. It is switched on when `wlindabla_user.classes.user_session` is set (and then
`session_control.firewall_name` is required).

## Contents

- [Enable it](#enable-it)
- [How it works](#how-it-works)
- [Policies: `takeover` and `block`](#policies-takeover-and-block)
- [Revocation reasons](#revocation-reasons)
- [What the user and the frontend see](#what-the-user-and-the-frontend-see)
- [Automatic cleanup](#automatic-cleanup)
- [Redis session eviction](#redis-session-eviction)
- [Console commands](#console-commands)

---

## Enable it

```yaml
# config/packages/wlindabla_user.yaml
wlindabla_user:
    classes:
        user_session: App\Entity\UserSession
    session_control:
        firewall_name: main
        policy: takeover          # or "block"
        max_duration_hours: 24
        inactivity_hours: 4       # used by the cleanup job
        excluded_routes:
            - wlindabla_user_logout
```

Create the `UserSession` entity and repository first ([entities](entities-and-migrations.md#other-entities)).
The module also needs a Lock configuration ([installation](installation.md#6-locks-user-session-module)).

---

## How it works

A row in the `user_sessions` table represents one PHP session of one user: user identifier, session id, IP,
user agent, a device fingerprint (hash of User-Agent + Accept-Language), creation date, last activity, an
`active` flag and a revoked reason. Revoked rows are kept for audit.

On every main request of the guarded firewall, `SessionControlListener` (priority 6) does this:

| Step | Situation | Action |
|---|---|---|
| 1 | The login subscriber left a **pending registration** in the session | Remove it. In `block` mode, if another session is already active, create a revoked row (reason `conflict`) and answer with the conflict response. Otherwise **create the session**: this revokes all the user's other active sessions (reason `conflict`) inside a lock per user. |
| 2 | No pending registration, and **no row** exists for this session id | Create the session silently (typical for a remember-me restoration). |
| 2b | A row exists and is **active** | Dispatch `updateSessionActivity` asynchronously. |
| 3 | A row exists and is **revoked** | Answer with the revoked response (see below). |

Why registration happens on the *next* request: the session id changes right after authentication, so the
login subscriber only leaves the data in the session and this listener writes the row with the final id.
The firewall must run first and `TwoFactorAuthListener` second
([listener order](security-integration.md#listener-order-and-why-it-matters)).

Any exception inside the listener is caught and logged (`SessionControlListener error.`); the request then
continues. So a database problem does not lock everybody out.

---

## Policies: `takeover` and `block`

| Policy | What happens at the new login |
|---|---|
| `takeover` (default) | The new session wins. The older sessions are revoked with the reason `conflict`. When the old browser makes its next request it is told its session was revoked. |
| `block` | The new login loses. A revoked row is stored for the new browser, and that browser is redirected to the **conflict page**, which says the account is already used elsewhere. The existing session stays active. |

The `policy` value is not validated: anything other than `block` behaves as `takeover`.

---

## Revocation reasons

`UserSessionRevokedReason` (stored as a string):

| Value | Meaning | Translation key |
|---|---|---|
| `conflict` | Another login on another device | `session_revoked.conflict` |
| `expired_inactivity` | Inactive for longer than `inactivity_hours` (cleanup job) | `session_revoked.expired_inactivity` |
| `expired_max_duration` | Older than `max_duration_hours` (cleanup job) | `session_revoked.expired_max_duration` (uses `%hours%`) |
| `manual_logout` | The user logged out, or an admin command revoked it | `session_revoked.manual_logout` |
| `security_email_changed` | The email of the account was changed | `session_revoked.security_email_changed` |

Translations live in the domain `WlindablaUserBundle` (English and French are provided).

---

## What the user and the frontend see

When `SessionControlListener` finds a **revoked** session:

| Request type | Response |
|---|---|
| **AJAX** (`X-Requested-With: XMLHttpRequest`) | JSON `401`: `{"error": "<reason value>", "message": "<translated message>"}`. This does **not** depend on `support_ajax_json_response`. |
| Normal navigation, reason `conflict` | Redirect to `wlindabla_user_session_conflict` (path `/session-conflict/{userId}`, id encrypted). |
| Normal navigation, any other reason | The PHP session is invalidated, a `warning` flash message is added and the user is logged out through the firewall. |

For an AJAX request the session is **not** invalidated: the JSON is returned and nothing else happens.
So the frontend should react by loading a full page, which then goes through the navigation rules above.
See [Frontend and AJAX](frontend-ajax.md#session-revoked-responses).

The conflict page (`SessionConflictController`) also revokes the current browser's own row with the reason
`conflict` and shows the `session_conflict` template with `site_name` and `logout_url`.

---

## Automatic cleanup

Two cleanup rules (`UserSessionManager::cleanExpiredSessions()`):

- revoke active sessions **inactive** for more than `inactivity_hours` (reason `expired_inactivity`);
- revoke active sessions **older than** `max_duration_hours`, even if the user is active (reason `expired_max_duration`).

They run from a Symfony Scheduler schedule named `wlindabla_user_session_cleanup`, **every 15 minutes**
(`SessionCleanupSchedule`, handler `RevokeInactiveSessionsHandler`). The services are registered only if
`symfony/scheduler` is installed.

```bash
composer require symfony/scheduler
php bin/console messenger:consume scheduler_wlindabla_user_session_cleanup -vv
```

(The transport name `scheduler_<schedule name>` is the Symfony Scheduler convention.) Without the scheduler,
`inactivity_hours` has no effect, and sessions are only revoked by conflicts, logouts or the console commands.
You can also call the manager yourself:

```php
$result = $userSessionManager->cleanExpiredSessions(4, 24);
// ['inactivity' => UserSessionInterface[], 'maxDuration' => UserSessionInterface[]]
```

---

## Redis session eviction

When a session is revoked, the manager calls `SessionStoreEvictorInterface::evict($sessionId)` so the PHP
session can be removed from the session store. The bundle registers `NullSessionStoreEvictor` (does nothing).
A `RedisSessionStoreEvictor` exists in the code (`UNLINK <prefix><sessionId>`, default prefix
`sf_wlindabla_user_s`) but **nothing selects it**: the setting `session_control.redis_session_prefix` is
accepted and never read.

To use it, define the services in your application (your definitions override the bundle's with the same id):

```yaml
# config/services.yaml
services:
    Wlindabla\UserBundle\Infrastructure\UserSession\RedisSessionStoreEvictor:
        arguments:
            $redis: '@Redis'           # your \Redis service
            $prefix: 'sess_'           # must match the prefix used by your Redis session handler

    Wlindabla\UserBundle\Domain\UserSession\Application\Service\SessionStoreEvictorInterface:
        alias: Wlindabla\UserBundle\Infrastructure\UserSession\RedisSessionStoreEvictor
```

The prefix must be the one your Symfony Redis session handler really uses (the default of Symfony's
`RedisSessionHandler` is `sess_`). Check your own configuration.

---

## Console commands

| Command | Purpose |
|---|---|
| `wlindabla:user-session:revoke <userIdentifier>` | Revoke every active session of one user |
| `wlindabla:user-session:revoke-all` | Revoke every active session of every user (global logout) |

Details in [Console commands](console-commands.md#session-commands).

Back to: [README](../README.md) · Next: [Frontend and AJAX](frontend-ajax.md)
