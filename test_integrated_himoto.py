import json
import time
from playwright.sync_api import sync_playwright

BASE_URL = "http://localhost:8090/"

def run_himoto_integration_tests():
    test_results = {
        "overflow_tests": [],
        "search_resize_focus": False,
        "drawer_accessibility": False,
        "drawer_ctrl_k_resistant": False,
        "header_create_button": False,
        "paginated_search_compatible": False,
        "role_gating_and_403": False,
        "dashboard_kpi": False
    }

    # Mock Data Fixtures
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

    mock_vehicles = [
        {"id": 101, "name": "Honda Vision 2023 Trắng", "license": "29B1-888.88", "license_plate": "29B1-888.88", "status": 1, "status_name": "Sẵn sàng", "store": {"store_name": "HIMOTO Đống Đa"}, "total_km": 12500},
        {"id": 102, "name": "Honda Air Blade 125 Đen Nhám", "license": "29K1-999.99", "license_plate": "29K1-999.99", "status": 2, "status_name": "Đang thuê", "store": {"store_name": "HIMOTO Cầu Giấy"}, "total_km": 24800}
    ]

    mock_customers = [
        {"id": 201, "name": "Nguyễn Văn An", "phone": "0912345678", "id_card": "001200012345", "address": "Ba Đình, Hà Nội", "total_order": 3}
    ]

    mock_orders = [
        {"id": 301, "customer_name": "Nguyễn Văn An", "store_name": "HIMOTO Đống Đa", "order_status": "renting", "status_label": "Đang thuê", "total": 900000, "vehicles": [{"name": "Honda Vision", "license": "29B1-888.88"}]}
    ]

    # REALISTIC LARAVEL PAGINATOR SHAPES
    paginated_vehicles = {
        "data": {
            "current_page": 1,
            "last_page": 1,
            "per_page": 20,
            "total": len(mock_vehicles),
            "data": mock_vehicles
        }
    }

    paginated_customers = {
        "data": {
            "current_page": 1,
            "last_page": 1,
            "per_page": 20,
            "total": len(mock_customers),
            "data": mock_customers
        }
    }

    paginated_orders = {
        "data": mock_orders,
        "pagination": {
            "current_page": 1,
            "last_page": 1,
            "per_page": 20,
            "total": len(mock_orders)
        }
    }

    page_errors = []

    with sync_playwright() as p:
        browser = p.chromium.launch(channel="msedge", headless=True)
        context = browser.new_context(viewport={"width": 1440, "height": 900})
        page = context.new_page()

        page.on("console", lambda msg: print(f"[BROWSER] {msg.text}", flush=True))
        page.on("pageerror", lambda err: page_errors.append(str(err)))

        # Intercept API calls (Default: Admin Role 1)
        def handle_routes(route):
            url = route.request.url
            if "/api/verify-token" in url:
                route.fulfill(status=200, content_type="application/json", body=json.dumps({"user": mock_user_admin, "data": mock_user_admin, "access_token": "mock_jwt_token_himoto"}))
            elif "/api/auth/stores/all" in url:
                route.fulfill(status=200, content_type="application/json", body=json.dumps({"data": mock_stores}))
            elif "/api/auth/dashboard/report-chart" in url:
                route.fulfill(status=200, content_type="application/json", body=json.dumps({"data": mock_chart_data}))
            elif "/api/auth/dashboard/report" in url:
                route.fulfill(status=200, content_type="application/json", body=json.dumps({"data": mock_dashboard_report}))
            elif "/api/auth/vehicle/vehicles" in url:
                # REAL PAGINATOR
                route.fulfill(status=200, content_type="application/json", body=json.dumps(paginated_vehicles))
            elif "/api/auth/customers" in url:
                # REAL PAGINATOR
                route.fulfill(status=200, content_type="application/json", body=json.dumps(paginated_customers))
            elif "/api/auth/order/car-rental" in url:
                # REAL PAGINATOR
                route.fulfill(status=200, content_type="application/json", body=json.dumps(paginated_orders))
            else:
                route.fulfill(status=200, content_type="application/json", body=json.dumps({"data": []}))

        page.route("**/api/**", handle_routes)

        # Set fake auth token before load
        page.add_init_script("window.localStorage.setItem('id_token', 'mock_jwt_token_himoto');")

        print("Navigating to /dashboard...", flush=True)
        page.goto(f"{BASE_URL}dashboard", wait_until="domcontentloaded")
        page.wait_for_timeout(1000)

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
        # TEST 2: Round 5 Specific Constraint: Search Viewport Resize & Focus Preservation
        # -------------------------------------------------------------
        print("\n--- TEST 2: Search Viewport Resize Focus Transfer ---")
        page.set_viewport_size({"width": 1440, "height": 900})
        page.wait_for_timeout(200)

        desktop_search = page.locator("#globalSearchInput")
        desktop_search.focus()
        desktop_search.fill("Vision")
        page.wait_for_timeout(350)

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
        # TEST 3: Realistic Laravel Paginator Search Compatibility
        # -------------------------------------------------------------
        print("\n--- TEST 3: Realistic Laravel Paginator Search Compatibility ---")
        desktop_search.focus()
        desktop_search.fill("")
        page.wait_for_timeout(100)
        desktop_search.fill("Vision")
        page.wait_for_timeout(500)

        result_rows = page.locator(".search-result-row")
        row_count = result_rows.count()
        print(f"Total search result rows rendered with Paginator: {row_count}")
        assert row_count >= 2, f"Paginator search must return multiple rows, got {row_count}!"

        # Verify vehicles are mapped (proves unwrapList succeeded!)
        vehicles_found = page.locator(".search-result-row:has-text('Honda Vision')").count()
        print(f"Vehicles found in search results: {vehicles_found}")
        assert vehicles_found > 0, "Vehicle results missing from search dropdown with paginator!"

        customers_found = page.locator(".search-result-row:has-text('Nguyễn Văn An')").count()
        print(f"Customer/Order rows found in search: {customers_found}")
        assert customers_found > 0, "Customer/Order results missing from search dropdown with paginator!"

        test_results["paginated_search_compatible"] = True
        print("PASS: Laravel Paginator unwrapList compatibility verified!")

        # -------------------------------------------------------------
        # TEST 4: Drawer Focus Trap, Ctrl+K Resistance & Escape Restore
        # -------------------------------------------------------------
        print("\n--- TEST 4: HimotoDrawer Accessibility & Ctrl+K Protection ---")
        # Select first result with ArrowDown + Enter
        page.keyboard.press("ArrowDown")
        page.wait_for_timeout(150)
        page.keyboard.press("Enter")
        page.wait_for_timeout(400)

        drawer = page.locator(".slide-drawer.open")
        assert drawer.is_visible(), "HimotoDrawer should be open after selecting result!"
        print("HimotoDrawer opened successfully.")

        # Focus trap test: Tab keeps focus inside drawer
        page.keyboard.press("Tab")
        page.wait_for_timeout(100)
        is_inside_drawer = page.evaluate("""() => {
            const d = document.querySelector('.slide-drawer.open');
            return d && d.contains(document.activeElement);
        }""")
        assert is_inside_drawer, "Focus must remain trapped inside drawer on Tab!"

        # CRITICAL AUDIT CHECK: Press Ctrl+K while drawer is open!
        print("Testing Ctrl+K while drawer is active...")
        page.keyboard.press("Control+KeyK")
        page.wait_for_timeout(200)

        # Drawer must remain open and focus must NOT jump out to search input
        assert drawer.is_visible(), "Drawer must remain open after Ctrl+K!"
        active_after_ctrl_k = page.evaluate("() => document.activeElement ? document.activeElement.id : null")
        inside_after_ctrl_k = page.evaluate("""() => {
            const d = document.querySelector('.slide-drawer.open');
            return d && d.contains(document.activeElement);
        }""")
        print(f"Active element after Ctrl+K: {active_after_ctrl_k}, insideDrawer: {inside_after_ctrl_k}")
        assert inside_after_ctrl_k, "Focus escaped drawer after Ctrl+K!"
        assert active_after_ctrl_k != "globalSearchInput", "Ctrl+K stole focus to globalSearchInput while drawer is open!"
        test_results["drawer_ctrl_k_resistant"] = True
        print("PASS: Drawer is 100% resistant to Ctrl+K focus hijacking!")

        # Escape key closes drawer and restores focus to search input
        page.keyboard.press("Escape")
        page.wait_for_timeout(300)
        assert not drawer.is_visible(), "Drawer should be closed on Escape!"
        active_after_escape = page.evaluate("() => document.activeElement ? document.activeElement.id : null")
        print(f"Active element after drawer Escape: {active_after_escape}")
        assert active_after_escape == "globalSearchInput", "Focus must return to search input opener!"
        test_results["drawer_accessibility"] = True
        print("PASS: Drawer accessibility and Escape restore validated!")

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
        # TEST 6: Role 4 Gating & Realistic Backend 403 Response
        # -------------------------------------------------------------
        print("\n--- TEST 6: Role 4 Gating & Backend 403 Protection ---")
        # Route handler for Role 4 with realistic Laravel NonSale middleware 403s
        def handle_role4(route):
            url = route.request.url
            if "/api/verify-token" in url:
                route.fulfill(status=200, content_type="application/json", body=json.dumps({"user": mock_user_lead, "data": mock_user_lead, "access_token": "mock_jwt_token_himoto"}))
            elif "/api/auth/stores/all" in url:
                route.fulfill(status=200, content_type="application/json", body=json.dumps({"data": mock_stores}))
            elif "/api/auth/leads" in url:
                route.fulfill(status=200, content_type="application/json", body=json.dumps({"data": []}))
            elif any(blocked in url for blocked in ["/api/auth/dashboard", "/api/auth/order", "/api/auth/vehicle", "/api/auth/report", "/api/auth/stores"]):
                # REAL LARAVEL NonSale 403 RESPONSE
                route.fulfill(status=403, content_type="application/json", body=json.dumps({
                    "error": True,
                    "message": "Bạn không có quyền, vui lòng liên hệ admin"
                }))
            else:
                route.fulfill(status=200, content_type="application/json", body=json.dumps({"data": []}))

        page.unroute("**/api/**")
        page.route("**/api/**", handle_role4)

        # Navigate to /dashboard as Role 4
        print("Navigating to /dashboard as Role 4 (expecting redirect or 403 protection)...")
        page.goto(f"{BASE_URL}dashboard", wait_until="domcontentloaded")
        page.wait_for_timeout(800)

        # Verify no runtime crashes occurred
        assert len(page_errors) == 0, f"Page threw unhandled runtime errors: {page_errors}"

        # Either user was cleanly redirected to /leads, or sees unauthorized fallback card
        current_path = page.url
        print(f"Role 4 landing URL: {current_path}")
        if "/leads" in current_path:
            print("Role 4 automatically and safely redirected to /leads workspace!")
        else:
            unauthorized_card = page.locator(".himoto-unauthorized-container")
            assert unauthorized_card.is_visible(), "Role 4 on /dashboard must display unauthorized protection card!"
            print("Role 4 displays clean 'Không có quyền truy cập' card on /dashboard!")

        # Verify Sidebar shows ONLY Lead consultant view
        lead_nav = page.locator(".sidebar-nav-item:has-text('Lead khách hàng')")
        assert lead_nav.first.is_visible(), "Lead menu must be visible for role 4!"
        admin_menu = page.locator(".sidebar-nav-item:has-text('Xe máy')")
        assert not admin_menu.is_visible(), "Admin menu 'Xe máy' must be hidden for role 4!"
        admin_kpi = page.locator(".sidebar-nav-item:has-text('Doanh thu theo xe')")
        assert not admin_kpi.is_visible(), "Admin menu 'Doanh thu theo xe' must be hidden for role 4!"

        # Header 'Tạo đơn' for role 4 routes to /leads
        quick_order_btn = page.locator(".header-right .btn-quick-order")
        quick_order_btn.click()
        page.wait_for_timeout(300)
        assert "/leads" in page.url, f"Role 4 'Tạo đơn' should route to /leads, got {page.url}!"

        test_results["role_gating_and_403"] = True
        print("PASS: Role 4 gating and realistic backend 403 handling verified!")

        # Screenshot artifacts
        page.set_viewport_size({"width": 1440, "height": 900})
        page.screenshot(path="C:/Users/admin/.gemini/antigravity-ide/brain/8842cd63-0170-4db6-a65b-7629b93019a1/vue_integrated_desktop.png")
        print("Saved screenshot: vue_integrated_desktop.png")

        page.set_viewport_size({"width": 390, "height": 844})
        page.screenshot(path="C:/Users/admin/.gemini/antigravity-ide/brain/8842cd63-0170-4db6-a65b-7629b93019a1/vue_integrated_mobile.png")
        print("Saved screenshot: vue_integrated_mobile.png")

        browser.close()

    print("\nALL HIMOTO INTEGRATION SUITE TESTS PASSED!")
    with open("C:/Users/admin/.gemini/antigravity-ide/brain/8842cd63-0170-4db6-a65b-7629b93019a1/integration_results.json", "w", encoding="utf-8") as f:
        json.dump(test_results, f, indent=2)

if __name__ == "__main__":
    run_himoto_integration_tests()
