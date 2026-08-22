# PHASE-00.md

# CoffeePOS Phase 00 — Project Foundation

## 1. Objective

Build the technical foundation of the CoffeePOS plugin without implementing business features.

At the end of this phase:

- the plugin can activate safely
- the project has the approved directory/namespace structure
- the core bootstrap process is established
- WooCommerce dependency handling is established
- POS routing/page foundation exists
- settings foundation exists
- capability foundation exists
- template loading foundation exists
- frontend asset loading foundation exists
- REST API foundation exists
- database migration foundation exists
- logging/error foundation exists
- the project is ready for Phase 01

This phase MUST NOT implement the Cashier, Cart, Checkout, Payment, Customer Display, KDS, Order Queue, Shift, Order History, or Reports features.

---

# 2. Prerequisites

Before starting this phase, Junie MUST read:

```text
JUNIE.md

docs/00-project/PROJECT.md
docs/00-project/REQUIREMENTS.md
docs/00-project/ROADMAP.md

docs/01-architecture/ARCHITECTURE.md
docs/01-architecture/DOMAIN-MODEL.md
docs/01-architecture/STATE-MACHINES.md
docs/01-architecture/DATA-FLOW.md
docs/01-architecture/SECURITY.md
docs/01-architecture/CODING-STANDARDS.md

docs/02-database/DATABASE.md
docs/02-database/WOOCOMMERCE-DATA.md

docs/03-ui/UI-ARCHITECTURE.md
docs/03-ui/COMPONENTS.md

docs/04-api/API-ARCHITECTURE.md
docs/04-api/POS-API.md
docs/04-api/ORDER-API.md
docs/04-api/SYNC-API.md

featured.txt
```

Junie MUST inspect the existing plugin directory before creating or replacing files.

If the plugin already contains code, Junie must preserve useful existing infrastructure where it is compatible with this architecture.

Do not perform a blind rewrite.

---

# 3. Scope

## In Scope

```text
Plugin bootstrap
Directory structure
Namespaces
Autoloading
Core initialization
WooCommerce dependency handling
Activation/deactivation
Uninstall foundation
Database migration foundation
Settings foundation
Capabilities foundation
POS page/routing foundation
Template loader
Asset loader
REST API foundation
Base frontend JS/CSS structure
Logging/error foundation
Environment guards
Development diagnostics
```

## Out of Scope

Do NOT implement:

```text
Cashier UI behavior
Product search behavior
Product selection
Variation selection
Modifier system
Cart operations
Coupons
Customer lookup
Membership
Dine-in/table workflow
Takeaway workflow
Checkout
Cash payment
VietQR/payment verification
WooCommerce order creation
Receipt printing
Customer Display synchronization
KDS
Order Queue
Order History
Quick Reorder
Refund
Shift Management
Reports
Analytics
Quick Stock Adjustment workflow
Suspended Cart workflow
```

A screen shell may exist only when required to verify the foundation.

---

# 4. Required Directory Structure

Create the project foundation around the following responsibility map:

```text
coffeepos/
│
├── coffeepos.php
├── uninstall.php
├── readme.txt
│
├── includes/
│   ├── Core/
│   ├── Domain/
│   ├── Application/
│   ├── Infrastructure/
│   ├── Integration/
│   ├── REST/
│   ├── Admin/
│   ├── POS/
│   └── Support/
│
├── templates/
│   ├── cashier/
│   ├── customer/
│   ├── kds/
│   ├── order-queue/
│   ├── order-history/
│   ├── shifts/
│   ├── reports/
│   └── components/
│
├── assets/
│   ├── js/
│   ├── css/
│   └── images/
│
├── docs/
│   ├── 00-project/
│   ├── 01-architecture/
│   ├── 02-database/
│   ├── 03-ui/
│   ├── 04-api/
│   └── 05-phases/
│
└── languages/
```

The structure is a responsibility guideline.

Do not create empty classes solely to fill every folder.

---

# 5. Plugin Entry Point

File:

```text
coffeepos.php
```

Responsibilities:

- plugin header
- define plugin constants
- prevent direct access where appropriate
- load Composer/autoloader if used
- load the project autoloader if required
- initialize the application at the appropriate WordPress hook
- register activation/deactivation hooks

The entry file MUST remain small.

It MUST NOT contain the entire application bootstrap.

---

# 6. Constants

Define stable plugin constants for:

```text
COFFEEPOS_VERSION
COFFEEPOS_FILE
COFFEEPOS_PATH
COFFEEPOS_URL
COFFEEPOS_BASENAME
```

Do not create duplicate constant definitions across multiple files.

---

# 7. Namespace

Use the project namespace:

```text
CoffeePOS
```

Sub-namespaces should match responsibilities.

Examples:

```text
CoffeePOS\Core
CoffeePOS\Domain
CoffeePOS\Application
CoffeePOS\Infrastructure
CoffeePOS\Integration
CoffeePOS\REST
CoffeePOS\Admin
CoffeePOS\POS
CoffeePOS\Support
```

The final namespace mapping must remain consistent throughout the project.

---

# 8. Autoloading

Use one consistent autoload strategy.

Preferred:

```text
Composer PSR-4
```

If Composer is not yet used by the existing plugin, a minimal internal autoloader may be introduced during this phase.

Do NOT create multiple competing autoloaders.

Namespace-to-directory mapping must be deterministic.

Example:

```text
CoffeePOS\Core\Bootstrap
→ includes/Core/Bootstrap.php
```

---

# 9. Core Bootstrap

Create a central bootstrap responsible for starting the plugin.

Conceptual flow:

```text
WordPress
   ↓
Plugin Entry
   ↓
Bootstrap
   ↓
Environment Checks
   ↓
Register Core Services
   ↓
Register Integrations
   ↓
Register REST
   ↓
Register Admin/POS infrastructure
```

The bootstrap must not execute business operations at plugin load time.

Use appropriate WordPress hooks.

---

# 10. Environment Checks

Before registering CoffeePOS functionality, check required dependencies.

At minimum:

```text
WordPress loaded
WooCommerce available
required PHP version
required WooCommerce version if the project defines one
```

If WooCommerce is unavailable:

- plugin should not fatal
- CoffeePOS functionality should not initialize
- an admin-visible dependency notice should be provided where appropriate

Do not run WooCommerce-dependent classes blindly.

---

# 11. Activation

Register an activation callback.

Activation should perform only infrastructure tasks.

Potential tasks:

```text
create/update plugin options
store database schema version
create CoffeePOS-owned tables when required
register capabilities
flush routing rules if necessary
```

Activation MUST NOT:

- create orders
- create customers
- create products
- create demo data automatically
- modify WooCommerce catalog data

---

# 12. Deactivation

Deactivation should:

- stop transient runtime behavior where applicable
- flush rewrite rules only if required by the routing implementation

Do NOT delete business data on deactivation.

Do NOT delete WooCommerce orders/customers/products.

---

# 13. Uninstall

File:

```text
uninstall.php
```

The uninstall foundation must distinguish:

```text
WooCommerce-owned data
CoffeePOS-owned data
CoffeePOS options
```

WooCommerce-owned data MUST NEVER be deleted by CoffeePOS uninstall.

Deletion of CoffeePOS-owned data should follow a deliberate uninstall policy.

Do not implement destructive deletion without an explicit documented setting/policy.

---

# 14. Database Migration Foundation

Create a single migration entry point.

Conceptual flow:

```text
Plugin Version
      ↓
Database Version
      ↓
Compare
      ↓
Run required migrations
      ↓
Store new database version
```

Use:

```text
coffeepos_db_version
```

or the exact stable option name chosen by implementation.

The migration system must support:

- initial schema creation
- future schema changes
- version comparison
- safe execution

Do not create application tables directly from random feature classes.

---

# 15. Database Tables in Phase 00

Only infrastructure required by the documented schema may be created.

The Phase 00 database foundation SHOULD prepare the migration mechanism for:

```text
coffeepos_shifts
coffeepos_suspended_carts
```

Actual feature-specific columns/behavior must follow `DATABASE.md`.

Do not create speculative tables.

---

# 16. Settings Foundation

Create a central settings registration mechanism.

Settings must be defined by ownership and purpose.

Examples that may be registered/foundationed:

```text
POS page
Customer Display page
```

Additional feature settings should be added in later phases when their functionality is implemented.

Do not implement all feature settings prematurely.

---

# 17. Settings Rules

All settings must have:

```text
key
type
default
sanitize callback
validation
capability requirement
```

Do not scatter `get_option()` calls throughout UI/business code without a documented settings abstraction where practical.

---

# 18. Capabilities Foundation

The POS access model must use WordPress capabilities.

At minimum, foundation code must support checking the capabilities defined by the project requirements.

Baseline requirements mention:

```text
manage_woocommerce
view_admin_dashboard
```

Do not invent a complete role/permission system during this phase.

Feature-specific permission granularity may be added later.

---

# 19. POS Routing Foundation

Create the routing foundation for the documented POS surfaces.

Baseline routes:

```text
/pos/cashier
/pos/customer
/pos/kds
/pos/order-queue
/pos/order-history
/pos/shifts
/pos/reports
```

Phase 00 does NOT implement the screen features.

The route foundation must make it possible for later phases to render the correct template/application surface.

---

# 20. Routing Rules

Routing must:

- use WordPress-compatible mechanisms
- avoid modifying WordPress core
- avoid hijacking unrelated pages
- verify the configured POS context
- allow capability checks
- work with the project's selected page/routing strategy

Do not hard-code page IDs.

POS page configuration should come from settings/configuration.

---

# 21. Template Loader

Create a central template loading mechanism.

Responsibilities:

```text
resolve template
load template
pass prepared data
allow reusable component templates
```

Templates must not:

- query the database directly
- perform REST requests
- mutate WooCommerce data
- execute business operations

---

# 22. Template Naming

Use a predictable structure:

```text
templates/cashier/
templates/customer/
templates/kds/
templates/order-queue/
templates/order-history/
templates/shifts/
templates/reports/
templates/components/
```

Component templates should be reusable.

---

# 23. Asset Loader

Create a centralized asset registration/loading system.

Responsibilities:

- register scripts
- register styles
- enqueue only where needed
- provide versioning
- provide dependencies

Do not load all POS assets on every WordPress page.

---

# 24. Frontend Foundation

Prepare a modular JavaScript structure.

Conceptual:

```text
assets/js/
├── core/
├── api/
├── state/
├── ui/
├── components/
├── screens/
├── sync/
└── utils/
```

Phase 00 may create only the minimum files needed to verify loading.

Do NOT implement cashier business behavior.

---

# 25. CSS Foundation

Prepare:

```text
assets/css/
```

with a project-wide baseline plus screen/component structure where appropriate.

Do not build final Cashier styling in this phase.

---

# 26. REST Foundation

Register the REST namespace:

```text
coffeepos/v1
```

Phase 00 may expose a health/bootstrap endpoint for verification.

Example:

```text
GET /wp-json/coffeepos/v1/health
```

Conceptual response:

```json
{
  "success": true,
  "data": {
    "plugin": "coffeepos",
    "version": "1.0.0",
    "woocommerce": true
  }
}
```

The health endpoint must not expose sensitive environment information.

Business endpoints belong to later phases.

---

# 27. REST Controller Rules

Every endpoint must define:

```text
route
methods
permission callback
input validation
callback/service
response serialization
```

Do not put business logic in the callback.

---

# 28. Security Foundation

Phase 00 must establish reusable mechanisms for:

```text
capability checks
nonce handling where applicable
input sanitization
output escaping
REST permission callbacks
secure settings
direct-access protection
```

Do not rely on JavaScript for authorization.

---

# 29. Logging Foundation

Create a project-level logging strategy.

Logging should support:

```text
debug
info
warning
error
```

Do not log secrets or sensitive payment/customer data unnecessarily.

Feature-level logs will be added later.

---

# 30. Error Foundation

Create a stable internal error approach compatible with API responses.

Errors should be representable as:

```text
code
message
details/context
```

Avoid returning raw exceptions to users.

---

# 31. Service Registration

If the project uses a service container/registry, establish it here.

Services should be registered by responsibility.

Do not build a complex dependency injection framework.

The minimum useful foundation is sufficient.

---

# 32. Configuration Separation

Separate:

```text
plugin constants
settings
runtime services
domain configuration
```

Do not mix all configuration into one global array without ownership.

---

# 33. Development Diagnostics

Provide safe development diagnostics where practical:

```text
plugin boot status
WooCommerce detected
database version
registered routes
```

Do not expose diagnostics publicly to unauthenticated users.

---

# 34. File Responsibilities

At the end of Phase 00, Junie should be able to explain the purpose of every newly created class.

Avoid classes with unclear responsibilities such as:

```text
Helper
Manager
Utility
Common
Misc
```

unless the name is justified by a specific project convention.

---

# 35. Bootstrap Flow

Required conceptual flow:

```text
coffeepos.php
    ↓
Bootstrap
    ↓
Environment Check
    ↓
Core Services
    ↓
Database/Migrations
    ↓
WooCommerce Integration
    ↓
Settings
    ↓
REST Registration
    ↓
POS Routing
    ↓
Admin/POS Infrastructure
```

Do not execute feature logic during plugin file inclusion.

---

# 36. Activation Flow

```text
Plugin Activation
    ↓
Environment validation
    ↓
Create/update CoffeePOS schema
    ↓
Register required capabilities/configuration
    ↓
Flush rules if needed
    ↓
Store installed version
```

---

# 37. Runtime Flow

```text
WordPress Request
    ↓
CoffeePOS Bootstrap
    ↓
Determine request context
    ↓
Load only required services/assets
    ↓
Handle route/API/admin request
    ↓
Render/return response
```

---

# 38. Definition of Done

Phase 00 is complete only when all of the following are true:

- plugin activates without fatal errors
- plugin deactivates without fatal errors
- plugin does not fatal when WooCommerce is unavailable
- constants are defined once
- namespace/autoloading works
- bootstrap works
- database version mechanism works
- required CoffeePOS infrastructure tables can be created by migration
- settings foundation works
- required capability checks work
- POS routing foundation works
- template loader works
- assets load only in relevant contexts
- REST namespace is registered
- health endpoint works for authorized users
- security checks are in place
- logging/error foundation works
- no Phase 01+ business functionality has been implemented
- existing unrelated site functionality is unaffected

---

# 39. Acceptance Tests

## AT-01 Plugin Activation

Given:

```text
WordPress + WooCommerce active
```

When the plugin is activated:

Then:

```text
No fatal error
Bootstrap initializes
Required schema/version foundation exists
```

---

## AT-02 WooCommerce Missing

Given:

```text
WordPress active
WooCommerce inactive
```

When CoffeePOS loads:

Then:

```text
No fatal error
WooCommerce-dependent functionality is not initialized
Admin receives appropriate dependency information
```

---

## AT-03 Autoload

Instantiate a foundation class through its namespace without manually including its file.

Expected:

```text
class loads successfully
```

---

## AT-04 Routing

Visit each configured POS route.

Expected:

```text
correct route is recognized
no WordPress fatal
placeholder/foundation template can render
```

No business UI is required.

---

## AT-05 Template Loader

Request a known foundation template.

Expected:

```text
template resolves
template receives data
no direct database access occurs in the template
```

---

## AT-06 Asset Loader

Load the POS route.

Expected:

```text
CoffeePOS assets load
```

Visit an unrelated WordPress page.

Expected:

```text
CoffeePOS POS assets are not unnecessarily loaded
```

---

## AT-07 REST

Request:

```text
/wp-json/coffeepos/v1/health
```

Expected:

```text
stable response
correct permission behavior
```

---

## AT-08 Unauthorized REST

Call the protected endpoint without permission.

Expected:

```text
403 / appropriate authorization failure
```

---

## AT-09 Database Migration

Install/activate in a clean environment.

Expected:

```text
required CoffeePOS schema is created
db version is stored
```

Run the migration again.

Expected:

```text
no destructive duplicate schema operation
```

---

## AT-10 Deactivation

Deactivate plugin.

Expected:

```text
no business data deletion
no WooCommerce order/customer/product deletion
```

---

## AT-11 Existing Site Compatibility

With WooCommerce and unrelated WordPress functionality active:

Expected:

```text
no unrelated fatal errors
no modification to WooCommerce core
no modification to unrelated routes
```

---

# 40. Files Expected to Exist

The exact list may vary slightly according to implementation, but the foundation should include equivalents for:

```text
coffeepos.php
uninstall.php

includes/Core/Bootstrap.php
includes/Core/Environment.php

includes/Infrastructure/Database/...
includes/Infrastructure/Settings/...
includes/Infrastructure/Logging/...

includes/REST/...
includes/POS/...
includes/Admin/...

templates/... foundation templates
assets/js/... foundation modules
assets/css/... foundation styles
```

Junie must prefer the smallest coherent implementation over creating dozens of empty placeholder classes.

---

# 41. Forbidden Changes

During Phase 00, Junie MUST NOT:

- implement Cashier functionality
- implement Cart business logic
- implement Checkout
- create WooCommerce orders
- implement payment processing
- implement Customer Display sync
- implement KDS
- implement Order Queue logic
- implement Order History logic
- implement Shift calculations
- implement Reports
- introduce React/Vue/another frontend framework
- rewrite WooCommerce data
- create duplicate product/customer/order tables
- change the documented architecture silently
- perform unrelated refactoring
- migrate the entire existing plugin to a new architecture without inspecting compatibility
- create speculative abstractions

---

# 42. Phase Exit Report

Junie MUST report:

## Changed

All files created/modified.

## Architecture

Explain the implemented foundation structure.

## Database

Report schema/migrations created.

## Routes

Report routes registered.

## REST

Report endpoints registered.

## Security

Report capability/permission/nonce foundations.

## Verification

Report tests/checks performed.

## Not Implemented

Confirm that Phase 01+ business features were not implemented.

## Issues

List architecture conflicts, environment problems, or assumptions that require review.

---

# 43. Final Phase 00 Rule

When this phase is complete, stop.

Do not automatically begin Phase 01.

The next implementation must be started explicitly using the Phase 01 specification.
