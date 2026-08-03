# Dark Mode Toggle Fix - Task List

## Root Cause
`renderThemeStyle()` in `includes/helpers.php` applies the `dark-mode` class only to `<html>` via `document.documentElement`, but the CSS element-specific dark overrides in `style.css` are keyed on `body.dark-mode`. On a fresh page load/refresh, `<body>` never gets the class, so some parts stay light.

## Steps
- [x] 1. Analyze repo and identify root cause
- [x] 2. Plan approved by user
- [x] 3. Fix `renderThemeStyle()` in `includes/helpers.php` to apply `dark-mode` to both `<html>` and `<body>`
- [x] 4. Verify the fix on all pages (index, profile, friend, recap)
