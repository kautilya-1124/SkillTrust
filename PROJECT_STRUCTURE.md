# SkillTrust Project Structure (Phase 1)

This project is being migrated to a cleaner structure without breaking existing routes.

## Canonical Core Paths

- `config/db.php`  
  Primary database connection file.
- `includes/functions.php`  
  Shared helpers (`e()`, CSRF helpers, flash toast helpers).
- `includes/auth.php`  
  Shared auth utilities (`require_user_login()`, `require_admin_login()`).

## Backward Compatibility

- `includes/config.php` is still available and now forwards to `config/db.php`.
- Existing route files under `student/`, `admin/`, `actions/`, `auth/` continue to work.

## Current App Areas

- `admin/` — Admin pages (`dashboard`, tests, users, recruiters, profile, auth)
- `student/` — Student-facing dashboard/tests/profile/results
- `actions/` — Shared action endpoints (upload/update/delete handlers)
- `auth/` — User login/register pages
- `components/` — Shared UI fragments (sidebar/navbar/footer)
- `uploads/` — Uploaded files (`csv`, `resumes`)
- `sql/` — SQL migration/utility scripts

## Next Phase (Optional)

1. Move admin and student shared layout into reusable include partials.
2. Replace query-string toasts with session flash toasts everywhere.
3. Normalize route naming (`kebab-case` or `snake_case`) consistently.
4. Add dedicated `admin/actions/` and `student/actions/` folders.
5. Add a root `README.md` with setup/run steps.

## Phase 2 Canonical Actions

- `student/actions/` is now the canonical home for student action handlers:
  - `submit_test.php`
  - `update_profile.php`
  - `update_password.php`
  - `delete_account.php`
  - `upload_resume.php`
  - `delete_resume.php`
  - `download_resume.php`
- `auth/actions/` is now canonical for auth form handlers:
  - `login_action.php`
  - `register_action.php`
- `actions/` files are preserved as backward-compatible stubs so old URLs keep working.

## Shared Layout Includes

- `components/admin/sidebar.php`
- `components/admin/topbar.php`

These are reusable admin layout includes for progressive migration of page templates.
