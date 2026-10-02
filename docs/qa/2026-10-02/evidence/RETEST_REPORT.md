# Targeted production API retest — 2026-10-02

**PASS: 119 recorded checks; zero failures.**

- Expected and verified deployed API commit: `f90c2ddcf1e6bb19d90d5e49bb4e1a15544c1189`.
- 23 nonnumeric resource paths now return JSON 404.
- 13 former pagination/date/source server error cases now return JSON 422.
- 23 bound-resource GET routes plus one invalid-query GET reject missing JWT with 401 before binding/validation.
- Four legacy detail report checks (admin/accountant/director/operations) return 200.
- Normal `Honda` text search returns 200 in both report APIs and XLSX export. Both export scenarios have confirmed ZIP signatures.
- Company counters agree: admin/director/operations each report **2,685** orders.
- Eight reversed date ranges reject with 422.
- Nine valid pagination cases using UI limits 15/100/1000 pass; three requests above 1000 reject with 422.
- Legacy `01-10-2026` / `02-10-2026` dates, valid two-date array, invalid reversed array, valid source arrays, invalid source scalar/array cases behave correctly.
- Accountant SePay restrictions and cross-store rental/bank/lease access restrictions remain enforced.

Status totals: **30 × 200**, **37 × 422**, **24 × 401**, **23 × 404**, **5 × 403**.

Two initial export assertions were harness false positives: the endpoint label omitted a leading slash, so binary mode was not enabled. The tester was corrected and both exports were requested again with ZIP signature assertions. `retest-corrected-results.json` contains the 117 original cases with this correction plus the two confirming requests, totaling 119. No application defect was hidden.

Artifacts: `retest_deployment.py`, `retest.log`, `retest-results.json`, `retest_export_confirmation.py`, `retest-export-confirmation-results.json`, and `retest-corrected-results.json`.

This retest is read-only. It verifies the repaired API boundaries and report behavior, not live money flows, external delivery/provider integration, or production load capacity. The initial audit fixture limits remain in `REPORT.md`.
