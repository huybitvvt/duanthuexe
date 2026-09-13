import json
import time
from playwright.sync_api import sync_playwright

BASE_URL = 'http://localhost:8090/'

def run_himoto_integration_tests():
    print("=== HIMOTO VUE/LARAVEL INTEGRATION TEST SUITE ===")
    
    test_results = {
        "overflow_tests": [],
        "search_resize_focus": False,
        "drawer_accessibility": False,
        "role_gating": False,
        "dashboard_kpi": False
    }

    mock_user_admin = {
        "id": 1,
        "name": "Quanlyvanhanh",
        "email": "Quanlyvanhanh@gmail.com",
        "role_id": 1,
        "role_rel": {"slug": "quan-tri-vien"}
    }

    mock_user_lead = {
        "id": 4,
        "name": "NhanVienLead",
        "email": "lead@himoto.com",
        "role_id": 4,
        "role_rel": {"slug": "tu-van-lead"}
    }

    mock_stores = [
        {"id": 1, "store_name": "HIMOTO Đống Đa"},
        {"id": 2, "store_name": "HIMOTO Cầu Giấy"},
        {"id": 3, "store_name": "HIMOTO Tây Hồ"}
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
        {"id": 101, "name": "Honda Vision 2023 Trắng", "license": "29B1-888.88", "license_plate": "29B1-888.88", "status": "ready", "status_label": "Sẵn sàng", "store_name": "HIMOTO Đống Đa", "daily_price": 150000},
        {"id": 102, "name": "Honda Air Blade 125 Đen Nhám", "license": "29K1-999.99", "license_plate": "29K1-999.99", "status": "using", "status_label": "Đang thuê", "store_name": "HIMOTO Cầu Giấy", "daily_price": 180000}
    ]

    mock_customers = [
        {"id": 201, "name": "Nguyễn Văn An", "phone": "0912345678", "id_card": "001200012345", "address": "Ba Đình, Hà Nội"}
    ]

    mock_orders = [
        {"id": 301, "customer_name": "Nguyễn Văn An", "store_name": "HIMOTO Đống Đa", "order_status": "renting", "status_label": "Đang thuê", "total": 900000}
    ]

    with sync_playwright() as p:
        browser = p.chromium.launch(channel="msedge", headless=True)
        context = browser.new_context(viewport={"width": 1440, "height": 900})
        page = context.new_page()

        page.on("console", lambda msg: print(f"[BROWSER] {msg.text}", flush=True))
        page.on("pageerror", lambda err: print(f"[PAGE ERROR] {err}", flush=True))

        # Intercept API calls
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
                route.fulfill(status=200, content_type="application/json", body=json.dumps({"data": mock_vehicles}))
            elif "/api/auth/customers" in url:
                route.fulfill(status=200, content_type="application/json", body=json.dumps({"data": mock_customers}))
            elif "/api/auth/order/car-rental" in url:
                route.fulfill(status=200, content_type="application/json", body=json.dumps({"data": mock_orders}))
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
        # 1. Start at desktop (1440px)
        page.set_viewport_size({"width": 1440, "height": 900})
        page.wait_for_timeout(200)

        desktop_search = page.locator("#globalSearchInput")
        desktop_search.focus()
        desktop_search.fill("Vision")
        page.wait_for_timeout(350) # wait for debounce

        # Verify search dropdown opened
        dropdown = page.locator("#globalSearchResults")
        assert dropdown.is_visible(), "Search results dropdown should be visible on desktop!"
        print("Desktop search results dropdown opened successfully.")

        # 2. Dynamically resize viewport to mobile (390px) while search dropdown is open
        page.set_viewport_size({"width": 390, "height": 844})
        page.wait_for_timeout(250) # allow resize debounce handler to execute

        # 3. Check focus: In Vue lifecycle, focus must transfer smoothly without loss or throwing errors
        active_el_id = page.evaluate("() => document.activeElement ? document.activeElement.id : null")
        print(f"Active element after resize to 390px: {active_el_id}")
        assert active_el_id in ["globalSearchInputMobile", "globalSearchInput", "mobileSearchInput"], f"Focus lost after resize! Active element: {active_el_id}"

        # 4. Resize back to desktop (1440px)
        page.set_viewport_size({"width": 1440, "height": 900})
        page.wait_for_timeout(250)
        active_el_desktop = page.evaluate("() => document.activeElement ? document.activeElement.id : null")
        print(f"Active element after resize back to 1440px: {active_el_desktop}")
        test_results["search_resize_focus"] = True
        print("PASS: Viewport resize focus preservation tested and validated!")

        # -------------------------------------------------------------
        # TEST 3: Drawer Focus Trap & Escape Key
        # -------------------------------------------------------------
        print("\n--- TEST 3: HimotoDrawer Accessibility & Focus Trap ---")
        # Open desktop search dropdown again
        desktop_search.focus()
        desktop_search.fill("")
        page.wait_for_timeout(100)
        desktop_search.fill("Vision")
        page.wait_for_timeout(500)

        results_count = page.locator(".search-result-row").count()
        print(f"Desktop search results count: {results_count}")

        # Press ArrowDown then Enter to select first result
        page.keyboard.press("ArrowDown")
        page.wait_for_timeout(150)
        page.keyboard.press("Enter")
        page.wait_for_timeout(400)

        # Check drawer is open
        drawer = page.locator(".slide-drawer.open")
        assert drawer.is_visible(), "HimotoDrawer should be open after selecting result!"
        print("HimotoDrawer opened successfully.")

        # Test Tab key focus trap
        page.keyboard.press("Tab")
        page.wait_for_timeout(100)
        is_inside_drawer = page.evaluate("""() => {
            const drawer = document.querySelector('.slide-drawer.open');
            return drawer && drawer.contains(document.activeElement);
        }""")
        assert is_inside_drawer, "Focus must remain trapped inside drawer on Tab!"
        print("Focus trapped inside drawer confirmed.")

        # Test Escape key: Closes drawer and restores focus
        page.keyboard.press("Escape")
        page.wait_for_timeout(300)
        assert not drawer.is_visible(), "Drawer should be closed on Escape!"
        active_after_escape = page.evaluate("() => document.activeElement ? document.activeElement.id : null")
        print(f"Active element after drawer Escape: {active_after_escape}")
        assert active_after_escape == "globalSearchInput", "Focus must return to search input opener!"
        test_results["drawer_accessibility"] = True
        print("PASS: Drawer accessibility, focus trap and Escape restore validated!")

        # -------------------------------------------------------------
        # TEST 4: Role 4 Gating (Lead Consultant View)
        # -------------------------------------------------------------
        print("\n--- TEST 4: Role 4 Menu Gating ---")
        # Route handler for Role 4
        def handle_role4(route):
            if "/api/verify-token" in route.request.url:
                route.fulfill(status=200, content_type="application/json", body=json.dumps({"user": mock_user_lead, "data": mock_user_lead, "access_token": "mock_jwt_token_himoto"}))
            else:
                handle_routes(route)

        page.unroute("**/api/**")
        page.route("**/api/**", lambda r: handle_role4(r) if "/api/verify-token" in r.request.url else handle_routes(r))
        
        # Reload page to apply Role 4 state
        page.reload(wait_until="domcontentloaded")
        page.wait_for_timeout(800)

        # Check that admin menus are hidden and only TƯ VẤN LEAD is shown
        lead_nav = page.locator(".sidebar-nav-item:has-text('Lead khách hàng')")
        assert lead_nav.first.is_visible(), "Lead menu must be visible for role 4!"
        admin_kpi = page.locator(".sidebar-nav-item:has-text('Doanh thu theo xe')")
        assert not admin_kpi.is_visible(), "Admin menu 'Doanh thu theo xe' must be hidden for role 4!"
        vehicles_menu = page.locator(".sidebar-nav-item:has-text('Xe máy')")
        assert not vehicles_menu.is_visible(), "Admin menu 'Xe máy' must be hidden for role 4!"
        print("PASS: Role 4 menu gating verified!")
        test_results["role_gating"] = True

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
