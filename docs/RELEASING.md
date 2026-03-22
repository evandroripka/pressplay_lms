# Releasing Pressplay LMS

This file defines the release routine and versioning policy for Pressplay LMS.

## Current baseline

- Plugin version: `1.0.1`
- WordPress tested up to: `6.9.4`
- WooCommerce tested up to: `10.6.1`

Update these values only after the release candidate has been tested in a real WordPress environment.

## Versioning policy

Pressplay LMS follows a simple semantic versioning style:

- `1.0.1`: patch release
- `1.1.0`: minor release
- `2.0.0`: major release

Use each level like this:

- Patch: bug fixes, compatibility updates, documentation, performance, or small UI improvements with no breaking change.
- Minor: new features or meaningful improvements that stay backward compatible.
- Major: breaking changes, migration requirements, removed hooks, changed data expectations, or behavior that can impact existing sites.

## Files to update for every release

### 1. Main plugin file

Update `pressplay-lms.php`:

- `Version`
- `Requires at least` when the WordPress minimum changes
- `Requires PHP` when the PHP minimum changes
- `WC requires at least` when the WooCommerce minimum changes
- `WC tested up to` after compatibility testing
- `PRESS_LMS_VERSION`

### 2. WordPress.org readme

Update `readme.txt`:

- `Stable tag`
- `Tested up to`
- `Requires at least` when needed
- `Requires PHP` when needed
- changelog entry for the new version

### 3. Optional Git release metadata

If the project is tagged in Git:

- create a tag such as `v1.0.1`
- keep the tag aligned with `Version` and `Stable tag`

## Release checklist

1. Run PHP lint on the changed files.
2. Test activation and route resolution.
3. Test a WooCommerce checkout with at least one supported gateway.
4. Confirm enrollment activation after payment.
5. Confirm invalid orders still revoke access.
6. Update version metadata.
7. Update `readme.txt` changelog.
8. Commit and tag the release.

## Suggested release rhythm

- Use patch releases often.
- Use minor releases for grouped feature work.
- Reserve major releases for deliberate breaking changes only.

This keeps the plugin safer for production sites and makes the update history more credible in public listings.
