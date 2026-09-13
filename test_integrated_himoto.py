import json
import time
from urllib.parse import urlparse, parse_qs
from playwright.sync_api import sync_playwright

BASE_URL = "http://localhost:8091/"

def run_himoto_integration_tests():
    test_results = {
        "overflow_tests": [],
        "search_resize_focus": False,
        "paginated_search_compatible": False,
        "vehicle_drawer_contract": False,
        "drawer_ctrl_k_resistant": False,
        "drawer_accessibility": False,
        "header_create_button": False,
        "admin_403_handling": False,
        "role_gating_and_redirect": False,
        "dashboard_kpi": False
    }

    # Mock Data Fixtures according to actual Laravel backend contracts
    mock_user_admin = {
        "id": 1,
        "name": "Quản Trị Viên HIMOTO",
        "email": "admin@himoto.vn",
        "role_id": 1,
        "store_id": 1,
        "role_rel": {"slug": "quan-tri-vien", "name": "Quản trị viên"}
    }

    mock_user_lead = {
        "id": 4,
        "name": "Chuyên Viên Tư Vấn Lead",
        "email": "lead@himoto.vn",
        "role_id": 4,
        "store_id": 1,
        "role_rel": {"slug": "tu-van-lead", "name": "Tư vấn Lead"}
    }

    mock_stores = [
        {"id": "all", "store_name": "Toàn hệ thống"},
        {"id": 1, "store_name": "HIMOTO Đống Đa"},
        {"id": 2, "store_name": "HIMOTO Cầu Giấy"},
        {"id": 3, "store_name": "HIMOTO Tây Hồ"},
        {"id": 4, "store_name": "HIMOTO Hà Đông"}
    ]

    mock_dashboard_report = {
        "total_vehicle": 165,
        "total_vehicle_using": 112,
        "total_vehicle_ready": 43,
        "total_vehicle_broken": 10,
        "total_vehicle_repairing": 0,
        "total_customer": 284,
        "total_staff": 18,
        "total_order_in_day": 14,
        "total_order_in_month": 268,
        "total_order_out_date_in_month": 5,
        "total_deposit_in_day_new": 14000000,
        "total_renew_in_day_new": 4500000,
        "total_rental_fees_in_day_new": 18500000,
        "total_refund_in_day_new": 3200000,
        "total_deposit_in_month_new": 180000000,
        "total_renew_in_month_new": 45000000,
        "total_rental_fees_in_month_new": 220000000,
        "total_refund_in_month_new": 35000000,
        "total_origin_refund_in_day_new": 2500000,
        "total_origin_refund_in_month_new": 28000000,
        "total_money_early_in_day_new": 0,
        "total_money_early_in_month_new": 1200000,
        "total_money_out_date_in_day_new": 800000,
        "total_money_out_date_in_month_new": 6500000
    }

    mock_chart_data = {
        "labels": ["01/09", "02/09", "03/09", "04/09", "05/09", "06/09", "07/09"],
        "values": [12000000, 14500000, 11800000, 16200000, 13400000, 15000000, 14800000]
    }

    # Realistic vehicle models: license, odometer, string status (repairing / ready)
    mock_vehicles = [
        {"id": 101, "name": "Honda Vision", "license": "29B1-888.88", "status": "repairing", "odometer": 12500, "store": {"id": 1, "store_name": "HIMOTO Dong Da"}},
        {"id": 102, "name": "Honda Air Blade", "license": "29K1-999.99", "status": "ready", "odometer": 24800, "store": {"id": 2, "store_name": "HIMOTO Cau Giay"}}
    ]

    mock_customers = [
        {"id": 201, "name": "Nguyễn Văn An", "phone": "0912345678", "id_card": "001200012345", "address": "Ba Đình, Hà Nội", "total_order": 3}
    ]

    mock_orders = [
        {"id": 301, "customer_name": "Nguyễn Văn An", "store_name": "HIMOTO Đống Đa", "order_status": "renting", "status_label": "Đang thuê", "total": 900000, "vehicles": [{"name": "Honda Vision", "license": "29B1-888.88"}]}
    ]

    api_state = {
        "deny_dashboard": False,
        "role4": False
    }

    page_errors = []

    with sync_playwright() as p:
        browser = p.chromium.launch(channel="msedge", headless=True)
        context = browser.new_context(viewport={"width": 1440, "height": 900})
        page = context.new_page()

        page.on("console", lambda msg: print(f"[BROWSER] {msg.text}", flush=True))
        page.on("pageerror", lambda err: page_errors.append(str(err)))

        def handle_api(route):
            u = urlparse(route.request.url)
            q = parse_qs(u.query)
            status, body = 200, {"data": []}

            if "verify-token" in u.path:
                user = mock_user_lead if api_state["role4"] else mock_user_admin
                body = {"user": user, "data": user, "access_token": "mock_jwt_token_himoto"}
            elif "/dashboard/" in u.path and api_state["deny_dashboard"]:
                status, body = 403, {"error": True, "message": "Forbidden fixture"}
            elif u.path.endswith("/stores/all"):
                body = {"data": mock_stores}
            elif u.path.endswith("/dashboard/report-chart"):
                body = {"data": mock_chart_data}
            elif u.path.endswith("/dashboard/report"):
                body = {"data": mock_dashboard_report}
            elif u.path.endswith("/vehicle/vehicles"):
                # VehicleRepositoryEloquent filter by name / keyword
                needle = q.get("name", q.get("keyword", [""]))[0].lower()
                rows = [v for v in mock_vehicles if needle in (v["name"] + " " + v["license"]).lower()] if needle else mock_vehicles
                body = {
                    "data": {
                        "current_page": 1,
                        "last_page": 1,
                        "per_page": 20,
                        "total": len(rows),
                        "data": rows
                    }
                }
            elif u.path.endswith("/customers"):
                body = {
                    "data": {
                        "current_page": 1,
                        "last_page": 1,
                        "per_page": 20,
                        "total": len(mock_customers),
                        "data": mock_customers
                    }
                }
            elif u.path.endswith("/order/car-rental"):
                body = {
                    "data": mock_orders,
                    "pagination": {
                        "current_page": 1,
                        "last_page": 1,
                        "per_page": 20,
                        "total": len(mock_orders)
                    }
                }
            elif u.path.endswith("/leads"):
                body = {"data": []}
            route.fulfill(status=status, content_type="application/json", body=json.dumps(body))

        page.route("**/api/**", handle_api)
        page.add_init_script("window.localStorage.setItem('id_token', 'mock_jwt_token_himoto');")

        print("Navigating to /dashboard...", flush=True)
        page.goto(f"{BASE_URL}dashboard", wait_until="domcontentloaded")
        page.locator("#globalSearchInput").wait_for(timeout=10000)
        page.wait_for_timeout(800)

        # -------------------------------------------------------------
        # TEST 1: Check Horizontal Overflow across 5 Viewport Widths
        # -------------------------------------------------------------
        print("\n--- TEST 1: Check Horizontal Overflow (360, 390, 768, 1024, 1440) ---", flush=True)
        viewports = [360, 390, 768, 1024, 1440]
        for w in viewports:
            page.set_viewport_size({"width": w, "height": 800})
            page.wait_for_timeout(200)
            overflow_info = page.evaluate("""() => {
                const docWidth = document.documentElement.scrollWidth;
                const winWidth = window.innerWidth;
                return { docWidth, winWidth, hasOverflow: docWidth > winWidth };
            }""")
            print(f"Viewport {w}px: scrollWidth={overflow_info['docWidth']}, winWidth={overflow_info['winWidth']}, overflow={overflow_info['hasOverflow']}")
            assert not overflow_info["hasOverflow"], f"Horizontal overflow detected at {w}px!"
            test_results["overflow_tests"].append({"width": w, "passed": True})

        # Verify Dashboard KPI Cards
        kpi_cards = page.locator(".kpi-grid .kpi-card")
        kpi_count = kpi_cards.count()
        print(f"Verified {kpi_count} core KPI cards on Dashboard.")
        assert kpi_count >= 5, "Dashboard must render 5 core KPI cards!"
        test_results["dashboard_kpi"] = True

        # -------------------------------------------------------------
        # TEST 2: Round 5 Constraint: Search Viewport Resize & Focus Transfer
        # -------------------------------------------------------------
        print("\n--- TEST 2: Search Viewport Resize Focus Transfer ---")
        page.set_viewport_size({"width": 1440, "height": 900})
        page.wait_for_timeout(200)

        desktop_search = page.locator("#globalSearchInput")
        desktop_search.focus()
        desktop_search.fill("Vision")
        page.wait_for_timeout(400)

        dropdown = page.locator("#globalSearchResults")
        assert dropdown.is_visible(), "Search results dropdown should be visible on desktop!"
        print("Desktop search results dropdown opened successfully.")

        # Dynamically resize viewport to mobile (390px)
        page.set_viewport_size({"width": 390, "height": 844})
        page.wait_for_timeout(250)

        active_el_id = page.evaluate("() => document.activeElement ? document.activeElement.id : null")
        print(f"Active element after resize to 390px: {active_el_id}")
        assert active_el_id in ["globalSearchInputMobile", "globalSearchInput", "mobileSearchInput"], f"Focus lost after resize! Active element: {active_el_id}"

        # Resize back to desktop (1440px)
        page.set_viewport_size({"width": 1440, "height": 900})
        page.wait_for_timeout(250)
        active_el_desktop = page.evaluate("() => document.activeElement ? document.activeElement.id : null")
        print(f"Active element after resize back to 1440px: {active_el_desktop}")
        test_results["search_resize_focus"] = True
        print("PASS: Viewport resize focus preservation tested and validated!")

        # -------------------------------------------------------------
        # TEST 3: Vehicle Search Contract (name filter, ODO, string status)
        # -------------------------------------------------------------
        print("\n--- TEST 3: Vehicle Search Contract & Paginator ---")
        desktop_search.focus()
        desktop_search.fill("")
        page.wait_for_timeout(100)
        desktop_search.fill("Vision")
        page.wait_for_timeout(600)

        result_rows = page.locator(".search-result-row")
        row_count = result_rows.count()
        print(f"Total search result rows rendered with Paginator: {row_count}")

        # Check vehicle item
        vehicle_row = page.locator(".search-result-row:has-text('Honda Vision')").first
        assert vehicle_row.count() > 0, "Honda Vision must be present in search results!"
        vehicle_text = vehicle_row.inner_text()
        print(f"Rendered vehicle row text: {repr(vehicle_text)}")

        # Verify correct ODO and status mapping
        assert "ODO 12500km" in vehicle_text, f"Vehicle row must display ODO 12500km, got: {vehicle_text}"
        assert "Đang sửa" in vehicle_text, f"Vehicle with status 'repairing' must display 'Đang sửa', got: {vehicle_text}"
        test_results["paginated_search_compatible"] = True
        print("PASS: Vehicle search mapped name, odometer, and repairing status correctly!")

        # -------------------------------------------------------------
        # TEST 4: Vehicle Drawer Contract (biển số, status, ODO, focus trap)
        # -------------------------------------------------------------
        print("\n--- TEST 4: Vehicle Drawer Contract & Focus Trap ---")
        vehicle_row.click()
        page.locator(".slide-drawer.open").wait_for(timeout=5000)
        drawer = page.locator(".slide-drawer.open")
        page.wait_for_timeout(300)

        drawer_text = drawer.inner_text()
        print(f"Drawer inner text:\n{drawer_text}")
        assert "29B1-888.88" in drawer_text, "Drawer must display correct license 29B1-888.88!"
        assert "Đang sửa" in drawer_text, "Drawer must display status 'Đang sửa' for repairing vehicle!"
        assert "12500 km" in drawer_text, "Drawer must display correct odometer '12500 km'!"
        test_results["vehicle_drawer_contract"] = True

        # Ctrl+K Resistance while drawer is open
        print("Testing Ctrl+K while drawer is open...")
        page.keyboard.press("Control+k")
        page.wait_for_timeout(200)
        inside_drawer = page.evaluate("() => ({open: !!document.querySelector('.slide-drawer.open'), inside: !!document.activeElement.closest('.slide-drawer')})")
        print(f"Focus check after Ctrl+K: {inside_drawer}")
        assert inside_drawer["open"] and inside_drawer["inside"], "Ctrl+K must not escape drawer focus!"
        test_results["drawer_ctrl_k_resistant"] = True

        # Escape closes drawer and restores focus to search input
        page.keyboard.press("Escape")
        page.wait_for_timeout(300)
        assert not drawer.is_visible(), "Drawer must close on Escape!"
        escape_focus = page.evaluate("() => document.activeElement ? document.activeElement.id : null")
        print(f"Active element after Escape: {escape_focus}")
        assert escape_focus == "globalSearchInput", f"Focus must return to #globalSearchInput, got: {escape_focus}"
        test_results["drawer_accessibility"] = True
        print("PASS: Drawer contract, focus trap, Ctrl+K resistance, and Escape restore validated!")

        # -------------------------------------------------------------
        # TEST 5: Header "Tạo đơn" Button Handler
        # -------------------------------------------------------------
        print("\n--- TEST 5: Header 'Tạo đơn' Button Handler ---")
        quick_order_btn = page.locator(".header-right .btn-quick-order")
        assert quick_order_btn.is_visible(), "Header 'Tạo đơn' button must be visible!"
        quick_order_btn.click()
        page.wait_for_url("**/car-rental**", timeout=5000)
        print(f"Current page URL after clicking 'Tạo đơn': {page.url}")
        assert "/car-rental" in page.url, f"Header 'Tạo đơn' must navigate to /car-rental, got {page.url}!"
        test_results["header_create_button"] = True
        print("PASS: Header 'Tạo đơn' button navigates correctly!")

        # -------------------------------------------------------------
        # TEST 6: Admin 403 Forbidden Handling on Dashboard
        # -------------------------------------------------------------
        print("\n--- TEST 6: Admin 403 Forbidden Handling on Dashboard ---")
        api_state["deny_dashboard"] = True
        page.goto(f"{BASE_URL}dashboard", wait_until="domcontentloaded")
        page.wait_for_timeout(1200)

        unauthorized_card = page.locator(".himoto-unauthorized-container")
        assert unauthorized_card.is_visible(), "Admin receiving 403 must display .himoto-unauthorized-container!"
        kpi_count_on_403 = page.locator(".kpi-card").count()
        print(f"KPI cards count on 403 error: {kpi_count_on_403}")
        assert kpi_count_on_403 == 0, f"KPI cards must be 0 when 403 Forbidden occurs, got {kpi_count_on_403}!"
        test_results["admin_403_handling"] = True
        print("PASS: Admin 403 handled gracefully with zero runtime crashes and unauthorized card!")

        # -------------------------------------------------------------
        # TEST 7: Role 4 Gating and Redirect to /leads
        # -------------------------------------------------------------
        print("\n--- TEST 7: Role 4 Gating and Redirect to /leads ---")
        api_state["deny_dashboard"] = False
        api_state["role4"] = True
        page.goto(f"{BASE_URL}dashboard", wait_until="domcontentloaded")
        page.wait_for_timeout(1200)

        print(f"Role 4 landing URL: {page.url}")
        assert "/leads" in page.url, f"Role 4 must be redirected to /leads, got: {page.url}"
        assert page.locator(".kpi-card").count() == 0, "Role 4 must have 0 KPI cards!"

        # Sidebar shows only Lead nav
        lead_nav = page.locator(".sidebar-nav-item:has-text('Lead khách hàng')")
        assert lead_nav.first.is_visible(), "Lead menu must be visible for role 4!"
        admin_menu = page.locator(".sidebar-nav-item:has-text('Xe máy')")
        assert not admin_menu.is_visible(), "Admin menu 'Xe máy' must be hidden for role 4!"

        # Page errors check: ZERO errors
        print(f"Page errors recorded: {page_errors}")
        assert len(page_errors) == 0, f"Page threw unhandled runtime errors: {page_errors}"
        test_results["role_gating_and_redirect"] = True
        print("PASS: Role 4 gating, zero page errors, and lead redirect validated!")

        # Screenshot artifacts
        page.set_viewport_size({"width": 1440, "height": 900})
        page.screenshot(path="C:/Users/admin/.gemini/antigravity-ide/brain/8842cd63-0170-4db6-a65b-7629b93019a1/vue_integrated_desktop.png")
        page.set_viewport_size({"width": 390, "height": 844})
        page.screenshot(path="C:/Users/admin/.gemini/antigravity-ide/brain/8842cd63-0170-4db6-a65b-7629b93019a1/vue_integrated_mobile.png")
        browser.close()

    print("\n============================================================")
    print("ALL 7 HIMOTO INTEGRATION SUITE TESTS PASSED (100%)")
    print("============================================================")
    with open("C:/Users/admin/.gemini/antigravity-ide/brain/8842cd63-0170-4db6-a65b-7629b93019a1/integration_results.json", "w", encoding="utf-8") as f:
        json.dump(test_results, f, indent=2)

if __name__ == "__main__":
    run_himoto_integration_tests()
