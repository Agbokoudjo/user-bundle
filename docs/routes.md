# Routes

[← Back to the README](../README.md)

## Contents

- [Import the routes](#import-the-routes)
- [How the route loader works](#how-the-route-loader-works)
- [Route list](#route-list)
- [Customising paths and names](#customising-paths-and-names)
- [Encrypted ids in URLs](#encrypted-ids-in-urls)

---

## Import the routes

Create one routing file in your application:

```yaml
# config/routes/wlindabla_user.yaml
wlindabla_user:
    resource: '@WlindablaUserBundle/config/routes.php'
    # prefix: /account        # optional
```

That file (`config/routes.php` in the bundle) contains one line: it imports the resource `.` with the type
`wlindabla_user`, which is handled by `WlindablaRouteLoader`.

Check the result:

```bash
php bin/console debug:router | grep wlindabla
```

---

## How the route loader works

The bundle does not scan directories for routes. `WlindablaRouteLoader` imports the `#[Route]` attributes of a
**fixed list of controllers**, grouped by module, and only for the modules that are enabled:

| Module | Controllers imported |
|---|---|
| `core` (always) | `AdminFormLoginController`, `UserLogoutController`, `EmailVerificationController`, `PasswordResetRequestController`, `PasswordResetConfirmationController` |
| `user_session` | `SessionConflictController` |
| `two_factor` | `TwoFactorAuthController` |
| `email_change` | `EmailChangeConfirmationController` |

The loader also replaces `%wlindabla_user.…%` placeholders in route **names** and **paths** with the values
of your configuration, and refuses two routes with the same name. In debug mode, changing a configuration
value rebuilds the router cache.

---

## Route list

| Route name | Path (default) | Methods | Module | Role |
|---|---|---|---|---|
| `wlindabla_user_login` ¹ | `/admin/form/login` ¹ | GET, POST | core | Login page. Redirects already-authenticated users to the dashboard (or home) with a flash message. |
| `wlindabla_user_logout` ¹ | `/admin/form/logout` ¹ | GET | core | Logout. The controller is empty; Symfony's firewall intercepts it. |
| `wlindabla_user_verify_email` ² | `/verify-email/{token}/{id}` | GET | core | Email verification link. `token`: 32–64 alphanumeric characters. |
| `wlindabla_user_password_reset_request` | `/forget_password/form/request` ³ | GET, POST | core | "Forgot password" form. Rate limited by IP (`password_reset` limiter). |
| `wlindabla_user_password_reset_confirm` | `/reset_password/form/request/confirm/{token}/{id}` ³ | GET | core | Password reset link sent by email. `token`: 32–64 alphanumeric characters. |
| `wlindabla_user_session_conflict` | `/session-conflict/{userId}` | GET | user_session | Page shown to the browser that lost a session conflict. Revokes that browser's own session row. |
| `wlindabla_user_two_factor_show` | `/admin/2fa/show` ⁴ | GET | two_factor | Page where the user types the code. |
| `wlindabla_user_two_factor_verify` | `/admin/2fa/verify` ⁴ | POST | two_factor | Verifies the code. Form fields: `code`, `_csrf_token` (id `two_factor_verify`). |
| `wlindabla_user_two_factor_resend` | `/admin/2fa/resend` ⁴ | POST | two_factor | Sends a new code. CSRF token (id `two_factor_resend`) in the `X-CSRF-Token` header. |
| `wlindabla_user_email_change_confirm_old` | `/email-change/confirm-old/{token}/{id}` | GET | email_change | Confirm the change from the **old** address. |
| `wlindabla_user_email_change_confirm_new` | `/email-change/confirm-new/{token}/{id}` | GET | email_change | Confirm the change from the **new** address. |
| `wlindabla_user_email_change_not_me` | `/email-change/not-me/{token}/{id}` | GET | email_change | "This wasn't me" link sent to the old address. |

¹ Configurable with `wlindabla_user.routes.login|login_path|logout|logout_path`.
² The name was `wlindabla_user.verify.email` (with dots) in earlier versions. It is now `wlindabla_user_verify_email`,
like the other routes. If your own code or templates generate this route, update the name. The name is hard-coded in
`UserAccountEmailVerificationSubscriber`, which already uses the new one.
³ Configurable with `wlindabla_user.password_reset.request_path|confirm_path`.
⁴ **Not configurable.** The three 2FA paths and names are fixed in the controller attributes, and
`TwoFactorAuthListener` generates the URL of `wlindabla_user_two_factor_show` by that name.

---

## Customising paths and names

```yaml
wlindabla_user:
    routes:
        login: app_login
        login_path: /login
        logout: app_logout
        logout_path: /logout
    password_reset:
        request_path: /password/forgot
        confirm_path: /password/reset/{token}/{id}
```

If you rename the login or logout route, update `security.yaml` to the new names. Keep `{token}` and `{id}` in
`confirm_path`.

## Encrypted ids in URLs

Routes that contain `{id}` or `{userId}` do **not** carry the raw database id. The bundle sends these ids
through `IdEncryptionInterface` (ShareServiceBundle) when it builds the links, and registers the route
parameter names `id` and `userId` so ShareServiceBundle can decrypt them for the controllers. The resolver
only works after you set `encryption_key` in the ShareServiceBundle configuration
(see [installation](installation.md#5-shareservicebundle-and-mailingbundle-configuration)).

Back to: [README](../README.md) · Next: [Two-factor authentication](two-factor-authentication.md)
