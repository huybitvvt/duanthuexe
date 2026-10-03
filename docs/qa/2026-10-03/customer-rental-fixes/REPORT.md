# Customer rental fixes — 2026-10-03

Base: `c8f6e1054b99f2ad80046f533693a3649a0279c5` (`origin/main`).
Implementation is limited to the existing Laravel/Vue application.

## Result

- Removed the inline quick-create form from the rental list. The existing add button opens the contract modal.
- Show the tariff and applied daily/package price together. Users with `order.discount_approve` or `*` can change the applied price or restore the tariff. Other users retain the tariff; the API rejects unauthorized overrides and inconsistent published totals.
- New standard contracts save as drafts first. Successful creation opens the same draft for missing information; a failed save retains entered information.
- Standard drafts reserve `YYMMDD-0001` numbers using the existing daily counter and signing date. Saving again and publishing preserve that number. Allocation skips occupied references, including deleted orders, and rolls back with failed draft creation.
- Draft creation creates no receipts and does not mark the vehicle as rented. Existing publication validation remains responsible for complete information and financial amounts.

## Verification

- PHP: **71 tests / 271 assertions**, including seven new cases, passed for `HimotoContractTest|HimotoDraftCustomerTest|LeaseBillingScheduleTest|HimotoLeaseDebtTest|LeasePdfSnapshotTest`.
- PHP test environment: `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`; `DATABASE_URL` removed.
- PHP syntax checks: six changed PHP files passed.
- Vue production build: `npm run production`, exit 0. The existing toolchain emits Sass/Browserslist warnings.
- Browser: **10 cases** passed at 1440px and 375px: four creation/pricing/error/reopen cases (staff and admin), four rental-list regression cases (staff and admin), two keyboard/signing regression cases. JSON results and screenshots are in this directory.
- Browser requests were intercepted; only local fixtures received attempted saves. PHP used an in-memory database. These checks do not establish real API integration or PostgreSQL concurrency.

Reproduce PHP verification in PowerShell:

```powershell
$env:APP_ENV = 'testing'
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = ':memory:'
Remove-Item Env:DATABASE_URL -ErrorAction SilentlyContinue
& 'E:\duanthuexe\tools\php74\php.exe' 'E:\duanthuexe\tools\phpunit.phar' --configuration phpunit.xml --filter 'HimotoContractTest|HimotoDraftCustomerTest|LeaseBillingScheduleTest|HimotoLeaseDebtTest|LeasePdfSnapshotTest'
```

Browser scripts require a loopback preview of the production Vue build:

```powershell
python tests/test_customer_rental_drafts_ui.py --base-url http://127.0.0.1:8103 --output-dir docs/qa/2026-10-03/customer-rental-fixes
python tests/test_rental_layout_ui.py --base-url http://127.0.0.1:8103 --output-dir docs/qa/2026-10-03/customer-rental-fixes/list-regression
python tests/test_contract_form_ui.py --base-url http://127.0.0.1:8103 --output-dir docs/qa/2026-10-03/customer-rental-fixes/keyboard-regression
```

## Live database preflight

Before this fix was pushed, live API health and frontend version both reported base commit `c8f6e10`.

Read-only PostgreSQL checks found migration `2026_10_03_000001_add_lease_billing_cycle_and_prepaid_amount` already applied in **batch 51**, with `billing_cycle`, `prepaid_amount` and `paperwork` present. Defaults and nullability match the migration. No lease contracts existed at inspection, so historical lease-data conversion was not exercised.

The database also has `orders.draft_reference`, its unique index, and `contract_number_counters` with its primary key. This fix requires no new database migration. No database write or repeat migration was performed during verification. Sanitized details are in [db-preflight.json](db-preflight.json).

The production startup script `docker/render-start.sh` already runs pending migrations. Frontend/backend availability after the new push must be confirmed separately from these local checks.
