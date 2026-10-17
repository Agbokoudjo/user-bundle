# Security integration (`security.yaml`)

[← Back to the README](../README.md)

## Contents

- [Complete example](#complete-example)
- [Line by line](#line-by-line)
- [`access_control` rules](#access_control-rules)
- [Listener order and why it matters](#listener-order-and-why-it-matters)
- [Remember-me](#remember-me)
- [Login: what happens after the password is correct](#login-what-happens-after-the-password-is-correct)
- [Logout](#logout)
- [Access denied (403)](#access-denied-403)

---

## Complete example

This example protects an administration area under `/admin`, with 2FA and the single-session policy.
The firewall is called `main`. That name must match `session_control.firewall_name` and
`two_factor.firewall_name`.

```yaml
# config/packages/security.yaml
security:
    password_hashers:
        Wlindabla\UserBundle\Infrastructure\Model\UserInterface: auto

    providers:
        app_user_provider:
            id: Wlindabla\UserBundle\Infrastructure\Security\Provider\UserProvider

    firewalls:
        dev:
            pattern: ^/(_(profiler|wdt)|css|images|js)/
            security: false

        main:
            lazy: true
            provider: app_user_provider

            form_login:
                login_path: wlindabla_user_login      # = wlindabla_user.routes.login
                check_path: wlindabla_user_login      # the login route also accepts POST
                enable_csrf: true                     # the login page generates the token id "authenticate"
                csrf_token_id: authenticate           # (Symfony's default value)

            logout:
                path: wlindabla_user_logout           # = wlindabla_user.routes.logout

            remember_me:
                secret: '%kernel.secret%'
                lifetime: 86400                       # keep equal to session_control.max_duration_hours (24 h)
                signature_properties: [password]

    access_control:
        # --- public pages of the bundle (order matters: first match wins) ---
        - { path: ^/admin/form/login$,                          roles: PUBLIC_ACCESS }
        - { path: ^/forget_password/form/request$,              roles: PUBLIC_ACCESS }
        - { path: ^/reset_password/form/request/confirm/,       roles: PUBLIC_ACCESS }
        - { path: ^/verify-email/,                              roles: PUBLIC_ACCESS }
        - { path: ^/session-conflict/,                          roles: PUBLIC_ACCESS }
        - { path: ^/email-change/,                              roles: PUBLIC_ACCESS }
        # --- 2FA pages: the user must already be logged in ---
        - { path: ^/admin/2fa/,                                 roles: IS_AUTHENTICATED_FULLY }
        # --- the protected area ---
        - { path: ^/admin,                                      roles: ROLE_ADMIN }
```

```yaml
# config/packages/wlindabla_user.yaml  (the matching bundle side)
wlindabla_user:
    classes:
        user: App\Entity\User
        user_session: App\Entity\UserSession
        two_factor_code: App\Entity\TwoFactorCode
    routes:
        home: app_home
        dashboard: admin_dashboard
    session_control:
        firewall_name: main
        max_duration_hours: 24
        excluded_routes:
            - wlindabla_user_two_factor_show
            - wlindabla_user_two_factor_verify
            - wlindabla_user_two_factor_resend
            - wlindabla_user_logout
    two_factor:
        firewall_name: main
        allowed_routes_while_pending:
            - wlindabla_user_two_factor_show
            - wlindabla_user_two_factor_verify
            - wlindabla_user_two_factor_resend
            - wlindabla_user_logout
```

> **Paths depend on your configuration.** The patterns above use the default paths. If you change
> `routes.login_path`, `password_reset.*_path`, or import the routes with a `prefix`, change the
> `access_control` patterns too. The three 2FA paths (`/admin/2fa/show|verify|resend`), the
> `/verify-email/…`, `/session-conflict/…` and `/email-change/…` paths are fixed in the controllers.
> With a route `prefix`, they get the prefix as well.

---

## Line by line

| Setting | Why |
|---|---|
| `providers.app_user_provider.id` | `UserProvider` loads the user by e-mail or username, caches a normalised copy, and refuses disabled accounts (`isEnabled() === false` → `UserNotFoundException`). |
| `password_hashers` | `DoctrineUserManager::updatePassword()` hashes `plainPassword` with Symfony's `UserPasswordHasherInterface`. The entity must be covered by a hasher. |
| `form_login.login_path` / `check_path` | The bundle's login route accepts `GET` and `POST`. The login controller only *renders* the form; Symfony's firewall handles the POST. |
| `csrf_token_id: authenticate` | The login controller creates the CSRF token with the id `authenticate` and passes it to the template as `csrf_token`. |
| `logout.path` | The bundle's logout route is a `GET` route with an empty controller. The firewall intercepts it. |
| `remember_me.lifetime` | The docs of the session module say `max_duration_hours` should be aligned with the remember-me cookie lifetime. |
| `lazy: true` | Optional. Works with the bundle's listeners because they call `Security::getUser()`. |

---

## `access_control` rules

| Page | Who may open it | Why |
|---|---|---|
| Login, password reset request, password reset confirmation | Anonymous visitors | The user is not logged in yet. |
| `/verify-email/{token}/{id}` | Anonymous visitors | The link comes from an email; the controller does not read the logged-in user. |
| `/email-change/...` | Anonymous visitors (recommended) | Same: links from emails. The controller does not read the logged-in user. |
| `/session-conflict/{userId}` | Anonymous visitors | In `block` mode the user is redirected here right after a refused login. |
| `/admin/2fa/*` | Authenticated users | The controller needs the current user (it redirects to login otherwise). |

---

## Listener order and why it matters

The bundle adds three request listeners and a few others. The numbers are Symfony event priorities;
**a higher number runs first**.

### `kernel.request`

| Priority | Listener | Job |
|---|---|---|
| 32 | Symfony `RouterListener` (framework) | Sets the `_route` attribute. The bundle's listeners read it. |
| 8 | Symfony security **Firewall** (framework) | Authenticates the user (form login, remember-me) and handles logout. |
| **7** | `TwoFactorAuthListener` | Blocks the request if a second factor is required. |
| **6** | `SessionControlListener` | Checks and registers the single session. |

The order **Firewall → 2FA → session** is deliberate:

1. The firewall must run first because both listeners call `Security::getUser()`. Before the firewall, there is
   no user.
2. `TwoFactorAuthListener` (7) must run **before** `SessionControlListener` (6). The source comment says:
   *"while the code isn't validated, no UserSession should be created for this browser."* If the session
   check ran first, a user who has not finished 2FA could already revoke another device's session
   (`takeover`) or be recorded as the active session.
3. Both listeners only act for the firewall named in the configuration. Requests that belong to another
   firewall (for example the `dev` firewall for the profiler) are ignored.

> **Ambiguity worth knowing.** Routes listed in `allowed_routes_while_pending` are *not* blocked by the 2FA
> listener, so they continue to `SessionControlListener`. If you do not also list them in
> `session_control.excluded_routes`, the session can be registered while the code is still pending. The
> example above lists them in both places on purpose. See [known issues](known-issues.md#session-registered-before-2fa).

### Security events

| Event | Priority | Listener | Job |
|---|---|---|---|
| `SecurityEvents::INTERACTIVE_LOGIN` | **10** | `LoginSubscriber::onRequireTwoFactor` | Sets the 2FA pending flag and sends the code. Runs first on purpose (see below). |
| `SecurityEvents::INTERACTIVE_LOGIN` | 0 | `LoginSubscriber::onLastLoginUpdate` | Dispatches the login log entry. |
| `SecurityEvents::INTERACTIVE_LOGIN` | 0 | `LoginSubscriber::onMarkPendingSession` | Stores the session registration data in the PHP session. |
| `LogoutEvent` | **10** | `LogoutSubscriber` | Revokes the `UserSession` row and clears the user cache. |

Why `onRequireTwoFactor` has priority 10: if a second factor is required, the session must not yet be
treated as "pending final registration". The 2FA flag must exist before anything else happens.

Why the session row is not created at login: at `INTERACTIVE_LOGIN` time the PHP session id is **not final** —
Symfony's session fixation protection regenerates it right after authentication. So `LoginSubscriber` only
stores the data under the key `_pending_session_registration`, and `SessionControlListener` creates the row on
the **next request**, when the id is final.

### `kernel.exception`

| Priority | Listener | Job |
|---|---|---|
| 5 | `EntityInUseExceptionListener` | Turns `EntityInUseException` into a JSON `409` (AJAX) or a flash message and a redirect. |
| 2 | `AccessDeniedSubscriber` | Shows a 403 page or JSON `403` for **authenticated** users. |

`AccessDeniedSubscriber` (priority 2) runs before Symfony's own security exception listener, so for logged-in
users the 403 page is rendered by the bundle. For anonymous users it does nothing and Symfony redirects to the
login page as usual.

---

## Remember-me

A user restored by a **remember-me cookie** does **not** trigger `INTERACTIVE_LOGIN`. That means the login
subscriber never sets the 2FA flag for them. The bundle closes this gap:

- `TwoFactorAuthListener` falls back to the freshness dates stored on the user account
  (`twoFactorValidatedAt`, `twoFactorLastCheckedAt`). If they are missing or too old, it starts a new
  challenge (context `remember_me_refresh`).
- `SessionControlListener` creates the `UserSession` row silently when it finds none for the current session id.

---

## Login: what happens after the password is correct

1. Symfony authenticates the user and dispatches `INTERACTIVE_LOGIN`.
2. If the 2FA module is enabled, `LoginSubscriber` sets `_2fa_pending = true` in the session **before** sending
   the code (fail closed: even if sending the email fails, access stays blocked), then dispatches
   `TwoFactorCodeManagerInterface::generateAndSendAsync()` through the async dispatcher.
3. A login log row is dispatched (`LoggerLoginUserManagerInterface::createAndSave`).
4. The pending session registration is stored in the session.
5. On the next request, `TwoFactorAuthListener` sends the user to the 2FA page. After the code is verified,
   `SessionControlListener` registers the session.

See [Two-factor authentication](two-factor-authentication.md) for the complete flow.

## Logout

`LogoutSubscriber` revokes the current `UserSession` row with the reason `manual_logout` and clears the
cached user. It does not need a route change: it listens to Symfony's `LogoutEvent`.

## Access denied (403)

For authenticated users, `AccessDeniedSubscriber` returns:

- JSON `403` `{"title": "...", "message": "..."}` if the request is an XHR request (`X-Requested-With`) or its
  preferred format is `json`;
- otherwise an HTML page rendered with the `access_denied` template.

Back to: [README](../README.md) · Next: [Entities and migrations](entities-and-migrations.md)
