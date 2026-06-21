# Repository Rename Preparation

## Executive Summary

This document prepares the project repository for a manual GitHub rename from:

```text
rommelfs/taka-tour-wp-plugin
```

to:

```text
rommelfs/taka-platform
```

The GitHub repository itself is not renamed by this change. The only repository-specific project reference found in tracked files was updated so developer tooling will target the future repository name after the manual GitHub Settings change.

## Files Changed

| File | Change |
| --- | --- |
| `scripts/merge_codex_pr.sh` | Updated the GitHub repository slug used by `gh pr view` and PR links from `rommelfs/taka-tour-wp-plugin` to `rommelfs/taka-platform`. |
| `docs/repository-rename.md` | Added this preparation report. |

## Search Scope

The repository was searched for:

- `github.com/rommelfs/taka-tour-wp-plugin`
- `git@github.com:rommelfs/taka-tour-wp-plugin.git`
- `https://github.com/rommelfs/taka-tour-wp-plugin`
- `taka-tour-wp-plugin`
- badge and `shields.io` references
- GitHub Actions, CI and workflow references
- clone examples and installation instructions
- repository links in documentation, scripts and metadata files

No `.github` workflow directory, Composer metadata, npm metadata, Makefile, Docker configuration, issue template or pull request template files were found in the current repository.

## Remaining References

No tracked-file occurrences of `taka-tour-wp-plugin` remain outside this report after this preparation.

The local git remote may still point to the old GitHub URL until the repository is renamed manually in GitHub Settings and the local remote is updated.

## References Intentionally Preserved

This task only prepares the GitHub repository rename. It does not rename plugin runtime or compatibility identifiers such as:

- the main plugin file `taka-tour-website-builder.php`
- legacy TAKA Tour compatibility classes and constants
- WordPress option names, post meta keys, shortcodes, nonces, admin slugs or asset handles
- CSS selectors used by existing themes or customizations

Those compatibility-sensitive identifiers are covered separately in `docs/branding-migration.md`.

## Manual Follow-Up Items

After renaming the repository in GitHub Settings:

1. Update local clones:

   ```bash
   git remote set-url origin git@github.com:rommelfs/taka-platform.git
   ```

2. Confirm GitHub redirects from the old repository URL still work.
3. Check any external deployment scripts, server cron jobs, CI systems or documentation outside this repository for the old repository slug.
4. If package distribution is added later, use `taka-platform` as the repository/package slug.

## Validation Performed

- Searched tracked project files for old repository URLs and the `taka-tour-wp-plugin` slug.
- Checked for badges, GitHub Actions, clone examples, CI references and package metadata.
- Verified no tracked-file `taka-tour-wp-plugin` references remain outside this report.
- Ran repository lint checks after the documentation/script-only change.
