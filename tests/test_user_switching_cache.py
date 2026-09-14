import os
import sys
import time
import json
from playwright.sync_api import sync_playwright

BASE_URL = "http://localhost:8091/"

def test_full_user_switching_lifecycle():
    print("\n" + "="*80)
    print("HIMOTO VERIFICATION: User Switching Cache Isolation & Pending Race Test")
    print("="*80)

    dashboard_requests = []
    delayed_routes = []

    mock_user_a = {
        "id": 1,
        "name": "Super Admin A",
        "email": "admin@himoto.vn",
        "role_id": 1,
        "store_id": "all"
    }

    mock_user_b = {
        "id": 2,
        "name": "Staff B",
        "email": "staff_b@himoto.vn",
        "role_id": 2,
        "store_id": 2
    }

    report_user_a = {
        "total_vehicle": 160,
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

            # Intercept race test request and hold it pending
            if "store_id=race_test" in u:
                delayed_routes.append(route)
                return

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

        # --- STEP 1: USER A (ADMIN) LOGS IN & VIEWS DASHBOARD ---
        print("\n[Step 1] User A (Admin) accesses /dashboard...")
        page.goto(f"{BASE_URL}dashboard", wait_until="domcontentloaded")
        page.locator("#globalSearchInput").wait_for(timeout=10000)
        page.wait_for_timeout(800)

        # STRICT ASSERTION: Window store must exist
        store_exists = page.evaluate("() => typeof window.__HIMOTO_STORE__ !== 'undefined' && window.__HIMOTO_STORE__ !== null")
        assert store_exists, "CRITICAL ERROR: window.__HIMOTO_STORE__ is missing or not exposed!"

        # Mark SPA Shell to verify zero-reload across user switching
        page.evaluate("window.__test_spa_shell_token = 'shell-session-' + Date.now();")

        cache_user_a = page.evaluate("""() => {
            const store = window.__HIMOTO_STORE__;
            return {
                reportCacheKeys: Object.keys(store.state.dashboard.reportCache || {}),
                chartCacheKeys: Object.keys(store.state.dashboard.chartCache || {}),
                hasKey1All: Boolean(store.state.dashboard.reportCache && store.state.dashboard.reportCache['1:all']),
                sessionId: store.getters.authSessionId
            };
        }""")
        print(f"    User A cache state: {cache_user_a}")
        assert cache_user_a["hasKey1All"], f"Expected cache key '1:all' for User A, found: {cache_user_a['reportCacheKeys']}"
        assert cache_user_a["sessionId"], "authSessionId must be present in Vuex store"
        session_id_a = cache_user_a["sessionId"]

        # --- STEP 2: USER A LOGS OUT IN SPA (PURGE_AUTH) ---
        print("\n[Step 2] User A logs out in SPA...")
        logout_state = page.evaluate("""() => {
            const store = window.__HIMOTO_STORE__;
            store.commit('logOut'); // PURGE_AUTH
            return {
                reportCacheKeys: Object.keys(store.state.dashboard.reportCache || {}),
                chartCacheKeys: Object.keys(store.state.dashboard.chartCache || {}),
                isAuth: store.getters.isAuthenticated,
                newSessionId: store.getters.authSessionId
            };
        }""")
        print(f"    State after PURGE_AUTH: {logout_state}")
        assert len(logout_state["reportCacheKeys"]) == 0, "reportCache must be emptied after logout"
        assert len(logout_state["chartCacheKeys"]) == 0, "chartCache must be emptied after logout"
        assert not logout_state["isAuth"], "User must be unauthenticated"
        assert logout_state["newSessionId"] != session_id_a, "Session ID must change upon logout"

        # --- STEP 3: USER B LOGS IN WITHIN SAME SPA (NO RELOAD) ---
        print("\n[Step 3] User B (Staff B, Store 2) logs in in-place (no page reload)...")
        shell_check_before = page.evaluate("window.__test_spa_shell_token")

        # In-SPA switch: update auth state and load dashboard data without page.goto
        page.evaluate("""(userB) => {
            localStorage.setItem('id_token', 'token-user-b');
            const store = window.__HIMOTO_STORE__;
            store.commit('setUser', { user: userB, access_token: 'token-user-b' });
            return store.dispatch('dashboard_report', { store_id: 2 });
        }""", mock_user_b)
        page.wait_for_timeout(600)

        shell_check_after = page.evaluate("window.__test_spa_shell_token")
        assert shell_check_before == shell_check_after, "SPA Shell was destroyed! User switching must remain in SPA."

        # Verify network request sent with User B token
        reqs_with_token_b = [r for r in dashboard_requests if "token-user-b" in r["auth"]]
        print(f"    Dashboard requests with User B token: {len(reqs_with_token_b)}")
        assert len(reqs_with_token_b) >= 1, "Expected fresh network request with User B token"

        cache_user_b = page.evaluate("""() => {
            const store = window.__HIMOTO_STORE__;
            const reportKeys = Object.keys(store.state.dashboard.reportCache || {});
            return {
                reportCacheKeys: reportKeys,
                hasKey2_2: reportKeys.includes('2:2'),
                hasLeakedKey1All: reportKeys.includes('1:all')
            };
        }""")
        print(f"    User B cache state: {cache_user_b}")
        assert cache_user_b["hasKey2_2"], f"Expected User B scoped key '2:2', got {cache_user_b['reportCacheKeys']}"
        assert not cache_user_b["hasLeakedKey1All"], "SECURITY VIOLATION: User A key '1:all' leaked into User B cache!"

        # --- STEP 4: REAL PENDING IN-FLIGHT PROMISE RACE TEST (SAME USER ID) ---
        print("\n[Step 4] Real Pending In-Flight Request Race Test (User A Session 1 -> Session 2)...")
        # Log in as User A Session 1
        page.evaluate("""(userA) => {
            const store = window.__HIMOTO_STORE__;
            store.commit('setUser', { user: userA, access_token: 'token-user-a' });
        }""", mock_user_a)

        # Dispatch a request with store_id=race_test, held pending by Playwright router
        page.evaluate("""() => {
            const store = window.__HIMOTO_STORE__;
            window.__pending_promise = store.dispatch('dashboard_report', { store_id: 'race_test' });
        }""")
        page.wait_for_timeout(300)

        assert len(delayed_routes) == 1, "Expected exactly 1 pending in-flight route captured"
        print("    In-flight request captured and held pending by network mock.")

        # User A logs out while request is still pending
        page.evaluate("window.__HIMOTO_STORE__.commit('logOut');")

        # User A logs back in (same user ID: 1, but new session ID)
        page.evaluate("""(userA) => {
            window.__HIMOTO_STORE__.commit('setUser', { user: userA, access_token: 'token-user-a' });
        }""", mock_user_a)

        # Now fulfill the old pending route from Session 1
        print("    Fulfilling delayed response from Session 1...")
        delayed_routes[0].fulfill(
            status=200,
            content_type="application/json",
            body=json.dumps({"data": {"leak_status": "STALE_SESSION_DATA_LEAKED"}})
        )
        page.wait_for_timeout(500)

        # Assert that the late response was DISCARDED and NOT written to reportCache
        cache_after_delayed = page.evaluate("""() => {
            const store = window.__HIMOTO_STORE__;
            const keys = Object.keys(store.state.dashboard.reportCache || {});
            return {
                keys: keys,
                hasStaleKey: keys.includes('1:race_test')
            };
        }""")
        print(f"    Cache state after delayed fulfillment: {cache_after_delayed}")
        assert not cache_after_delayed["hasStaleKey"], "RACE CONDITION VULNERABILITY: Stale late response from previous session wrote to cache!"
        print("    [PASS] Late pending response from previous session was safely discarded!")

        print("\n" + "="*80)
        print("[SUCCESS] All user switching and race condition tests PASSED cleanly.")
        print("="*80)
        browser.close()

    return True

if __name__ == "__main__":
    try:
        success = test_full_user_switching_lifecycle()
        if success:
            sys.exit(0)
    except AssertionError as err:
        print(f"\n[ASSERTION FAILED] {err}")
        sys.exit(1)
    except Exception as exc:
        print(f"\n[ERROR] Unexpected error: {exc}")
        sys.exit(1)
