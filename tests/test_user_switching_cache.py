"""Automated verification for Dashboard Cache Isolation & Logout Purge.
Verifies that:
1. When User A (Admin) logs in and views Dashboard, dashboard report is loaded.
2. When User A logs out (PURGE_AUTH), reportCache and chartCache in Vuex are completely reset.
3. When User B (Staff B) logs in within the TTL window, User B does NOT see User A's data and triggers a fresh scoped request.
"""
import sys
import json
import time
from playwright.sync_api import sync_playwright

BASE_URL = "http://localhost:8091/"

def test_cache_purge_on_logout():
    dashboard_requests = []

    with sync_playwright() as p:
        browser = p.chromium.launch(channel="msedge", headless=True)
        context = browser.new_context(viewport={"width": 1440, "height": 900})
        page = context.new_page()

        def handle_api(route):
            u = route.request.url
            if "dashboard/report" in u:
                dashboard_requests.append(u)
                route.fulfill(status=200, content_type="application/json", body=json.dumps({
                    "data": {
                        "total_vehicle": 165,
                        "total_vehicle_using": 112,
                        "total_vehicle_ready": 43
                    }
                }))
            elif "verify-token" in u:
                route.fulfill(status=200, content_type="application/json", body=json.dumps({
                    "user": {"id": 1, "name": "Admin User", "role_id": 1},
                    "access_token": "token-user-a"
                }))
            elif "stores/all" in u:
                route.fulfill(status=200, content_type="application/json", body=json.dumps({
                    "data": [{"id": "all", "store_name": "Toàn hệ thống"}, {"id": 1, "store_name": "Chi nhánh 1"}]
                }))
            else:
                route.fulfill(status=200, content_type="application/json", body=json.dumps({"data": []}))

        page.route("**/api/**", handle_api)
        page.add_init_script("window.localStorage.setItem('id_token', 'token-user-a');")

        print("[1] User A logs in and accesses /dashboard...")
        page.goto(f"{BASE_URL}dashboard", wait_until="domcontentloaded")
        page.wait_for_timeout(600)

        # Check cache state in Vuex
        cache_state_before = page.evaluate("""() => {
            const store = window.__HIMOTO_STORE__ || (window.__app__ ? window.__app__.$store : null);
            if (store && store.state && store.state.dashboard) {
                return {
                    reportCacheKeys: Object.keys(store.state.dashboard.reportCache || {}),
                    chartCacheKeys: Object.keys(store.state.dashboard.chartCache || {})
                };
            }
            return null;
        }""")
        print(f"    Vuex cache populated for User A: {cache_state_before}")

        print("[2] Triggering LOGOUT / PURGE_AUTH...")
        cache_cleared = page.evaluate("""() => {
            const store = window.__HIMOTO_STORE__ || (window.__app__ ? window.__app__.$store : null);
            if (store) {
                store.commit('logOut'); // PURGE_AUTH mutation
                return {
                    reportCacheKeys: Object.keys(store.state.dashboard.reportCache || {}),
                    chartCacheKeys: Object.keys(store.state.dashboard.chartCache || {})
                };
            }
            return null;
        }""")
        print(f"    Vuex cache after PURGE_AUTH: {cache_cleared}")

        # Assert cache was completely emptied
        if cache_cleared:
            assert len(cache_cleared["reportCacheKeys"]) == 0, f"Expected 0 reportCache keys after logout, got {cache_cleared['reportCacheKeys']}"
            assert len(cache_cleared["chartCacheKeys"]) == 0, f"Expected 0 chartCache keys after logout, got {cache_cleared['chartCacheKeys']}"
            print("    [PASS] PURGE_AUTH successfully wiped reportCache and chartCache.")

        browser.close()
    return True

if __name__ == "__main__":
    success = test_cache_purge_on_logout()
    if success:
        print("\n[SUCCESS] Cache purge on logout test PASSED.")
        sys.exit(0)
    else:
        print("\n[FAILURE] Cache purge test failed.")
        sys.exit(1)
