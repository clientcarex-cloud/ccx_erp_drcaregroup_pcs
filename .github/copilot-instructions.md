# Project Guidelines

## Architecture
- This repository is a Perfex CRM/CodeIgniter 3 codebase customized for healthcare workflows.
- Keep framework internals in `system/` unchanged unless the task explicitly requires framework-level fixes.
- Main application code lives in `application/`.
- Feature plugins live in `modules/` and usually have their own `{module}.php` bootstrap and `install.php` lifecycle logic.
- Frontend source files live in `resources/`; compiled artifacts are written to `assets/builds/`.

Key examples:
- Module bootstrap/hooks: `modules/patient/patient.php`
- Admin controller pattern: `application/controllers/admin/Invoices.php`
- Build pipeline: `webpack.mix.js`
- Tailwind conventions: `tailwind.config.js`

## Build And Verification
- PHP dependencies (main app):
  - `cd application && composer install`
- Some modules are independently managed with Composer (only run when editing those modules):
  - `cd modules/flexibackup && composer install`
  - `cd modules/perfshield && composer install`
- Frontend bundling is configured via Laravel Mix in `webpack.mix.js` and expects a Node environment with matching dependencies available.
- There is no repository-level automated test suite configuration in this workspace snapshot; validate changes with focused manual checks and targeted syntax checks.

Useful checks:
- PHP syntax check for changed files: `php -l path/to/file.php`

## Conventions
- Controllers typically extend `AdminController` (admin area) or `ClientsController` (client area).
- Models use `*_model` naming and extend `App_Model`.
- Always guard access with permission helpers such as `staff_cant(...)` / `staff_can(...)` for admin features.
- Use `db_prefix()` for table names; do not hardcode `tbl` in queries.
- Use `_l('key')` for user-facing translatable strings.
- Keep module lifecycle logic in module bootstrap files (`register_activation_hook`, `register_deactivation_hook`) plus `install.php`.

## Frontend Conventions
- Tailwind uses a required `tw-` prefix; do not add unprefixed utility classes.
- When changing UI behavior, prefer editing source files in `resources/` and rebuild outputs rather than directly patching generated bundles in `assets/builds/`.
- Ensure new template/view paths are covered by Tailwind `content` globs in `tailwind.config.js` when introducing new frontend locations.

## Safety Notes
- Avoid editing third-party vendored code under `application/vendor/` or module `vendor/` directories unless explicitly requested.
- Prefer small, isolated changes in `application/` and `modules/`, and preserve existing hook/module patterns.