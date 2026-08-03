# Habit Tracker - Refactoring & UX/Security Upgrade

## Completed

### 1. Centralized bootstrap (`includes/bootstrap.php`)
- New single entry point that loads `config/db.php` + `includes/helpers.php`.
- Sends all security headers **once** (X-Frame-Options, X-Content-Type-Options, X-XSS-Protection, Referrer-Policy, and a basic CSP).
- Calls `initSecureSession()` idempotently.
- Every page/endpoint now requires `bootstrap.php` instead of manually requiring db + helpers.

### 2. Removed duplicated CSRF render helper
- `renderCsrfInput()` now echoes directly (was `renderCsrfInput()` + `csrfField()` + `renderCsrfInput()` duplication). Removed the old `csrfField()` function.

### 3. Added reusable helpers
- `redirectWithFlash($location, $message, $isSuccess)` — single consistent way to redirect with a flash message.
- `loadUserSession($pdo, $userId)` — loads avatar/theme/custom-color/dark-mode into session idempotently.

### 4. Refactored all action endpoints to use bootstrap + flash redirects
- `add-habit.php` — added empty-name and max-length validation, flash messages.
- `delete-habit.php`, `update-bio.php`, `upload-avatar.php`, `remove-avatar.php`, `add-friend.php`, `accept-friend.php`, `remove-friend.php`, `update-theme.php`, `toggle-dark-mode.php`, `logout.php`, `toggle.php` — all now use `bootstrap.php` + `redirectWithFlash()`.

### 5. Pages now load session settings consistently
- `index.php`, `profile.php`, `recap.php`, `friend.php` call `loadUserSession()` so theme/avatar/dark-mode are always in sync.
- `index.php` now shows flash messages (add/delete habit feedback).
- `friend.php` cleaned up unused `$myTheme`/extra avatar query.

### 6. Fixed pre-existing JS syntax error
- `color-picker.js` had an unclosed `applyLiveThemeFromHex()` function that broke the whole script. Added the missing closing brace.

### 7. Security hardening
- Added CSP header in bootstrap.
- All user input validated (habit name length, CSRF, friendship ownership, avatar re-encode).
- Uploads re-encoded as fresh JPEG (strips embedded data).

## In Progress

### 8. Spotify Wrapped-style auto-advance timer for the year recap
- [x] `public/recap.js` — add auto-advance timer (~4s) to next slide.
- [x] `public/recap.js` — restart timer on manual navigation (next/prev/keys/click).
- [x] `public/recap.js` — pause timer on hover, stop on final slide.
- [x] `public/recap.css` — add auto-advance progress indicator on the current segment.

## Verification
- All PHP files pass `php -l`.
- All JS files pass `node --check`.
