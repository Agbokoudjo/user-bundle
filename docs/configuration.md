# Configuration reference

[← Back to the README](../README.md)

The configuration root is `wlindabla_user`. The tree below is taken from `WlindablaUserBundle::configure()`.
Only keys that exist in the code are listed. Keys that are accepted but **not used anywhere** are marked
with ⚠️ and explained in [known issues](known-issues.md).

## Contents

- [Full commented example](#full-commented-example)
- [Reference by section](#reference-by-section)
  - [`classes`](#classes)
  - [`default_role`](#default_role)
  - [`security`](#security)
  - [`routes`](#routes)
  - [`templates`](#templates)
  - [`password_reset`](#password_reset)
  - [`session_control`](#session_control)
  - [`two_factor`](#two_factor)
  - [`email_change`](#email_change)
- [Validation rules](#validation-rules-checked-when-the-container-is-built)
- [Container parameters created by the bundle](#container-parameters-created-by-the-bundle)
- [Doctrine and ShareServiceBundle settings added automatically](#settings-added-automatically-by-the-bundle)

---

## Full commented example

```yaml
# config/packages/wlindabla_user.yaml
wlindabla_user:

    # ------------------------------------------------------------------
    # Entity classes of YOUR application. Only "user" is required.
    # Setting a class switches the matching module on.
    # ------------------------------------------------------------------
    classes:
        user: App\Entity\User                      # REQUIRED. Must implement Wlindabla\UserBundle\Infrastructure\Model\UserInterface
        user_session: App\Entity\UserSession       # Enables the single-session module. Requires session_control.firewall_name
        logger_login_user: App\Entity\LoggerLoginUser  # Enables the login history module
        two_factor_code: App\Entity\TwoFactorCode  # Enables 2FA. Requires two_factor.firewall_name

        # Permission module: set ALL FOUR or NONE.
        permission_role: App\Entity\PermissionRole
        permission_group: App\Entity\PermissionGroup
        user_permission_role: App\Entity\UserPermissionRole
        user_permission_group: App\Entity\UserPermissionGroup

        # Email change module: set BOTH or NONE.
        email_change_request: App\Entity\EmailChangeRequest
        email_change_validation: App\Entity\EmailChangeValidation

        # user_token: ~                            # ⚠️ accepted but never read by the code

    # ⚠️ Role added to every user. Never use ROLE_ADMIN here.
    # The value is stored in a parameter, but the code that actually adds the role
    # is the constant AbstractBaseUser::ROLE_DEFAULT ('ROLE_USER'). See known issues.
    default_role: ROLE_USER

    security:
        # Lifetime (seconds) of the cached user inside UserProvider.
        # 0 (default) = effectively no caching. Keep it small (see two-factor docs).
        user_cache_ttl: 0

    routes:
        login: wlindabla_user_login        # NAME of the login route (used in redirects and emails)
        login_path: /admin/form/login      # PATH of the login page
        logout: wlindabla_user_logout      # NAME of the logout route
        logout_path: /admin/logout         # PATH of the logout route
        home: app_home                     # Name of YOUR home route (default null: bundle falls back to "/")
        dashboard: admin_dashboard         # Name of YOUR dashboard route (default null). Used when an already
                                           # authenticated user visits the login page

    # Every key is a Twig template path. Override only the ones you want to change.
    templates:
        login: '@WlindablaUser/security/login.html.twig'
        two_factor_show: 'security/two_factor.html.twig'      # example of an override
        # ... see the full list in the "templates" section below

    password_reset:
        request_path: /forget_password/form/request                          # page where the user asks for a reset email
        confirm_path: /reset_password/form/request/confirm/{token}/{id}      # link sent by email. Keep {token} and {id}

    session_control:                       # used only if classes.user_session is set
        policy: takeover                   # "takeover" or "block" (see session-control.md)
        firewall_name: main                # REQUIRED when the module is on: the firewall to protect
        inactivity_hours: 4                # used by the cleanup job only (needs symfony/scheduler)
        max_duration_hours: 24             # absolute session age; used by the listener AND the cleanup job
        excluded_routes:                   # route names the session check must skip
            - wlindabla_user_two_factor_show
            - wlindabla_user_two_factor_verify
            - wlindabla_user_two_factor_resend
            - wlindabla_user_logout
        redis_session_prefix: ~            # ⚠️ accepted but never read by the code

    two_factor:                            # used only if classes.two_factor_code is set
        firewall_name: main                # REQUIRED when the module is on. Same firewall as session_control
        allowed_routes_while_pending:      # route names reachable while a code is pending. MUST include the 2FA routes
            - wlindabla_user_two_factor_show
            - wlindabla_user_two_factor_verify
            - wlindabla_user_two_factor_resend
            - wlindabla_user_logout
        inactivity_threshold_seconds: 7200      # sliding window: 2 hours without activity => new code (default 2*3600)
        absolute_cap_seconds: 86400             # hard limit since the last validation: 24 hours (default 24*3600)
        activity_touch_throttle_seconds: 300    # write the activity heartbeat at most every 5 minutes (default 5*60)
        support_ajax_json_response: false       # false = HTTP redirect for everyone; true = JSON 401 for AJAX requests

    email_change:                          # used only if the email change module is on
        token_lifetime: 3600               # seconds a request/token stays valid (default 1 hour)
        retry_cooldown: 86400              # seconds between two requests for the same account (default 24 hours)
        exempt_roles_on_self:              # roles exempt from double validation when changing their OWN email
            - ROLE_SUPER_ADMIN
        recovery_initiator_role: ROLE_SUPER_ADMIN   # role allowed to start a "recovery" change
```

---

## Reference by section

### `classes`

| Key | Type | Default | Required | Notes |
|---|---|---|---|---|
| `user` | string (FQCN) | — | **yes** | Must implement `Wlindabla\UserBundle\Infrastructure\Model\UserInterface` (extend `BaseUser`). Mapped for Doctrine with `resolve_target_entities`. |
| `user_token` | string \| null | `null` | no | ⚠️ Not read anywhere in the code. |
| `user_session` | string \| null | `null` | no | Switches on the **user session** module. |
| `logger_login_user` | string \| null | `null` | no | Switches on the **login logger** module. |
| `two_factor_code` | string \| null | `null` | no | Switches on the **two-factor** module. |
| `permission_role` | string \| null | `null` | no | Permission module (all four required together). |
| `permission_group` | string \| null | `null` | no | Permission module. |
| `user_permission_role` | string \| null | `null` | no | Permission module. |
| `user_permission_group` | string \| null | `null` | no | Permission module. |
| `email_change_request` | string \| null | `null` | no | Email change module (both required together). |
| `email_change_validation` | string \| null | `null` | no | Email change module. |

The bundle also registers Doctrine `resolve_target_entities` for these interfaces
(only for the classes you set): `UserInterface`, `PermissionRoleInterface`, `PermissionGroupInterface`,
`UserPermissionRoleInterface`, `UserPermissionGroupInterface`, `EmailChangeRequestInterface`,
`EmailChangeValidationInterface`.

### `default_role`

| Type | Default | Notes |
|---|---|---|
| string (not empty) | `ROLE_USER` | ⚠️ The value is stored in the parameter `wlindabla_user.default_role`, but no code reads that parameter. `AbstractBaseUser::getRoles()` always adds the constant `ROLE_DEFAULT` (`ROLE_USER`). To change the default role, override the constant in your entity (`public const string ROLE_DEFAULT = '…'`; the code uses `static::ROLE_DEFAULT`). |

### `security`

| Key | Type | Default | Meaning |
|---|---|---|---|
| `user_cache_ttl` | integer ≥ 0 | `0` | Seconds that `UserProvider` keeps a normalised copy of the user in the cache (both at login and on every `refreshUser()`). `0` is passed to Symfony's `expiresAfter(0)`, which makes the entry expire immediately, so with the default there is effectively no caching. The cache is invalidated when a user is updated, enabled/disabled, or changes roles. |

### `routes`

| Key | Type | Default | Meaning |
|---|---|---|---|
| `login` | string | `wlindabla_user_login` | **Route name** of the login page. Used in redirects and emails. |
| `login_path` | string | `/admin/form/login` | **URL path** of the login page. |
| `logout` | string | `wlindabla_user_logout` | **Route name** of the logout route. |
| `logout_path` | string | `/admin/logout` | **URL path** of the logout route. |
| `home` | string \| null | `null` | Name of your home route. When `null`, redirects fall back to `/`. |
| `dashboard` | string \| null | `null` | Name of your dashboard route. Used first when an authenticated user opens the login page. |

These four route settings are placed into the `#[Route]` attributes with `%wlindabla_user.routes.*%`
placeholders and resolved by `WlindablaRouteLoader`. Route names must stay unique across the bundle's
routes, otherwise a `LogicException` is thrown.

### `templates`

All values are Twig template paths. Defaults:

| Key | Default |
|---|---|
| `session_conflict` | `@WlindablaUser/security/sessionConflict.html.twig` |
| `login` | `@WlindablaUser/security/login.html.twig` |
| `access_denied` | `@WlindablaUser/security/error403.html.twig` |
| `outcome_page` | `@WlindablaUser/email/security/checkEmail.html.twig` |
| `email_verification` | `@WlindablaUser/email/profile/admin_account_confirmation.html.twig` |
| `email_verification_result` | `@WlindablaUser/email/security/checkEmail.html.twig` |
| `toggle_user_account` | `@WlindablaUser/email/profile/toggle_user_account.html.twig` |
| `temporary_password` | `@WlindablaUser/email/profile/temporary_password.html.twig` |
| `password_reset_request` | `@WlindablaUser/security/passwordResetRequest.html.twig` |
| `password_reset_email` | `@WlindablaUser/email/security/passwordResetRequest.html.twig` |
| `two_factor_show` | `@WlindablaUser/security/twoFactorAuth.html.twig` |
| `two_factor_code_email` | `@WlindablaUser/email/security/twoFactorCode.html.twig` |
| `email_change_old_notification` | `@WlindablaUser/email/security/emailChangeOldNotification.html.twig` |
| `email_change_new_confirmation` | `@WlindablaUser/email/security/emailChangeNewConfirmation.html.twig` |
| `email_change_applied` | `@WlindablaUser/email/security/emailChangeApplied.html.twig` |
| `email_change_not_me_alert` | `@WlindablaUser/email/security/emailChangeNotMeAlert.html.twig` |
| `email_change_validation_pending` | `@WlindablaUser/email/security/emailChangeValidationPending.html.twig` |
| `email_change_validation_outcome` | `@WlindablaUser/email/security/emailChangeValidationOutcome.html.twig` |

Templates are looked up by key through `TemplateRegistry::get()`. Asking for a key that does not exist
throws an `InvalidArgumentException`. See [events and extensibility](events-and-extensibility.md#templates)
for the variables each template receives.

### `password_reset`

| Key | Default | Meaning |
|---|---|---|
| `request_path` | `/forget_password/form/request` | Path of the "I forgot my password" page. |
| `confirm_path` | `/reset_password/form/request/confirm/{token}/{id}` | Path of the confirmation link sent by email. It must contain `{token}` and `{id}`; the code does not check this for you. |

### `session_control`

| Key | Type | Default | Meaning |
|---|---|---|---|
| `policy` | string | `takeover` | `takeover`: the new login revokes the old sessions. `block`: the new login is refused while another session is active. Any value other than `block` behaves like `takeover` (the value is not validated). |
| `firewall_name` | string \| null | `null` | **Required** when `classes.user_session` is set. Name of the firewall to guard. |
| `inactivity_hours` | integer | `4` | Sessions inactive for this long are revoked by the **cleanup job**. Without `symfony/scheduler` this value has no effect. |
| `max_duration_hours` | integer | `24` | Maximum session age. Used by the cleanup job and shown in the "expired" message. Also passed to `SessionControlListener`. Align it with your `remember_me` lifetime. |
| `excluded_routes` | list of strings | `[]` | Route names the listener ignores. |
| `redis_session_prefix` | string \| null | `null` | ⚠️ Never read by the code. See [session control](session-control.md#redis-session-eviction). |

### `two_factor`

| Key | Type | Default | Meaning |
|---|---|---|---|
| `firewall_name` | string \| null | `null` | **Required** when `classes.two_factor_code` is set. Use the same firewall as `session_control`. |
| `allowed_routes_while_pending` | list of strings | `[]` | Route names that stay reachable while a code is pending. **Must contain the three 2FA routes and the logout route**, otherwise you get a redirect loop (see below). |
| `inactivity_threshold_seconds` | integer | `7200` | Sliding window. If the last activity is older than this, a new code is required. |
| `absolute_cap_seconds` | integer | `86400` | Maximum age of the last successful validation, even if the user is always active. |
| `activity_touch_throttle_seconds` | integer | `300` | The activity timestamp is written at most once per this many seconds. |
| `support_ajax_json_response` | boolean | `false` | `false`: always an HTTP redirect. `true`: JSON `401` for AJAX (XHR) requests. See [frontend and AJAX](frontend-ajax.md). |

> **Redirect loop warning.** `TwoFactorAuthListener` blocks every request of the guarded firewall while a
> code is pending, except routes listed in `allowed_routes_while_pending`. The default list is empty, so the
> 2FA page itself would be redirected to the 2FA page forever. Always list
> `wlindabla_user_two_factor_show`, `wlindabla_user_two_factor_verify`, `wlindabla_user_two_factor_resend`
> and your logout route name.

### `email_change`

| Key | Type | Default | Meaning |
|---|---|---|---|
| `token_lifetime` | integer | `3600` | Validity of an email change request, in seconds. |
| `retry_cooldown` | integer | `86400` | Minimum delay between two requests for the same account, in seconds. |
| `exempt_roles_on_self` | list of strings | `['ROLE_SUPER_ADMIN']` | Roles that skip the double human validation when they change their own email. |
| `recovery_initiator_role` | string | `ROLE_SUPER_ADMIN` | Role allowed to start a recovery request. |

---

## Validation rules checked when the container is built

`WlindablaUserBundle::loadExtension()` throws a `\LogicException` in these cases:

| Situation | Message (short) |
|---|---|
| `classes.user_session` set but `session_control.firewall_name` is `null` | `…session_control.firewall_name is required.` |
| `classes.two_factor_code` set but `two_factor.firewall_name` is `null` | `…two_factor.firewall_name is required.` |
| Only some of the four permission classes are set | `The Permission module requires all four …` |
| Only one of the two email change classes is set | `The EmailChange module requires both …` |

---

## Container parameters created by the bundle

You can read them in your own services (`%wlindabla_user.…%`):

```
wlindabla_user.classes.<key>                       (one per classes.* key)
wlindabla_user.default_role
wlindabla_user.security.user_cache_ttl
wlindabla_user.templates                           (the whole templates array)
wlindabla_user.routes.{login,login_path,logout,logout_path,home,dashboard}
wlindabla_user.password_reset.{request_path,confirm_path}
wlindabla_user.session_control.{policy,inactivity_hours,max_duration_hours,firewall_name,excluded_routes}
wlindabla_user.two_factor.{firewall_name,allowed_routes_while_pending,inactivity_threshold_seconds,
                           absolute_cap_seconds,activity_touch_throttle_seconds,support_ajax_json_response}
wlindabla_user.email_change.{token_lifetime,retry_cooldown,exempt_roles_on_self,recovery_initiator_role}
wlindabla_user.routing.enabled_modules             (list: core, user_session, two_factor, email_change)
```

---

## Settings added automatically by the bundle

You do not have to write these; they are listed so nothing surprises you.

```yaml
# Added through prependExtension()
doctrine:
    orm:
        resolve_target_entities:
            Wlindabla\UserBundle\Infrastructure\Model\UserInterface: '%classes.user%'
            # + one line per optional interface whose class you set

# Only if wlindabla_share_service is registered
wlindabla_share_service:
    async_method_dispatcher:
        allowed_services: [ ... every service this bundle dispatches asynchronously ... ]
    id_encryption:
        identity_key_collection: [id, userId]
```

Back to: [README](../README.md) · Next: [Security integration](security-integration.md)
