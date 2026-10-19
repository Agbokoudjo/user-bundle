# Admin Back-Office Integration

The bundle does **not** ship an admin interface. It ships the *actions* an admin needs
(activate/deactivate an account, resend the verification email, regenerate a temporary
password, detect duplicate users, approve/reject an email change) and leaves the screens to
your application.

It was designed with **SonataAdmin / SonataUserBundle** projects in mind, but nothing in the
actions depends on Sonata. **EasyAdmin**, a hand-written back-office or any other admin tool
can use them in the same way.

## Table of contents

- [The three layers](#the-three-layers)
- [`MicroUserCRUDActionTrait`](#microusercrudactiontrait)
- [Using the trait with SonataAdmin](#using-the-trait-with-sonataadmin)
- [Using the bundle without Sonata (EasyAdmin or custom controllers)](#using-the-bundle-without-sonata-easyadmin-or-custom-controllers)
- [Example-app admin classes](#example-app-admin-classes)
- [Things to know](#things-to-know)

## The three layers

| Layer | Who writes it | Example in the example app | Role |
|---|---|---|---|
| Admin class | Your application | `SonataUserAdmin`, `LoggerLoginAdmin`, `UserSessionAdmin` | Lists, filters, forms, routes, menu entries |
| CRUD controller | Your application | `AdminUserCRUDController` extends `WlindablaAdminCRUDController` | Receives the admin requests and runs the actions |
| Action trait | **The bundle** | `MicroUserCRUDActionTrait` | The ready-made account actions |

Only the third layer comes from the bundle. The first two are yours, because they depend on
your admin framework, your entities and your design.

## `MicroUserCRUDActionTrait`

**Namespace:** `Wlindabla\UserBundle\UI\Http\Controller\Admin\Action\MicroUserCRUDActionTrait`

Add `use MicroUserCRUDActionTrait;` to your own CRUD controller. Every action returns a
`JsonResponse` shaped like this, so your front end can display it as a flash message:

```json
{ "title": "Operation successful", "type": "success", "message": "Account activated successfully - john (ID: 12)" }
```

`type` is `success`, `warning` or `danger` (a Bootstrap-style alert type). Titles and messages
are translated with the `WlindablaUserBundle` domain, keys under `user_action.*`
(`translations/WlindablaUserBundle.en.yaml` and `.fr.yaml`).

### Actions

| Action method | HTTP method | What it does |
|---|---|---|
| `resendEmailVerifyAction` | GET (AJAX) | Resends the verification email through `EmailVerificationInterface::resendVerificationEmail()`. Refused if the email is already verified. Returns 429 if the resend cooldown is still running. |
| `toggleEnabledUserAccountAction` | PATCH (AJAX) | Activates or deactivates an account. Reads a `status` value (`true/false`, `1/0`, `on/off`, `yes/no`) from the request payload. Refused if the email is not verified, or if you try to deactivate **your own** account. The change itself runs asynchronously in `ToggleUserAccountHandler`. |
| `regenerateTemporyPasswordUserAction` | PATCH (AJAX) | Dispatches `GenerateTemporaryPasswordHandler::process()` asynchronously. Refused if the email is not verified, or if you target **your own** account. |
| `approveAction` / `rejectAction` | POST (AJAX) | Records a validator's decision on an email-change request (see [email-change.md](email-change.md)). An optional `comment` field is read from the payload. |

Each action throws a `BadRequestHttpException` when it is called with the wrong HTTP method
or without the AJAX marker (`X-Requested-With: XMLHttpRequest`, or `_xml_http_request=1` in
the query string or body).

The method names `resendEmailVerifyAction`, `regenerateTemporyPasswordUserAction` and the
route name `regenerate_tempory_password_user` keep the original spelling ("tempory"). Use them
exactly as written.

### Helper: `validateUser(...)`

A `protected` method to call **before creating** a user. It returns `null` when everything is
fine, or a ready-to-send `409 Conflict` JSON response when:

1. the username or the email is already used (`UserManagerInterface::findOneByUsernameOrEmail()`), or
2. the username is suspiciously close to an existing one (`UserDuplicateFinderService::findDuplicates()`, which relies on PostgreSQL `pg_trgm`).

In both cases a warning is logged through the async dispatcher (`LoggerInterface::warning`).

```php
protected function preCreate(Request $request, object $newObject): ?Response
{
    if (!$request->isMethod('POST')) {
        return null;
    }

    return $this->validateUser(
        new SubmittedUserDto($request->request->get('username'), $request->request->get('email')),
        $this->userManager,
        $this->userDuplicateFinder,
        $this->asyncMethodDispatcher,
        $this->translator,
    );
}
```

> The near-duplicate check needs the `pg_trgm` extension. On another database, override
> `findSimilarCandidates()` in your repository (see [known-issues.md](known-issues.md)).

### Other helpers you can reuse

| Helper | Purpose |
|---|---|
| `createFlashJsonResponse($type, $title, $message, $status)` | Builds the JSON shape shown above (already-translated text). |
| `createTranslatedFlashJsonResponse($translator, $type, $titleKey, $messageKey, …)` | Same, from translation keys of the `WlindablaUserBundle` domain. |
| `bundleTrans($translator, $key, $params)` | Translates with the `WlindablaUserBundle` domain. |
| `getStatusFromRequest($request)` | Reads and validates the `status` boolean. |
| `_isXmlHttpRequest($request)` | The AJAX check described above. |

### `ResolvesManagedCurrentUserTrait`

The trait uses `Wlindabla\UserBundle\UI\Http\Controller\Trait\ResolvesManagedCurrentUserTrait`.
Use it whenever you attach the logged-in user to another entity (for example as
`EmailChangeRequest::$requestedBy` or as a validator).

Why: the user provided by Symfony Security is rebuilt from a cache on every request. Doctrine
does not know that object, so persisting it or linking it to another entity can trigger an
`INSERT` instead of an association. `resolveManagedCurrentUser($userManager, $currentUser)`
reloads the user through the `UserManager`, so you get a managed entity.

### Authorization is your job

The trait **does not check permissions**. Add the check in your controller (Sonata's
`$this->admin->checkAccess()`, a Symfony voter, `#[IsGranted]`, …) before an action runs.
Without it, anybody who can reach the route could call the action.

## Using the trait with SonataAdmin

1. Create the CRUD controller. Extend Sonata's `CRUDController` (or your own base class, see
   [Example-app admin classes](#example-app-admin-classes)) and add the trait. Inject the
   services your own `preCreate()` needs.
2. Register the controller on the admin (`controller` option of the `sonata.admin` tag, or
   `admin.xml`/`services.php`).
3. Add one route per action in the admin's `configureRoutes()`:

```php
protected function configureRoutes(RouteCollectionInterface $collection): void
{
    $collection->add('toggle_enabled_user_account', '{id}/toggle_enabled_user_account', [
        '_controller'   => AdminUserCRUDController::class . '::toggleEnabledUserAccountAction',
        '_sonata_admin' => $this->getCode(),
    ], [], [], '', [], ['PATCH']);

    $collection->add('resend_email_verify', '{id}/resend_email_verify', [
        '_controller'   => AdminUserCRUDController::class . '::resendEmailVerifyAction',
        '_sonata_admin' => $this->getCode(),
    ], [], [], '', [], ['GET']);

    $collection->add('regenerate_tempory_password_user', '{id}/regenerate_tempory_password_user', [
        '_controller'   => AdminUserCRUDController::class . '::regenerateTemporyPasswordUserAction',
        '_sonata_admin' => $this->getCode(),
    ], [], [], '', [], ['PATCH']);
}
```

4. Add buttons or list actions (`createToggleEnabledAction()` and similar) that call these
   routes with `fetch()` / XHR, then show the returned `title` + `message` as a flash.

To enable the email-change vote actions, add two more routes pointing to `approveAction` and
`rejectAction` (POST).

## Using the bundle without Sonata (EasyAdmin or custom controllers)

The trait only needs the Symfony `Request`, so it works in any controller. Two options:

**Option A — reuse the trait.** Add `use MicroUserCRUDActionTrait;` to a normal
`AbstractController`, declare the routes yourself with `#[Route]`, and call them from your
EasyAdmin custom action (a button that sends an AJAX request).

**Option B — call the bundle services directly.** This is the most flexible way, and the one
to use if you want your own responses or a non-JSON UI. These are the services behind each
action:

| You want to… | Call |
|---|---|
| Find a user | `UserManagerInterface::find($id)` |
| Resend the verification email | `EmailVerificationInterface::resendVerificationEmail($id)` |
| Activate / deactivate an account | `AsyncMethodDispatcherInterface::dispatch(ToggleUserAccountHandler::class, 'handle', [$id, AccountStatus::ACTIVE])` (or `INACTIVE`) |
| Regenerate a temporary password | `AsyncMethodDispatcherInterface::dispatch(GenerateTemporaryPasswordHandler::class, 'process', [$id])` |
| Detect duplicates before creating | `UserManagerInterface::findOneByUsernameOrEmail()` and `UserDuplicateFinderService::findDuplicates()` / `hasSuspectedDuplicate()` |
| Approve / reject an email change | `EmailChangeRequestServiceInterface::submitValidation(request: …, validator: …, approved: true|false, comment: …)` |
| List the people who may validate an email change | `EmailChangeValidatorPolicyInterface` (you implement it) |

Example with a plain Symfony controller (usable from EasyAdmin through a custom action URL):

```php
#[Route('/admin/users/{id}/toggle', methods: ['PATCH'])]
#[IsGranted('ROLE_ADMIN')]
public function toggle(
    int|string $id,
    Request $request,
    UserManagerInterface $userManager,
    AsyncMethodDispatcherInterface $dispatcher,
): JsonResponse {
    $user = $userManager->find($id) ?? throw $this->createNotFoundException();

    $status = $request->getPayload()->getBoolean('status')
        ? AccountStatus::ACTIVE
        : AccountStatus::INACTIVE;

    $dispatcher->dispatch(ToggleUserAccountHandler::class, 'handle', [$user->getId(), $status]);

    return $this->json(['type' => 'success', 'message' => 'Status update queued.']);
}
```

> Copy the safety rules of the trait when you write your own version: require a verified
> email before activating or resetting a password, and refuse to deactivate or reset **your
> own** account.

The email-change approval step in an EasyAdmin back-office works the same way: load the
`EmailChangeRequest`, reload the logged-in user with `UserManagerInterface::find()` (the
reason is explained [above](#resolvesmanagedcurrentusertrait)), then call `submitValidation()`.

## Example-app admin classes
- [The Sonata front end of the example app](#the-sonata-front-end-of-the-example-app)

These files live in the **example application**, not in the bundle. Copy and adapt them.

| File | Purpose |
|---|---|
| `App\Admin\WlindablaAdmin` | Abstract base for every admin. Constructor takes optional translation ids for the list/show/create/edit titles (`getLabelList()`, `getLabelShow($object)`, …). Sets the default sort (`id` DESC) and page size (10). `isDynamicFormRequest()` is true when the request has `?dynamic_form=1`. |
| `App\Controller\Admin\WlindablaAdminCRUDController` | Base CRUD controller for the SPA front end: validation errors and success replies are **JSON** for AJAX requests (`422` with a `violations` list built by ShareServiceBundle's `ProcessingErrorFormHandler`, or `{ "result": "ok", "objectId", "message" }`). `preList()` turns off the select/batch columns for AJAX list calls. `batchActionDelete()` answers JSON (`result: ok` or `error`) for AJAX requests. |
| `App\Controller\Admin\AdminUserCRUDController` | Final user controller: `use MicroUserCRUDActionTrait;` plus `preCreate()` calling `validateUser()`. |
| `App\Admin\SonataUserAdmin` | User admin: registers the three account routes above, list/template actions, the role field, and the password voter (`PasswordUserVoter::EDIT_PASSWORD`). |
| `App\Admin\LoggerLoginAdmin` | **Read-only** admin for the `LoggerLoginUser` login journal (create, edit and delete routes removed — an audit log must not be edited). |
| `App\Admin\UserSessionAdmin` | **Read-only** admin for `UserSession` (single-session policy). Same removed routes. |

Admins are registered with the PHP attribute
`#[AutoconfigureTag('sonata.admin', [... 'model_class' => …, 'manager_type' => 'orm', 'group' => …])]`
instead of YAML.

Make a log or session admin read-only like this:

```php
protected function configureRoutes(RouteCollectionInterface $collection): void
{
    $collection->remove('create');
    $collection->remove('edit');
    $collection->remove('delete');
}
```


## The Sonata front end of the example app

The account actions return JSON, so the admin pages need a little JavaScript and Twig to call
them. The example app does this with four pieces. Copy and adapt them (the role name
`ROLE_FOUNDER`, the CSS classes and the translation domains are specific to the example).

| Piece | File in the example app | Role |
|---|---|---|
| Layout meta tags | `templates/bundles/SonataAdminBundle/standard_layout.html.twig` | Gives the JavaScript its config and translations |
| List-action templates | `templates/bundles/SonataAdminBundle/CRUD/list_action_*.html.twig` | One button per action, with `data-*` attributes |
| Button helpers | `SonataUserAdmin::createToggleEnabledAction()` and two similar methods | Return label, icon, URL and confirm text for a user |
| JavaScript | `assets/utils.js` and `assets/sonata.js` | Confirmation dialog, request, flash message |

### 1. Layout: meta tags

Add these two blocks inside the `<head>` of your `standard_layout.html.twig`. The JavaScript
reads them at start-up (`config.param('APP_ENV')`, `appTranslation.preload('sonata-translations')`).

```twig
{% block meta_sonata_config %}
<meta name="sonata-config" content='{{ {
    SKIN: _skin,
    CONFIRM_EXIT: sonata_config.getOption('confirm_exit'),
    USE_SELECT2: _use_select2,
    USE_ICHECK: _use_icheck,
    USE_STICKYFORMS: sonata_config.getOption('use_stickyforms'),
    DEBUG: app.debug ?? false,
    APP_ENV: app.environment ?? 'prod',
    LOCALE: app.locale ?? 'fr',
    ENABLE_LOCALE: app.enabled_locales ?? []
}|json_encode()|e('html_attr') }}'>
{% endblock %}

{% block meta_sonata_translation %}
    {% set translations = {
        CONFIRM_EXIT: 'confirm_exit'|trans({}, 'SonataAdminBundle', app.locale),
        LABEL_BTN_CONFIRM: 'label_btn_confirm'|trans({}, 'SonataAdminBundle', app.locale),
        LABEL_BTN_CANCEL: 'label_btn_cancel'|trans({}, 'SonataAdminBundle', app.locale),
        ACTION_CANCELLED_SUCCESS: 'action_cancelled_success'|trans({}, 'SonataAdminBundle', app.locale),
        FORM_SUBMISSION_PROGRESS_MESSAGE: 'form_submission_progress_message'|trans({}, 'SonataAdminBundle', app.locale),
        FORM_SUBMISSION_PROGRESS_TITLE: 'form_submission_progress_title'|trans({}, 'SonataAdminBundle', app.locale),
        ACTION_PENDING_RESEND_EMAIL_VERIFCATION_MESSAGE: 'action_pending_resend_verification_message'|trans({}, 'SonataUserBundle', app.locale),
        ACTION_PENDING_TITLE: 'action.pending.title'|trans({}, 'SonataAdminBundle', app.locale),
        ACTION_PENDING_MESSAGE: 'action.pending.message'|trans({}, 'SonataAdminBundle', app.locale),
    } %}
    <meta name="sonata-translations" content='{{ translations|json_encode()|e('html_attr') }}'>
    <meta name="sonata-translations-hash" content="{{ TRANSLATION_HASH }}">
{% endblock %}
```

- `TRANSLATION_HASH` is a value you provide (for example a Twig global). When it changes, the
  browser cache of translations is cleared.
- The key `ACTION_PENDING_RESEND_EMAIL_VERIFCATION_MESSAGE` keeps its original spelling
  ("VERIFCATION"). It must be identical in the Twig layout and in the button's
  `data-loading-config-key-message`.


#### How the translations reach the JavaScript

`appTranslation` (from `@wlindabla/form_validator`,
[guide](https://github.com/Agbokoudjo/form_validator/blob/main/docs/Translation/AppTranslation.md))
does not call the server. It reads a **meta tag whose `content` is a JSON object**, keeps it in
memory and in `localStorage`, and returns the text on demand.

| You write | What happens |
|---|---|
| `appTranslation.preload('sonata-translations')` | Reads `<meta name="sonata-translations">` once and fills the cache. Call it before the first dialog. |
| `await SonataTranslator.trans('LABEL_BTN_CONFIRM', 'sonata-translations')` | Returns the text for that key. The second argument is the **meta tag name**, not a Symfony domain. |
| `appTranslation.clearCache()` | Empties the cache. The example calls it when `sonata-translations-hash` changes, so a new deployment or a new locale is picked up. |

Things to know:

- The Symfony domain (`SonataAdminBundle`, `SonataUserBundle`) exists **only in Twig**, where each
  key is translated server-side. The JavaScript only sees the final text.
- The language comes from `<html lang="…">`. Keep it in sync with `app.locale`.
- A key missing from the meta tag makes `trans()` throw `TranslationKeyNotFoundError` (unless a
  `fallbackTranslations` value was configured). So every key used by a button
  (`data-loading-config-key-title`, `data-loading-config-key-message`) must be listed in the
  `translations` Twig map above.
- `TRANSLATION_HASH` is your own cache-busting value (for example the application version). It is
  not part of the library; it only tells your code when to call `clearCache()`.
  
### 2. Button data for each action (in the admin class)

Three `final` helpers in `SonataUserAdmin` return what the templates need. They return `null`
when the button must be hidden: no password reset for a disabled account, no "resend" for an
already verified email.

```php
final public function createToggleEnabledAction(SonataUser $user): array
{
    $isEnabled = $user->isEnabled();

    return [
        'label'   => $isEnabled ? 'label_inactive' : 'label_active',
        'icon'    => $isEnabled ? 'fas fa-lock' : 'fas fa-lock-open',
        'title'   => $isEnabled ? 'link_title_inactive' : 'link_title_active',
        'url'     => $this->generateUrl('toggle_enabled_user_account', ['id' => $user->getId()]),
        'target'  => '_self',
        'confirm' => $isEnabled ? 'confirm_action_inactive' : 'confirm_action_active',
    ];
}

final public function createResendEmailVerificationAction(SonataUser $user): ?array
{
    if ($user->isEmailVerified()) {
        return null;
    }

    return [
        'label'   => 'label_resend_verification',
        'icon'    => 'fas fa-envelope-open-text',
        'title'   => 'link_title_resend_verification',
        'url'     => $this->generateUrl('resend_email_verify', ['id' => $user->getId()]),
        'target'  => '_self',
        'confirm' => 'confirm_resend_verification',
    ];
}

final public function createActionGeneratePassword(SonataUser $user): ?array
{
    if (!$user->isEnabled()) {
        return null;
    }

    return [
        'label'       => 'label_regenerate_password',
        'icon'        => 'fas fa-key',
        'title'       => 'link_title_regenerate_password',
        'url'         => $this->generateUrl('regenerate_tempory_password_user', ['id' => $user->getId()]),
        'target'      => '_self',
        'confirm'     => 'confirm_regenerate_password',
        'extra_class' => 'js-regenerate-password-btn',
    ];
}
```

The label, title and confirm values are translation keys (domain `SonataUserBundle` or
`SonataAdminBundle`, depending on the template). Define them in your own translation files.

### 3. Register the buttons in the list

In `configureListFields()`, add the three templates to the actions column. Put the Twig files in
`templates/bundles/SonataAdminBundle/CRUD/`.

```php
->add(ListMapper::NAME_ACTIONS, null, [
    'label'   => 'Actions',
    'actions' => [
        'show'   => [],
        'edit'   => [],
        'delete' => [],
        'toggle_enabled_user_account' => [
            'template' => 'bundles/SonataAdminBundle/CRUD/list_action_toggle_enabled_user_account.html.twig',
        ],
        'resend_email_verify' => [
            'template' => 'bundles/SonataAdminBundle/CRUD/list_action_resend_email_verify.html.twig',
        ],
        'regenerate_tempory_password_user' => [
            'template' => 'bundles/SonataAdminBundle/CRUD/list_action_regenerate_tempory_password_user.html.twig',
        ],
    ],
])
```

### 4. The list-action templates

Each button is a normal link with `data-*` attributes. The JavaScript reads them; the link
itself is never followed.

| Attribute | Meaning |
|---|---|
| `data-event-name` | Name of the DOM event fired after the user confirms |
| `data-http-method-request-action` | HTTP method to use (`PATCH` or `GET`, as the route requires) |
| `data-action-confirm` | Text of the confirmation dialog |
| `data-additional` | JSON sent as the request payload (the toggle button sends `{"status":"true"}` or `{"status":"false"}`) |
| `data-loading-config-key-title` / `-message` | Translation keys (from the `sonata-translations` meta) for the "please wait" dialog. Default: `FORM_SUBMISSION_PROGRESS_TITLE` / `_MESSAGE` |
| `data-csrf` | Optional. Sent as the `X-CSRF-TOKEN` header when present |

**Toggle account** — `list_action_toggle_enabled_user_account.html.twig`:

```twig
{% if admin.isGranted('ROLE_FOUNDER', object) %}
    {% set action = admin.createToggleEnabledAction(object) %}
    {% set status_account = { status: object.enabled ? 'false' : 'true' } %}
    {% set badge_status = object.enabled ? 'badge-inactive text-bg-danger' : 'badge-active text-bg-success' %}
    <a href="{{ action.url }}"
       class="btn btn-sm btn-toggle-account badge {{ badge_status }}"
       title="{{ action.title|trans([], 'SonataAdminBundle', app.locale) }}"
       data-bs-toggle="tooltip" data-bs-placement="top"
       data-additional="{{ status_account|json_encode() }}"
       data-event-name="account:toggle:confirmed"
       data-http-method-request-action="PATCH"
       data-loading-config-key-title="ACTION_PENDING_TITLE"
       data-loading-config-key-message="ACTION_PENDING_MESSAGE"
       data-action-confirm="{{ action.confirm|trans({'%username%': object.username}, 'SonataAdminBundle', app.locale) }}">
        <i class="{{ action.icon }}" aria-hidden="true"></i>
        <span class="d-none d-sm-inline ms-1">{{ action.label|trans([], 'SonataAdminBundle', app.locale) }}</span>
    </a>
{% endif %}
```

**Resend verification email** — `list_action_resend_email_verify.html.twig`:

```twig
{% if admin.isGranted('ROLE_FOUNDER', object) %}
    {% set action = admin.createResendEmailVerificationAction(object) %}
    {% if action is not null %}
        <a href="{{ action.url }}"
           class="btn btn-sm btn-info btn-resend-email-verification text-white fw-bold"
           title="{{ action.title|trans([], 'SonataUserBundle', app.locale) }}"
           data-bs-toggle="tooltip" data-bs-placement="top"
           data-event-name="resend:email:verificatio:password:confirmed"
           data-http-method-request-action="GET"
           data-loading-config-key-message="ACTION_PENDING_RESEND_EMAIL_VERIFCATION_MESSAGE"
           data-action-confirm="{{ action.confirm|trans({'%username%': object.username}, 'SonataUserBundle', app.locale) }}">
            <i class="{{ action.icon }}" aria-hidden="true"></i>
            <span class="d-none d-sm-inline ms-1">{{ action.label|trans([], 'SonataUserBundle', app.locale) }}</span>
        </a>
    {% endif %}
{% endif %}
```

**Regenerate temporary password** — `list_action_regenerate_tempory_password_user.html.twig`:

```twig
{% if admin.isGranted('ROLE_FOUNDER', object) %}
    {% set action = admin.createActionGeneratePassword(object) %}
    {% if action is not null %}
        <a href="{{ action.url }}"
           class="btn btn-sm btn-secondary fw-bold {{ action.extra_class }}"
           title="{{ action.title|trans({}, 'SonataUserBundle', app.locale) }}"
           data-bs-toggle="tooltip" data-bs-placement="top"
           data-user-id="{{ object.id }}"
           data-http-method-request-action="PATCH"
           data-event-name="create:action:generate:password:confirmed"
           data-action-confirm="{{ action.confirm|trans({'%username%': object.username}, 'SonataUserBundle', app.locale) }}"
           target="{{ action.target }}">
            <i class="{{ action.icon }}"></i>
            {{ action.label|trans({}, 'SonataUserBundle', app.locale) }}
        </a>
    {% endif %}
{% endif %}
```

`ROLE_FOUNDER` is the role the example app allows to use these buttons. Use your own role, and
also check access in the controller (the trait does not do it, see
[Authorization is your job](#authorization-is-your-job)).

### 5. JavaScript

The example uses `@wlindabla/form_validator` (`CRUDActionConfirmationHandle`,
`processCRUDAction`, `appTranslation`, `Logger`) and jQuery. Two functions, both in `utils.js`,
do the work:

1. `crudAccountHandle()` listens for clicks on `a.btn-toggle-account`,
   `a.btn-resend-email-verification`, `a.js-regenerate-password-btn` and
   `a.js-toggle-visible-btn`. It cancels the click, opens the confirmation dialog
   (SweetAlert2) with the text of `data-action-confirm`, and, once confirmed, fires the DOM event
   named in `data-event-name`.
2. `crudUserAccountListener()` listens for those events. It sends the request with the headers
   `X-Requested-With: XMLHttpRequest` and `Accept: application/json` (plus `X-CSRF-TOKEN` if
   `data-csrf` is set), shows the "please wait" dialog, and displays the `title` / `message` of
   the JSON reply.

Start them once, after the page is ready, in your admin entry file (`sonata.js` in the example):

```js
document.addEventListener('DOMContentLoaded', async () => {
    const APP_ENV = config.param('APP_ENV', 'sonata-config');
    const DEBUG   = config.param('DEBUG', 'sonata-config');
    Logger.config(APP_ENV, DEBUG);

    // Translations come from the <meta name="sonata-translations"> block above.
    const currentHash = jQuery('meta[name="sonata-translations-hash"]').attr('content');
    if (currentHash && localStorage.getItem('sonata_translations_hash') !== currentHash) {
        await appTranslation.clearCache();
        localStorage.setItem('sonata_translations_hash', currentHash);
    }
    await appTranslation.preload('sonata-translations');

    window.fetchErrorTranslator = fetchErrorTranslator;
    window.SonataTranslator = appTranslation;

    crudAccountHandle();
    crudUserAccountListener();
});
```

`config` is a small helper in `utils.js` that reads the `sonata-config` meta tag (JSON) once
and returns a value by key.

The three events used by the account buttons must exist in both places, the Twig
`data-event-name` and the `crudUserAccountListener()` event list:
`account:toggle:confirmed`, `resend:email:verificatio:password:confirmed`,
`create:action:generate:password:confirmed`. Their names keep the original spelling
("verificatio"): do not "fix" them in one file only.

#### How the two library functions work

Both functions come from `@wlindabla/form_validator`
([full guide](https://github.com/Agbokoudjo/form_validator/blob/main/docs/User/action_and_listener.md)).
They need jQuery and SweetAlert2.

| Function | Phase | What it does |
|---|---|---|
| `CRUDActionConfirmationHandle({ element, eventName, confirmDialogConfig, cancelDialogConfig })` | 1. Confirmation | Reads the button's attributes, opens the confirmation dialog, and dispatches the event named `eventName` on `document` if the user confirms. Returns `true` (confirmed) or `false` (cancelled). Default event name: `crud:action:confirmed`. |
| `processCRUDAction({ eventDetail, httpMethod, retryCount, optionsheaders, loadingConfig, translator, … })` | 2. Request | Shows a loading dialog, sends the request (retries on network errors, default 2), then shows a success or error dialog using the `title` and `message` of the JSON reply. Throws after showing the error dialog. |

The event payload (`event.detail`) contains `data`, `urlActionRequest`, `sourceElement`,
`timestamp` and `httpMethodRequestAction`. It is built from the button's attributes, so the only
required ones are: `data-action-confirm`, a title (`title`, `data-bs-original-title` or
`data-original-title`) and a URL (`href`, `data-href` or `data-url`).

The server reply must be JSON with `title` and `message`. This is exactly what
`MicroUserCRUDActionTrait` returns. React, Angular and vanilla-JS examples are in the guide above.

`data-loading-config-key-title` / `-message` are **not** library attributes: the example app's
`actionTranslator()` reads them to choose the text of the loading dialog.

### 6. Single-page mode (optional)

The example also boots `SpaKernel` from `@wlindabla/sonata_spa`. In that mode, list requests are
sent with AJAX and `WlindablaAdminCRUDController` answers JSON (see
[Example-app admin classes](#example-app-admin-classes)). A `401` reply that contains
`twoFactorRequired` is turned into a redirect to the 2FA page:

```js
eventDispatcherBrowser.addListener(SpaEvents.FETCH_FAILED, (event) => {
    const { fetchResponse } = event;
    if (fetchResponse.statusCode === 401 && fetchResponse.data?.twoFactorRequired) {
        event.stopPropagation?.();
        window.location.href = fetchResponse.data.redirect ?? '/admin/2fa/show';
    }
}, 10);
```

> The single-page mode is not required. Without it, the buttons above still work: they are plain
> AJAX calls on a normal Sonata page.


## Things to know

- **Authorization is never done by the trait** (see above).
- **Messages of the vote actions.** `approveAction` / `rejectAction` use the translation keys
  `email_change_vote.*` (domain `WlindablaUserBundle`, English and French provided).
  Override the keys in your own `translations/WlindablaUserBundle.<locale>.yaml` to change the
  wording or add a language. The only text not translated is the reason of a `DomainException`
  (for example "already decided"), which is an English domain message shown as is.
- **The actions return JSON only.** Your front end renders the flash message.
- **Async work.** Toggle and password regeneration are *queued*: the JSON reply says the
  request was accepted, not that the change is already stored. Your Messenger worker must be
  running (see [related-bundles.md](related-bundles.md)).



