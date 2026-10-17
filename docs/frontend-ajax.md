# Frontend integration and AJAX requests

[← Back to the README](../README.md)

This page explains what your JavaScript must do when a request is blocked by **two-factor authentication**
or by the **single-session policy**.

> **These examples are generic.** They use the native `fetch` API (and plain `XMLHttpRequest` for the second
> version). They do not depend on any JavaScript library or framework, so you can adapt them to React, Vue,
> jQuery, Stimulus or any other stack. The code is plain JavaScript, not TypeScript.

## Contents

- [The problem in one minute](#the-problem-in-one-minute)
- [Important: how the server detects an AJAX request](#important-how-the-server-detects-an-ajax-request)
- [Mode 1 — redirect mode (default)](#mode-1--redirect-mode-default)
- [Mode 2 — JSON mode](#mode-2--json-mode)
- [Choosing a mode](#choosing-a-mode)
- [Forcing a full page load](#forcing-a-full-page-load)
- [Session revoked responses](#session-revoked-responses)
- [The 2FA code form with fetch](#the-2fa-code-form-with-fetch)
- [Other JSON responses you may meet](#other-json-responses-you-may-meet)

---

## The problem in one minute

A normal page navigation that is blocked simply gets a redirect and the browser follows it. An AJAX request is
different: it is made by your JavaScript, in the middle of a page. If the server answers "go to the 2FA page",
your code must decide what to do. The code-entry page must always be a **full page navigation**. It must never
be injected into a part of the page (a SPA view, a modal, a fragment).

Quick comparison of what the bundle does:

| Situation | Normal navigation | AJAX request, `support_ajax_json_response: false` (default) | AJAX request, `support_ajax_json_response: true` |
|---|---|---|---|
| 2FA code required | `302` → `/admin/2fa/show` | `302` → `/admin/2fa/show` (followed by `fetch`, so you get the HTML of the 2FA page with status `200`) | `401` + JSON `{success:false, twoFactorRequired:true, redirect:"/admin/2fa/show"}` |
| Session revoked | Redirect to conflict page or logout | JSON `401` `{error, message}` | JSON `401` `{error, message}` (same) |
| Access denied (authenticated) | 403 HTML page | JSON `403` `{title, message}` | JSON `403` `{title, message}` |
| Entity in use | Flash + redirect | JSON `409` `{title, message}` | JSON `409` `{title, message}` |

The setting only changes the **2FA** row.

---

## Important: how the server detects an AJAX request

The bundle calls Symfony's `$request->isXmlHttpRequest()`. It is true **only** when the request has the header

```
X-Requested-With: XMLHttpRequest
```

Plain `fetch()` does **not** send this header. jQuery and many HTTP libraries add it automatically; native
`fetch` and `XMLHttpRequest` do not. **If you forget it, the bundle treats your request as a normal
navigation**: you will get redirects even in JSON mode. Always add it:

```js
fetch('/api/orders', {
  headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
  credentials: 'same-origin',
});
```

---

## Mode 1 — redirect mode (default)

```yaml
wlindabla_user:
    two_factor:
        support_ajax_json_response: false
```

**What the server does.** For every blocked request, AJAX or not, it returns a standard `302` redirect to
`wlindabla_user_two_factor_show` (default path `/admin/2fa/show`). The bundle deliberately treats AJAX like
a normal request here ("it is up to the host frontend to detect `response.redirected` / `xhr.responseURL`
and to force `window.location.href` itself", says the source comment).

**What the browser does.** `fetch` follows the redirect automatically. Your `.then()` receives the final
response: the HTML of the 2FA page, status `200`. The tell-tale signs are:

- `response.redirected === true`
- `response.url` ends with the 2FA path.

**What your code must do.** Detect that and do a full page load.

```js
// ---- redirect mode, native fetch ----
const TWO_FACTOR_PATH = '/admin/2fa/show'; // fixed path of the bundle's 2FA page (see docs/routes.md)

async function apiFetch(url, options = {}) {
  const response = await fetch(url, {
    credentials: 'same-origin',
    ...options,
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      ...(options.headers || {}),
    },
  });

  // The server redirected us and the final page is the 2FA page: leave the SPA / fragment.
  if (response.redirected && new URL(response.url).pathname === TWO_FACTOR_PATH) {
    window.location.assign(response.url);          // full page navigation
    return new Promise(() => {});                   // stop the caller: the page is going away
  }

  return response;
}

// usage
apiFetch('/admin/orders/42/approve', { method: 'POST' })
  .then((response) => response.json())
  .then((data) => console.log(data));
```

The same with `XMLHttpRequest` (the final URL is in `xhr.responseURL`):

```js
// ---- redirect mode, XMLHttpRequest ----
function apiXhr(method, url, body, onSuccess) {
  const xhr = new XMLHttpRequest();
  xhr.open(method, url);
  xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
  xhr.onload = function () {
    // XHR also follows redirects automatically.
    if (xhr.responseURL && new URL(xhr.responseURL).pathname === '/admin/2fa/show') {
      window.location.assign(xhr.responseURL);      // full page navigation
      return;
    }
    onSuccess(xhr);
  };
  xhr.send(body || null);
}
```

> **Tip.** Redirects that go to the *login page* (session lost, anonymous user) look the same: you can test
> `response.redirected` against your login path too, and handle it the same way.

> **Do not use `redirect: 'manual'`** for this. The result is an "opaque redirect" response: you cannot read the
> target URL, so you cannot tell the 2FA page from anything else.

---

## Mode 2 — JSON mode

```yaml
wlindabla_user:
    two_factor:
        support_ajax_json_response: true
```

**What the server does.** For a blocked request that has the `X-Requested-With: XMLHttpRequest` header, it
returns:

```
HTTP/1.1 401 Unauthorized
Content-Type: application/json

{
  "success": false,
  "twoFactorRequired": true,
  "redirect": "/admin/2fa/show"
}
```

`redirect` is the URL generated from the route `wlindabla_user_two_factor_show` (an absolute **path**, such as
`/admin/2fa/show`, including your route `prefix` if you use one). Requests **without** the header still get the
normal `302` redirect.

**What your code must do.** Look at the status and the `twoFactorRequired` field, then load `redirect` as a full
page.

```js
// ---- JSON mode, native fetch ----
async function apiFetch(url, options = {}) {
  const response = await fetch(url, {
    credentials: 'same-origin',
    ...options,
    headers: {
      'X-Requested-With': 'XMLHttpRequest',   // REQUIRED, otherwise you get a 302 instead of JSON
      'Accept': 'application/json',
      ...(options.headers || {}),
    },
  });

  if (response.status === 401) {
    // Read the body without consuming the original response.
    const data = await response.clone().json().catch(() => null);

    if (data && data.twoFactorRequired === true) {
      window.location.assign(data.redirect);         // full page navigation to the 2FA page
      return new Promise(() => {});
    }
  }

  return response;
}

// usage
apiFetch('/admin/orders/42/approve', { method: 'POST' })
  .then((response) => response.json())
  .then((data) => console.log(data));
```

With `XMLHttpRequest`:

```js
// ---- JSON mode, XMLHttpRequest ----
function apiXhr(method, url, body, onSuccess) {
  const xhr = new XMLHttpRequest();
  xhr.open(method, url);
  xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
  xhr.setRequestHeader('Accept', 'application/json');
  xhr.onload = function () {
    if (xhr.status === 401) {
      try {
        const data = JSON.parse(xhr.responseText);
        if (data.twoFactorRequired === true) {
          window.location.assign(data.redirect);    // full page navigation
          return;
        }
      } catch (e) { /* not JSON: fall through */ }
    }
    onSuccess(xhr);
  };
  xhr.send(body || null);
}
```

---

## Choosing a mode

| | Redirect mode (`false`) | JSON mode (`true`) |
|---|---|---|
| Server behaviour | Same for every request | Different for AJAX and navigation |
| Frontend check | `response.redirected` + final URL | Status `401` + `twoFactorRequired` field |
| Needs the `X-Requested-With` header | No (but useful for other responses) | **Yes** |
| Extra request to the 2FA page | Yes (the redirect is followed, the 2FA page is fetched but you throw it away) | No |
| Easier to debug | Harder (a `200` that is really a redirect) | Easier (explicit `401` and a clear field) |
| Best for | Mostly server-rendered pages with a little AJAX | SPAs and API-style frontends |

Both modes are safe. In both modes the "target path" (the page to return to after validation) is saved only for
**non-AJAX** requests.

---

## Forcing a full page load

Use one of these. A full page load is required because the 2FA code page must run as a normal document with its
own CSRF tokens and scripts.

```js
window.location.assign(url);    // adds a history entry (user can press Back)
window.location.replace(url);   // no history entry
window.location.href = url;     // same as assign()
```

For SPA routers (React Router, Vue Router, …) **do not** call the router's `navigate()` / `push()` for this
URL. Those only change the view inside your app. Use `window.location.assign(url)` instead.

---

## Session revoked responses

When the single-session policy revokes a session (see [session control](session-control.md)), an AJAX request
gets, **in both modes**:

```
HTTP/1.1 401 Unauthorized
{ "error": "conflict", "message": "…translated text…" }
```

`error` is one of: `conflict`, `expired_inactivity`, `expired_max_duration`, `manual_logout`,
`security_email_changed`. The server does **not** invalidate the PHP session for an AJAX request, so your code
should load a full page; the next normal navigation applies the rule (conflict page, or logout + flash message).

```js
// Add this next to the 2FA check inside apiFetch().
const REVOKED = ['conflict', 'expired_inactivity', 'expired_max_duration',
                 'manual_logout', 'security_email_changed'];

if (response.status === 401) {
  const data = await response.clone().json().catch(() => null);

  if (data && data.twoFactorRequired === true) {
    window.location.assign(data.redirect);          // 2FA (JSON mode)
    return new Promise(() => {});
  }
  if (data && REVOKED.includes(data.error)) {
    alert(data.message);                            // optional: show the message
    window.location.reload();                       // a normal navigation: the server will redirect/log out
    return new Promise(() => {});
  }
}
```

---

## The 2FA code form with fetch

You can use the default `two_factor_show` template, or build your own form and call the endpoints yourself.

**Verify the code** — `POST /admin/2fa/verify`, form-encoded, CSRF token id `two_factor_verify`:

```js
async function verifyCode(code, csrfToken) {
  const body = new URLSearchParams({ code, _csrf_token: csrfToken });

  const response = await fetch('/admin/2fa/verify', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body,
  });
  const data = await response.json();

  if (response.ok && data.success) {
    window.location.assign(data.redirect);          // saved target page, or home
    return;
  }

  // 422 = wrong/expired code, 423 = code locked, 401 = session lost (data.redirect = login page)
  if (response.status === 401 && data.redirect) {
    window.location.assign(data.redirect);
    return;
  }
  showError(data.errorMessage || data.message);     // your own UI function
}
```

**Resend a code** — `POST /admin/2fa/resend`, CSRF token id `two_factor_resend` in the `X-CSRF-Token` header:

```js
async function resendCode(csrfToken) {
  const response = await fetch('/admin/2fa/resend', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken },
  });
  const data = await response.json().catch(() => ({}));
  // 200 {success:true,message} | 429 {success:false,errorMessage} | 401 {success:false,message,redirect}
  return { ok: response.ok, data };
}
```

How you obtain the two CSRF tokens (Twig `csrf_token('two_factor_verify')` and `csrf_token('two_factor_resend')`)
is up to your template.

---

## Other JSON responses you may meet

| Where | Status | Body |
|---|---|---|
| 2FA verify, wrong or expired code | `422` | `{"success": false, "errorMessage": "…"}` |
| 2FA verify, code locked | `423` | `{"success": false, "errorMessage": "…"}` |
| 2FA verify/resend, session expired | `401` | `{"success": false, "message": "…", "redirect": "<login url>"}` |
| 2FA verify, success | `200` | `{"success": true, "message": "…", "redirect": "<target url>"}` |
| 2FA resend, success | `200` | `{"success": true, "message": "…"}` |
| 2FA resend, throttled | `429` | `{"success": false, "errorMessage": "…"}` |
| Access denied | `403` | `{"title": "…", "message": "…"}` |
| Entity in use | `409` | `{"title": "…", "message": "…"}` |

Back to: [README](../README.md) · Next: [Console commands](console-commands.md)
