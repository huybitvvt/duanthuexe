# Laravel production API audit — 2026-10-02

## Retest after fixes

**Final deployment verified: web and API both report `4f6ee0a52a217aa2b3cb03ac34423ab61c5084eb`.** Nine additional checks passed after the final store selector compatibility fix: four administrator/accountant company bank queries using `store_id=0`, two invalid store queries, branch denial for company banks, and both deployment versions. See `final-deployment-checks.json` and `FINAL_DEPLOYMENT_REPORT.md`.

**119 targeted checks passed, zero failures**, against deployed API commit `f90c2ddcf1e6bb19d90d5e49bb4e1a15544c1189`. Former malformed path/query server errors are now controlled 404/422; protected bound resources reject missing JWT with 401; report search/export and accounting/director/operations access return 200; company counters agree at 2,685. Valid UI page sizes 15/100/1000, legacy formatted dates, source arrays, and cross-store denials passed.

See `retest-corrected-results.json` and `RETEST_REPORT.md`. The remainder of this document records the **initial audit of 2bed34d before fixes**; its server error findings are historical. Capacity/external integration/fixture limits still apply.

## Environment and scope

- API: `https://himoto-api.onrender.com`; health reports deployed commit `2bed34d90e602b7c05a6a4d4ff6456b4dcff931c` and database OK.
- Runtime inventory: **213 route definitions**, including **100 GET definitions** and **113 mutation definitions**.
- **898 recorded request checks**. Login and health setup requests are excluded from this count.
- **207/213 route definitions exercised**: all 100 GET routes plus authentication guards on 107 mutation routes. This is route discovery/guard coverage, not 97% business workflow coverage.
- Live testing used GET reads and unauthenticated empty mutation requests guaranteed to pass through JWT guards. No authenticated mutation, external dispatch, financial change, or file upload was submitted.
- Evidence records only request paths, nonpersonal parameters, status, timing, schemas, counts, and binary signatures. Credentials/tokens and response payload PII remain outside artifacts.

## Confirmed defects sent to root

1. **Report search breaks for normal text** — `keyword=Honda` produces HTTP 500 in `report/detail-report-new`, `report/detail-report-day-by-day`, and `export/general_reports`. `ReportService` compares `orders.id` with `substr(keyword, 1)` (`onda`) in PostgreSQL bigint expressions. Expected: valid filtered report or empty report, HTTP 200.
2. **Company accounts cannot read legacy detail reports** — `report/detail-report` with valid dates returns HTTP 500 for accounting, director, and operations roles. Legacy `ReportService` scope assumes every role other than literal `role_id === 1` has `$user->store->id`. Expected: company scope for approved roles.
3. **Company order counters are wrong** — all-time `report/quick-report` reports 2,685 orders for administrator but zero for director and operations. Both have company capabilities; legacy literal role filtering produces an empty scope. Expected: the same company counter with the same filters.
4. **All 23 dynamic GET patterns crash on nonnumeric path IDs** — e.g. `accounting/journal-entries/not-an-id`, `banks/not-an-id`, `order/car-rental/not-an-id`. Expected: 404 or 422, not 500. Route parameter constraints are missing. Batch delete parameter `data` must preserve comma-separated IDs.
5. **Malformed pagination crashes** — `accounting/journal-entries?per_page=nonsense` and `daily-cash-registers/history?per_page=nonsense` return 500. Expected: 422 or safe normalization.
6. **Malformed dates crash several read paths** — verified in cash-register history (`from_date`), HR duty schedules (`date`), detail reports, transactions, vehicle revenue, lead list, quick report, and report export (`start_date=not-a-date`). Expected: structured 422 response.
7. **Scalar lead source filter crashes** — `leads?source=123` returns 500 because `count()` receives a string. Expected: structured 422 identifying an invalid array.

There were **42 HTTP 500 responses** across the recorded cases. They represent the shared defects above, not 42 separate root causes.

## Other findings and exclusions

- Seven APIs accept a reversed date range and return empty results with HTTP 200. Root received the cases to add range validation: trial balance, journal entries, detail reports, transactions, receipts, vehicle revenue.
- No auth: 90 GET routes returned 401; nine missing-model GET routes returned 404 before JWT due middleware ordering. Root is changing JWT priority. Mutation guards: 82 returned 401, 25 returned 404; all 107 requests were denied.
- Eight malformed/unsigned token requests returned 401. No authentication bypass was found.
- Existing rental order details, legal document DTOs, and handover HTML loaded for administrator; six other roles were denied consistently according to store scope/capabilities. Existing cash details and a canonical store detail also passed.
- Existing bank authorization initially had two harness false positives from string/integer store ID comparison; correct store matches were allowed. No bank IDOR was demonstrated.
- Accountant SePay list/unmatched calls return 403 due additional `PilotAccess` restrictions. Root identified this as existing design; no permission grant was inferred.
- Administrator lead index intentionally includes soft-deleted rows; detail lookup of one archived row returns 404. This is listed as a contract inconsistency observation, not a release blocker.
- Vehicle cost fields are visible to `vehicle.view_all`. No explicit policy requiring concealment was established; root received the observation without a speculative privilege change.
- Warehouse movement history API correctly denies HR/telesale and other-store viewers. UI currently enables the history button based only on `vehicle.view_all`; root received this UI action gating issue for browser verification.

## Positive checks

- All **77 static GET routes** passed with administrator and valid parameters, covering accounting, cash register, HR, GPS, approval/reminders, vehicle/warehouse, customer/lead, rental/sales, SePay, and reporting.
- All **nine spreadsheet export endpoints** returned HTTP 200 with ZIP/XLSX signatures. Binary content was checked in memory and not saved.
- **360 extended role checks** covered static endpoints absent from the prior baseline.
- Four read-only quoted search strings (`' OR '1'='1`) in lead/customer/vehicle/rental searches returned controlled JSON; no server crash or bypass was demonstrated. This is a parameter handling check, not a full SQL injection proof.

## Performance

- 331 HTTP 200 samples: median **250 ms**, p95 **1,187 ms** end-to-end from this client, including reports/exports and network latency. This does not establish database CPU/query duration or a production SLA.
- No flood/load test was run against shared production. Capacity at 10x normal traffic, stress, endurance, and rate-limit exhaustion are **unverified**. Source API throttle is 600 requests/minute; threshold behavior must be validated locally.

## Live fixture limits

- No existing journal entries, maintenance schedules, lease contracts, ownership requests, or SePay payment requests were available for valid detail workflow tests.
- No rental orders or banks were returned for canonical CS1/CS2 in discovery; existing legacy records were used only for denied scope checks. Positive branch-owned write workflows require root's local fixtures or owned disposable UAT records.
- Existing lease PDFs were deliberately skipped: GET PDF generation can capture a missing immutable snapshot and write an audit record. Root's local tests should cover legal snapshots, checksums, approval/payment/allocation/reversal, debt/ownership, and generated PDFs.
- External GPS integration is explicitly pending credentials in response contract. SMS/email dispatch, live webhook reconciliation, payments, and external provider failures were not activated.

## Artifacts

- `route-inventory.json`: exact runtime inventory.
- `audit_readonly.py`: reproducible read-only and guard probe modes.
- `readonly-results.json`, `edge-results.json`, `scope-results.json`, `finish-results.json`, `business-results.json`: recorded phases.
- `audit-classified-results.json`: combined 898 checks with observations/harness corrections classified.
- `summarize.py`: deterministic rollup.

**Status before root fixes: NEEDS WORK.** Positive workflows pass, but report/search/counter defects and malformed input server errors remain on the tested deployment. Re-run changed cases against the new deployment before declaring them fixed.
