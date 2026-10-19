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
