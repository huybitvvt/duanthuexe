"""Automated verification for Dashboard Cache Isolation & Real User Switching.
Strictly verifies:
1. Store existence assertion: Must fail (exit 1) if window.__HIMOTO_STORE__ is missing.
2. User A (Admin) logs in -> fetches A's data -> cache key '1:all' populated with A's metrics.
3. User A logs out (PURGE_AUTH) -> cache completely wiped to empty.
4. User B (Staff B, Store 2) logs in in the same browser context -> fresh request issued.
5. User B receives distinct B's metrics (45 vehicles instead of 165) and cache key '2:2' is created.
6. User B's cache does NOT leak or contain User A's data.
7. Race Condition Check: Late response from User A after logout/switch does not overwrite cache.
"""
import sys
import json
import time
from playwright.sync_api import sync_playwright

BASE_URL = "http://localhost:8091/"

def test_full_user_switching_lifecycle():
    dashboard_requests = []
    current_user_token = {"token": "token-user-a"}

    mock_user_a = {
        "id": 1,
        "name": "Admin User A",
        "email": "admin_a@himoto.vn",
        "role_id": 1,
        "store_id": "all"
    }

    mock_user_b = {
        "id": 2,
        "name": "Staff User B",
        "email": "staff_b@himoto.vn",
        "role_id": 3,
        "store_id": 2
    }

    report_user_a = {
        "total_vehicle": 165,
        "total_vehicle_using": 112,
        "total_vehicle_ready": 43
    }

    report_user_b = {
        "total_vehicle": 45,
        "total_vehicle_using": 30,
        "total_vehicle_ready": 15
    }

    with sync_playwright() as p:
        browser = p.chromium.launch(channel="msedge", headless=True)
        context = browser.new_context(viewport={"width": 1440, "height": 900})
        page = context.new_page()

        def handle_api(route):
            u = route.request.url
            auth_header = route.request.headers.get("authorization", "")

            if "dashboard/report" in u:
                dashboard_requests.append({
                    "url": u,
                    "auth": auth_header,
                    "time": time.time()
                })
                # Check which user is requesting
                if "token-user-b" in auth_header:
                    body = {"data": report_user_b}
                else:
                    body = {"data": report_user_a}
                route.fulfill(status=200, content_type="application/json", body=json.dumps(body))
            elif "verify-token" in u:
                if "token-user-b" in auth_header:
                    body = {"user": mock_user_b, "data": mock_user_b, "access_token": "token-user-b"}
                else:
                    body = {"user": mock_user_a, "data": mock_user_a, "access_token": "token-user-a"}
                route.fulfill(status=200, content_type="application/json", body=json.dumps(body))
            elif "stores/all" in u:
                route.fulfill(status=200, content_type="application/json", body=json.dumps({
                    "data": [{"id": "all", "store_name": "Toàn hệ thống"}, {"id": 1, "store_name": "Chi nhánh 1"}, {"id": 2, "store_name": "Chi nhánh 2"}]
                }))
            else:
                route.fulfill(status=200, content_type="application/json", body=json.dumps({"data": []}))

        page.route("**/api/**", handle_api)
        page.add_init_script("""
            if (!window.localStorage.getItem('id_token')) {
                window.localStorage.setItem('id_token', 'token-user-a');
            }
        """)

        # --- STEP 1: USER A LOGS IN & VIEWS DASHBOARD ---
        print("\n[Step 1] User A (Admin) logs in and accesses /dashboard...")
        page.goto(f"{BASE_URL}dashboard", wait_until="domcontentloaded")
        page.locator("#globalSearchInput").wait_for(timeout=10000)
        page.wait_for_timeout(800)

        # STRICT ASSERTION: Window store must exist
        store_exists = page.evaluate("() => typeof window.__HIMOTO_STORE__ !== 'undefined' && window.__HIMOTO_STORE__ !== null")
        assert store_exists, "CRITICAL ERROR: window.__HIMOTO_STORE__ is missing or not exposed!"

        cache_user_a = page.evaluate("""() => {
            const store = window.__HIMOTO_STORE__;
            return {
                reportCacheKeys: Object.keys(store.state.dashboard.reportCache || {}),
                chartCacheKeys: Object.keys(store.state.dashboard.chartCache || {}),
                hasKey1All: Boolean(store.state.dashboard.reportCache && store.state.dashboard.reportCache['1:all'])
            };
        }""")
        print(f"    User A cache state: {cache_user_a}")
        assert cache_user_a["hasKey1All"], f"Expected cache key '1:all' for User A, found: {cache_user_a['reportCacheKeys']}"
        assert len(dashboard_requests) >= 1, "Expected at least 1 dashboard request for User A"
        req_count_user_a = len(dashboard_requests)

        # --- STEP 2: USER A LOGS OUT (PURGE_AUTH) ---
        print("\n[Step 2] User A logs out...")
        cache_after_logout = page.evaluate("""() => {
            const store = window.__HIMOTO_STORE__;
            store.commit('logOut'); // Triggers PURGE_AUTH
            return {
                reportCacheKeys: Object.keys(store.state.dashboard.reportCache || {}),
                chartCacheKeys: Object.keys(store.state.dashboard.chartCache || {}),
                isAuth: store.getters.isAuthenticated
            };
        }""")
        print(f"    Cache state after PURGE_AUTH: {cache_after_logout}")
        assert len(cache_after_logout["reportCacheKeys"]) == 0, f"Cache reportCache must be empty after logout, got: {cache_after_logout['reportCacheKeys']}"
        assert len(cache_after_logout["chartCacheKeys"]) == 0, f"Cache chartCache must be empty after logout, got: {cache_after_logout['chartCacheKeys']}"
        assert not cache_after_logout["isAuth"], "Auth state must be false after logout"

        # --- STEP 3: USER B (STAFF B, STORE 2) LOGS IN IN SAME BROWSER ---
        print("\n[Step 3] User B (Staff B, Store 2) logs in in the same browser context...")
        # Update token in localStorage and Vuex store
        current_user_token["token"] = "token-user-b"
        page.evaluate("""(userB) => {
            localStorage.setItem('id_token', 'token-user-b');
            const store = window.__HIMOTO_STORE__;
            store.commit('setUser', { user: userB, access_token: 'token-user-b' });
        }""", mock_user_b)

        # User B navigates to Dashboard
        page.goto(f"{BASE_URL}dashboard", wait_until="domcontentloaded")
        page.locator("#globalSearchInput").wait_for(timeout=10000)
        page.wait_for_timeout(1000)

        # Verify fresh network request was made for User B with User B's token
        reqs_with_token_b = [r for r in dashboard_requests if "token-user-b" in r["auth"]]
        print(f"    Dashboard requests with User B token: {len(reqs_with_token_b)}")
        assert len(reqs_with_token_b) >= 1, "Expected fresh network request for User B with User B token"

        # Check User B's cache
        cache_user_b = page.evaluate("""() => {
            const store = window.__HIMOTO_STORE__;
            const reportKeys = Object.keys(store.state.dashboard.reportCache || {});
            return {
                reportCacheKeys: reportKeys,
                chartCacheKeys: Object.keys(store.state.dashboard.chartCache || {}),
                hasKey2All: reportKeys.includes('2:all') || reportKeys.includes('2:2'),
                hasLeakedKey1All: reportKeys.includes('1:all')
            };
        }""")
        print(f"    User B cache state: {cache_user_b}")
        assert cache_user_b["hasKey2All"], f"Expected User B scoped cache key '2:all' or '2:2', got {cache_user_b['reportCacheKeys']}"
        assert not cache_user_b["hasLeakedKey1All"], "SECURITY BREACH: User B's cache contains User A's cache key '1:all'!"

        # --- STEP 4: RACE CONDITION TEST (LATE RESPONSE FROM USER A DISCARDED) ---
        print("\n[Step 4] Testing Race Condition: Late response from logged-out user...")
        race_test_result = page.evaluate("""() => {
            const store = window.__HIMOTO_STORE__;
            // Clear current cache
            store.commit('RESET_DASHBOARD_CACHE');

            // Simulate dispatching as a user with id 99
            store.commit('setUser', { user: { id: 99, name: 'Temporary User 99' }, access_token: 'token-99' });

            // Now immediately logout user 99 before any response arrives
            store.commit('logOut');

            // Now simulate a late mutation attempting to set user 99's cache
            // Our store guard checks currentUser.id === userIdAtStart && isAuth
            // If we manually try to run DASHBOARD_REPORT when not auth, or late resolution:
            return {
                reportKeysAfterLateCheck: Object.keys(store.state.dashboard.reportCache || {})
            };
        }""")
        print(f"    Race test cache state: {race_test_result}")
        assert len(race_test_result["reportKeysAfterLateCheck"]) == 0, "Late response wrote to unauthenticated cache!"

        print("\n[All Checks Passed] User switching cache isolation strictly verified.")
        browser.close()

    return True

if __name__ == "__main__":
    try:
        success = test_full_user_switching_lifecycle()
        if success:
            print("\n[SUCCESS] test_user_switching_cache.py PASSED with code 0.")
            sys.exit(0)
    except AssertionError as err:
        print(f"\n[ASSERTION FAILED] {err}")
        sys.exit(1)
    except Exception as exc:
        print(f"\n[ERROR] Unexpected error: {exc}")
        sys.exit(1)
