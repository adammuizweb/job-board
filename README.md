# Job Board for Jyavani CMS

Job Board is a reusable, organization-neutral vacancy publishing plugin for
Jyavani CMS. It provides a permission-separated dashboard, localized company,
category and job records, public search and filters, Schema.org `JobPosting`
output, a Shortcode Preset source, and a direct list widget.

## Requirements

- Jyavani CMS 2.3.187 or newer
- PHP 8.1 or newer
- PDO MySQL, JSON, and mbstring

## Public Routes

The default collection is `/jobs/`. Change the path in Job Board settings. A
detail URL uses the localized job slug, such as `/jobs/sample-role/`. When an
optional localization integration resolves a locale, only published rows for
that exact locale are visible; there is no source-locale fallback.

Active themes may override templates with regular, non-symlink files at:

```text
views/themes/{active}/plugins/job-board/list.php
views/themes/{active}/plugins/job-board/single.php
views/themes/{active}/plugins/job-board/widget-list.php
```

## Applications

Each job has exactly one application target: an HTTPS URL or an email address.
This plugin links applicants to that target and does not collect applicant data,
files, forms, subscriptions, analytics, or payments.

## Presets And Widget

The Shortcode Preset source ID is `job_board_jobs`, owned by `job-board`. Its
bounded filters are company, category, employment type, workplace type,
featured, open, and a limit of at most 24. The direct widget is:

```text
[[widget:job_board_list limit="6" featured="1"]]
```

Both integrations use the same exact-locale public repository query as the
public route. Registration fails closed on a source or widget name collision.

## Translation

Version 0.1.0 stores and edits plugin-owned locale rows and deterministically
uses the current Core content locale. When a compatible Content Translation
host is active, Job Board registers its generic resource adapter for prefixed
routes, canonical alternates, language switching, and transaction-time locale
edit grants. Older, partial, or absent hosts remain optional and fail closed for
non-default locale editing.

The plugin bundles Indonesian and German UI catalogs while keeping English as
the source language.

## Uninstall

Keep-data uninstall leaves records and migration history intact. Complete
uninstall removes only `jyb_` tables and the plugin's explicit `jyb_*` settings.

## Tests

Run all standalone contracts:

```sh
for test in tests/*.php; do php "$test"; done
```

The MySQL test skips unless `JYB_TEST_DSN` is set. It refuses any database whose
name does not begin with `jyb_contract_` and requires it to be empty.
