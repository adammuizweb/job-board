# Job Board Repository Contract

Job Board is a generic Jyavani CMS plugin for publishing vacancies for any
organization. Tracked files must not contain downstream names, domains, people,
brands, credentials, proprietary data, or deployment-specific paths. The exact
author, repository, and canonical Jyavani Store URLs in `plugin.json` are
upstream release identity, not downstream identity.

## Product Boundary

- The plugin owns companies, localized company text, categories, localized
  category text, jobs, localized job text, job-category assignments, settings,
  public presentation, and its audit trail.
- A job accepts applications through exactly one validated HTTPS URL or one
  email address. The plugin never stores applicants, submissions, or uploads.
- Jobs use draft, published, closed, and archived workflows. Ordinary hard
  deletion is not a product operation; close, archive, and restore instead.
- Content Translation is optional. Public localized reads never fall back to a
  different locale.
- Core owns routing, authorization primitives, migrations, and plugin lifecycle.
  Do not patch Core when a published contract exists.

## Security

- Every dashboard route uses its exact dynamic permission.
- Mutations require POST, CSRF, bounded scalar input, transaction-time actor
  permission locking and reauthorization, deterministic row locks, optimistic
  versions, same-transaction audit insertion, rollback on error, and a 303
  redirect to a fixed dashboard route.
- Editing a published job, publishing a job, or changing a published localized
  representation requires the publish permission.
- Public queries require a published job, published text in the exact locale,
  a published company, and published company text in that exact locale.
- Escape text by default. Application URLs allow HTTPS only; application email
  targets are validated addresses. Theme overrides require realpath containment
  and reject symlinks.

## Verification

1. Lint every PHP file.
2. Run every standalone test in `tests/`.
3. Run the optional MySQL contract only against an explicitly configured,
   disposable database named `jyb_contract_*`.
4. Verify manifests, migration immutability, permissions, CSRF, visibility,
   no-locale-fallback behavior, provider/widget collisions, and uninstall scope.
5. Search tracked source for downstream identity before release.

Do not commit or push unless explicitly requested.
