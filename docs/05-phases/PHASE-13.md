# CoffeePOS Phase 13 — WordPress.org Release Readiness

## 1. Objective

Phase 13 converts the completed CoffeePOS application into a reproducible,
internationalized, GPL-compatible WordPress.org release. It closes directory
submission blockers without changing the server-authoritative POS business
rules delivered by Phases 01–12.

The exit condition is a reviewable release artifact whose metadata, license,
translations, third-party disclosures, bundled files, automated checks, and
clean-install behavior agree with the source tree.

## 2. Prerequisites

- Phase 12 staff access, settings, notes, navigation, and receipt completion
- all earlier cart, checkout, Customer Display, KDS, Queue, Membership, Shift,
  History, and Reports contracts
- WordPress Plugin Directory detailed guidelines and readme format
- WordPress gettext and JavaScript internationalization APIs

## 3. In Scope

- GPL-2.0-or-later licensing in plugin metadata, Composer, and distribution
- one synchronized release version and stable tag
- a current WordPress.org `readme.txt`
- English source strings and the `coffeepos` text domain
- PHP and JavaScript gettext coverage for user-visible fixed strings
- an extractable `languages/coffeepos.pot` catalog
- a bundled Vietnamese `coffeepos-vi.po`/`.mo` catalog and per-script JSON
  catalogs so locale `vi` works before a WordPress.org language pack exists
- backward-compatible Quick Note defaults and existing saved labels
- explicit VietQR third-party service and transmitted-data disclosure
- a deterministic release ZIP containing runtime files and Composer autoload
- exclusion of tests, internal specifications, VCS, and developer-only files
- static release checks and affected Phase 01–12 regression tests
- clean install, upgrade, deactivation, uninstall, and real-browser acceptance

## 4. Out of Scope

- Phase 14 loyalty or membership benefits
- Phase 15 hardware drivers or automated payment verification
- changing WooCommerce ownership of products, customers, coupons, or orders
- translating merchant-entered product, table, store, note, or customer data
- silently rewriting saved Quick Note labels
- claiming compatibility with an untested WordPress, WooCommerce, PHP, HPOS,
  multisite, browser, or device version
- submitting the plugin or publishing a release without the owner's approval

## 5. Release Identity and License

The first directory-ready release is `1.0.0`. These values MUST agree:

```text
coffeepos.php Version       1.0.0
COFFEEPOS_VERSION           1.0.0
readme.txt Stable tag       1.0.0
Composer license            GPL-2.0-or-later
plugin/readme license       GPLv2 or later
```

All PHP, JavaScript, CSS, templates, documents, generated translation files,
images, and bundled dependencies in the distribution MUST be GPL-compatible.
The release contains a license notice and no `Proprietary` declaration.

## 6. Internationalization Contract

`coffeepos` is the only plugin text domain and matches the intended directory
slug. Fixed source strings are written in English and extracted through the
WordPress gettext toolchain.

PHP output uses the appropriate translation-and-escaping function. Dynamic
values use placeholders and translator comments where context is not obvious.
JavaScript user-visible strings use `wp.i18n` and registered CoffeePOS script
handles load translations through `wp_set_script_translations()`.

The distribution bundles a complete Vietnamese translation for locale `vi`.
PHP loads `languages/coffeepos-vi.mo`; each translated script handle has a
matching WordPress Jed JSON catalog. Source strings, placeholders, HTML tags,
URLs, and stable identifiers must remain intact in every translation.

The following are data and are not translated by gettext:

- WooCommerce product, variation, category, coupon, customer, and order data
- administrator-entered store identity, tables, receipt footer, and Custom CSS
- administrator-entered Quick Note labels
- cashier-entered item/order notes

The built-in Quick Note IDs remain stable. New/default definitions use English
labels (`Less sugar`, `Extra sugar`, `Less milk`, `Less ice`). Existing saved
Vietnamese or customized labels remain unchanged. A translation must never
change an ID or historical order metadata.

## 7. Public REST Error Messages

Stable error codes remain the machine contract. User-visible REST messages must
be translatable and safe. Internal exception details may be logged, but an
unexpected exception never exposes a stack trace, path, SQL, credential, nonce,
bank secret, or customer-private value.

Where several internal failures share one error code, the public response may
use a localized code-owned message while structured safe context remains
available for documented conflict recovery.

## 8. Third-Party Service Disclosure

CoffeePOS uses `https://vietqr.app/img` only when Bank Transfer is enabled and a
cashier prepares the VietQR payment preview. The Customer Display browser loads
the generated image URL. The URL may contain the configured bank identifier,
account number, account holder, store name, authoritative amount, and generated
payment reference.

The readme identifies VietQR as an external service, states when and why data is
sent, links to the service, and states that the cashier—not the image service—
confirms receipt before checkout. No undisclosed analytics, tracking, remote
code, or unrelated external request is permitted.

## 9. Distribution Contract

The release artifact has one top-level `coffeepos/` directory and includes only
runtime/plugin-directory material, including generated `vendor/autoload.php`.
It excludes at least:

```text
.git and editor metadata
AGENTS.md
docs/
tests/
featured.txt
composer.lock and development tooling
temporary, cache, log, coverage, and local environment files
```

The build must fail when required metadata disagree, the autoloader is absent,
the license is proprietary, untranslated built-in Vietnamese defaults remain,
or required runtime files are missing. A generated ZIP must not overwrite the
working tree.

## 10. Required Verification

### Automated and static

- PHP syntax for every plugin PHP file
- JavaScript syntax for every plugin JavaScript file
- Composer metadata validation and production autoload generation
- Phase 01–12 scenario suites that exist in the repository
- release metadata/license/stable-tag consistency
- gettext domain and JavaScript translation registration checks
- POT generation or catalog validation
- Vietnamese PO/MO completeness, placeholder, POS glossary, and JavaScript JSON
  catalog validation
- release ZIP content and autoload smoke check
- no secrets, local absolute paths, debug artifacts, or proprietary metadata
- WordPress Plugin Check and WordPress Coding Standards where available
- PHP compatibility analysis against the declared PHP 7.4 minimum

### Runtime and browser

- activate on a clean supported WordPress + WooCommerce installation
- upgrade from the last pre-1.0 development version without losing settings,
  carts, roles/capabilities, shifts, or WooCommerce order metadata
- English and Vietnamese locale checks for every staff/customer surface
- Cashier, checkout, Customer Display, KDS, Queue, Shifts, History, Reports,
  Settings, login/logout, receipt and uninstall regression
- Bank Transfer disclosure behavior and VietQR failure recovery

Do not mark a compatibility target or browser acceptance as passed unless it
was actually tested in that environment.

## 11. Acceptance Criteria

- [ ] All release metadata and the stable tag agree on `1.0.0`.
- [ ] CoffeePOS and all distributed material are GPL-2.0-or-later compatible.
- [ ] `readme.txt` describes the implemented plugin rather than Phase 00.
- [ ] Fixed PHP and JavaScript UI strings are translatable with `coffeepos`.
- [ ] No saved merchant-entered label or historical Quick Note ID is rewritten.
- [ ] A POT catalog can be generated from the release source.
- [ ] Locale `vi` has complete bundled PHP and JavaScript translation catalogs.
- [ ] VietQR use and transmitted fields are clearly disclosed.
- [ ] The release build is deterministic and excludes internal/development files.
- [ ] The ZIP activates with its bundled production autoloader.
- [ ] Static checks and all available affected regression suites pass.
- [ ] Clean-install, upgrade, English/Vietnamese, and critical browser workflows
      have recorded evidence or are explicitly reported as still unverified.

## 12. Definition of Done

Phase 13 is complete only when the documentation, source tree, generated release
artifact, automated verification, and recorded runtime/browser evidence agree.
Passing repository tests alone does not authorize a WordPress.org submission.
Submission remains an explicit owner action after the final manual review.

Completion authorizes planning Phase 14. It does not implement loyalty benefits,
hardware integration, automated bank confirmation, or publish the plugin.
