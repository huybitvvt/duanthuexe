# Final deployment API check — 2026-10-02

**PASS: 9 checks, zero failures.**

Verified final commit on both deployed services: `4f6ee0a52a217aa2b3cb03ac34423ab61c5084eb`.

| Check | Count | Expected/result |
|---|---:|---|
| Administrator/accountant bank list with `store_id=0`, with/without `account_type=20` | 4 | 200, valid JSON |
| Bank list with `store_id=-1` or `invalid` | 2 | 422, validation response |
| Branch employee querying company banks with `store_id=0` | 1 | 403 |
| API `/api/health` and web `/version.json` | 2 | 200, exact final commit |

API health also reports operational status `ok`.

The previous **119 targeted API checks** passed against `f90c2dd`; the final change only adjusted warehouse UI authorization and allowed the existing company bank selector value zero in GET validation. This final probe verifies the changed query behavior and preserved denial. The full 898-case initial audit was not repeated.

Artifacts: `final-deployment-checks.json`, `final-deployment-check.log`, and `final_deployment_check.py`. No authenticated live mutation or financial/provider action was submitted. Browser checks on the final web deployment remain root's responsibility.
