"""Comprehensive In-Repo Automated Audit for HIMOTO Fleet Dashboard.
Verifies:
1. True SPA menu switching without page reload (window.__himoto_shell_id check, 0 document requests).
2. Correct API endpoints (/api/auth/stores/all, /api/auth/leads, etc.).
3. Business workflows for Customer, Order, Lead, Maintenance, Cash, Bank.
4. Cache TTL & keep-alive request count preservation.
5. Error state (500) with retry and 0 unhandled promise rejections.
6. Responsive across 5 viewports without horizontal scroll.
7. Strict exit code: Exits with code 1 if ANY check fails.
"""
import argparse
import json
import sys
import time
from pathlib import Path
from urllib.parse import urlparse, parse_qs
from playwright.sync_api import sync_playwright

DEFAULT_OUT = Path(__file__).parent
DEFAULT_BASE_URL = "http://localhost:8091/"

mock_user_admin = {
    "id": 1,
    "name": "Quản Trị Viên HIMOTO",
    "email": "admin@himoto.vn",
    "role_id": 1,
    "store_id": 1,
    "role_rel": {"slug": "quan-tri-vien", "name": "Quản trị viên"}
}

mock_stores = [
    {"id": "all", "store_name": "Toàn hệ thống"},
    {"id": 1, "store_name": "HIMOTO Đống Đa"},
    {"id": 2, "store_name": "HIMOTO Cầu Giấy"}
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
    {"id": 101, "name": "Honda Vision 2023", "license": "29B1-888.88", "status": "repairing", "odometer": 12500, "store": {"id": 1, "store_name": "HIMOTO Dong Da"}},
    {"id": 102, "name": "Honda Air Blade 125", "license": "29K1-999.99", "status": "ready", "odometer": 24800, "store": {"id": 2, "store_name": "HIMOTO Cau Giay"}}
]

mock_customers = [
    {"id": 201, "name": "Nguyễn Văn An", "phone": "0912345678", "id_card": "001200012345", "address": "Ba Đình, Hà Nội", "total_order": 3, "gender": 1, "birthday": "1990-05-15"}
]

mock_orders = [
    {
        "id": 301,
        "customer_name": "Nguyễn Văn An",
        "customer": {"name": "Nguyễn Văn An", "phone": "0912345678"},
        "store_name": "HIMOTO Đống Đa",
        "order_status": "renting",
        "status_label": "Đang thuê",
        "rent_at": "2026-09-10 09:00:00",
        "return_at": "2026-09-20 09:00:00",
        "total": 900000,
        "first_deposit_amount": 1000000,
        "additional_deposit_amount": 0,
        "deposit_amount": 1000000,
        "vehicles": [{"name": "Honda Vision 2023", "license": "29B1-888.88"}]
    }
]

mock_leads = [
    {
        "id": 401,
        "customer_name": "Trần Thị Mai",
        "customer_phone": "0987654321",
        "name": "Trần Thị Mai",
        "phone": "0987654321",
        "status": "pending",
        "source": "Facebook Ads",
        "note": "Hỏi thuê xe Air Blade 1 tuần",
        "store_id": 1,
        "created_at": "2026-09-14 07:00:00"
    }
]

mock_schedules = [
    {
        "id": 501,
        "vehicle": {"name": "Honda Vision 2023", "license": "29B1-888.88"},
        "maintenance_type": {"name": "Thay dầu máy định kỳ"},
        "next_time_manual": "2026-09-25 10:00:00",
        "note": "Bảo dưỡng 15,000km",
        "status": "pending"
    }
]

mock_cashes = [
    {
        "id": 601,
        "store_name": "HIMOTO Đống Đa",
        "opening_balance": 10000000,
        "current_balance": 15000000,
        "transactions": []
    }
]

mock_banks = [
    {
        "id": 701,
        "bank_name": "Techcombank",
        "account_number": "19031234567890",
        "account_name": "CTCP HIMOTO VIETNAM",
        "balance": 125000000,
        "store": {"store_name": "Toàn hệ thống"},
        "transactions": []
    }
]

def run_comprehensive_audit(base_url=DEFAULT_BASE_URL, output_dir=DEFAULT_OUT):
    results = {
        "suite_completed": False,
        "spa_no_reload": {},
        "api_endpoints_verified": {},
        "module_workflows": {},
        "cache_ttl_and_request_counts": {},
        "error_state_handling": {},
        "responsive_viewports": {},
        "page_errors": []
    }

    requests_log = []
    page_errors = []
    document_requests = []
    simulate_500 = {"customer": False}

    output_path = Path(output_dir)
    output_path.mkdir(parents=True, exist_ok=True)

    with sync_playwright() as p:
        browser = p.chromium.launch(channel="msedge", headless=True)
        context = browser.new_context(viewport={"width": 1440, "height": 900})
        page = context.new_page()

        try:
            page.on("pageerror", lambda err: page_errors.append(str(err)))
            page.on("request", lambda req: document_requests.append(req.url) if req.resource_type == "document" else None)

            def handle_api(route):
                u = urlparse(route.request.url)
                q = parse_qs(u.query)
                requests_log.append({"path": u.path, "query": q})
                status, body = 200, {"data": []}

                if "verify-token" in u.path:
                    body = {"user": mock_user_admin, "data": mock_user_admin, "access_token": "valid-himoto-token"}
                elif u.path.endswith("/stores/all"):
                    body = {"data": mock_stores}
                elif u.path.endswith("/dashboard/report"):
                    body = {"data": mock_dashboard_report}
                elif u.path.endswith("/dashboard/report-chart"):
                    body = {"data": mock_chart_data}
                elif u.path.endswith("/vehicle/vehicles"):
                    needle = q.get("name", q.get("keyword", [""]))[0].lower()
                    rows = [v for v in mock_vehicles if needle in (v["name"] + " " + v["license"]).lower()] if needle else mock_vehicles
                    body = {"data": {"current_page": 1, "last_page": 1, "per_page": 20, "total": len(rows), "data": rows}}
                elif u.path.endswith("/vehicle/vehicles/report"):
                    body = {"data": {"total": len(mock_vehicles), "ready": 1, "using": 0, "repairing": 1}}
                elif u.path.endswith("/customers"):
                    if simulate_500["customer"]:
                        status, body = 500, {"message": "Simulated Internal Server Error"}
                    else:
                        needle = q.get("keyword", [""])[0].lower()
                        rows = [c for c in mock_customers if needle in (c["name"] + " " + c["phone"]).lower()] if needle else mock_customers
                        body = {"data": {"current_page": 1, "last_page": 1, "per_page": 20, "total": len(rows), "data": rows}}
                elif u.path.endswith("/order/car-rental"):
                    needle = q.get("keyword", [""])[0].lower()
                    rows = [o for o in mock_orders if needle in (o["customer_name"] + " " + o["vehicles"][0]["name"]).lower()] if needle else mock_orders
                    body = {"data": rows, "pagination": {"current_page": 1, "last_page": 1, "per_page": 20, "total": len(rows)}}
                elif u.path.endswith("/order/car-rental/report"):
                    body = {"data": {"renting": 1, "out_date": 0, "completed": 0}}
                elif u.path.endswith("/order/car-rental/report-new"):
                    body = {"data": {"profit_hiring_fee": 900000, "addon": 0, "money_out_date": 0, "money_out_date_early": 0, "total_deposit": 1000000, "total_renew": 0, "total_rental_fees": 900000}}
                elif "/order/car-rental/" in u.path:
                    body = {"data": mock_orders[0]}
                elif u.path.endswith("/leads/unique-users"):
                    body = {"data": [{"id": 1, "name": "Facebook Ads"}, {"id": 2, "name": "Zalo OA"}]}
                elif u.path.endswith("/leads"):
                    body = {"data": {"current_page": 1, "last_page": 1, "per_page": 20, "total": len(mock_leads), "data": mock_leads}}
                elif u.path.endswith("/maintenance-schedules"):
                    body = {"data": {"current_page": 1, "last_page": 1, "per_page": 20, "total": len(mock_schedules), "data": mock_schedules}}
                elif u.path.endswith("/cashes") or u.path.endswith("/cash"):
                    body = {"data": {"current_page": 1, "last_page": 1, "data": mock_cashes}}
                elif u.path.endswith("/banks"):
                    body = {"data": {"current_page": 1, "last_page": 1, "data": mock_banks}}
                elif u.path.endswith("/role/all"):
                    body = {"data": [{"id": 1, "name": "Quản trị viên"}, {"id": 4, "name": "Tư vấn Lead"}]}

                route.fulfill(status=status, content_type="application/json", body=json.dumps(body))

            page.route("**/api/**", handle_api)
            page.add_init_script("window.localStorage.setItem('id_token', 'valid-himoto-token');")

            print("[1] Initial Page Load -> /dashboard")
            document_requests.clear()
            page.goto(f"{base_url}dashboard", wait_until="domcontentloaded")
            page.locator("#globalSearchInput").wait_for(timeout=10000)
            page.wait_for_timeout(600)

            # Mark Shell Identity
            shell_token = f"himoto-shell-token-{int(time.time()*1000)}"
            page.evaluate(f"window.__himoto_shell_id = '{shell_token}';")
            initial_shell_check = page.evaluate("window.__himoto_shell_id")
            assert initial_shell_check == shell_token, "Shell token failed to register"
            initial_doc_reqs = len(document_requests)
            print(f"    Shell registered with ID: {shell_token}. Document requests: {initial_doc_reqs}")

            # --- SECTION 1: SPA MENU NAVIGATION WITHOUT RELOAD ---
            print("\n[2] Testing Menu Switching via Sidebar Navigation (NO page reload)")
            menu_checks = [
                {"name": "Orders", "path": "/car-rental", "selector": 'a.sidebar-nav-item[href*="car-rental"]'},
                {"name": "Vehicles", "path": "/vehicles", "selector": 'a.sidebar-nav-item[href*="vehicles"]'},
                {"name": "Customers", "path": "/customers", "selector": 'a.sidebar-nav-item[href*="customers"]'},
                {"name": "Leads", "path": "/leads", "selector": 'a.sidebar-nav-item[href*="leads"]'},
                {"name": "Maintenance", "path": "/maintenance-schedule", "selector": 'a.sidebar-nav-item[href*="maintenance-schedule"]'},
                {"name": "Cash", "path": "/cash", "selector": 'a.sidebar-nav-item[href*="cash"]'},
                {"name": "Banks", "path": "/banks", "selector": 'a.sidebar-nav-item[href*="banks"]'}
            ]

            spa_transitions = []
            for menu in menu_checks:
                link = page.locator(menu["selector"]).first
                link.wait_for(state="visible", timeout=5000)
                link.click()

                try:
                    page.wait_for_url(f"*{menu['path']}*", timeout=5000)
                except Exception:
                    page.wait_for_timeout(800)

                page.wait_for_timeout(300)
                cur_url = page.url
                active_shell_id = page.evaluate("window.__himoto_shell_id")
                doc_reqs_now = len(document_requests)

                is_intact = (active_shell_id == shell_token)
                no_doc_reload = (doc_reqs_now == initial_doc_reqs)
                has_path = menu["path"] in cur_url

                spa_transitions.append({
                    "menu": menu["name"],
                    "target_path": menu["path"],
                    "current_url": cur_url,
                    "shell_intact": is_intact,
                    "zero_document_reloads": no_doc_reload,
                    "status": "PASS" if (is_intact and no_doc_reload and has_path) else "FAIL"
                })
                print(f"    Transition -> {menu['name']}: shell_intact={is_intact}, doc_reqs={doc_reqs_now} (Expected {initial_doc_reqs}), url={cur_url}")

            # Test Back / Forward history in SPA
            page.go_back()
            page.wait_for_timeout(400)
            back_shell = page.evaluate("window.__himoto_shell_id")
            page.go_forward()
            page.wait_for_timeout(400)
            fwd_shell = page.evaluate("window.__himoto_shell_id")

            results["spa_no_reload"] = {
                "transitions": spa_transitions,
                "all_transitions_passed": all(t["status"] == "PASS" for t in spa_transitions),
                "back_forward_verified": (back_shell == shell_token and fwd_shell == shell_token),
                "total_document_requests_during_all_menu_switching": len(document_requests) - initial_doc_reqs
            }

            # --- SECTION 2: VERIFY REAL BACKEND API ENDPOINTS ---
            print("\n[3] Verifying Backend API Contract Endpoints")
            stores_called = any(r["path"] == "/api/auth/stores/all" for r in requests_log)
            leads_called = any(r["path"] == "/api/auth/leads" for r in requests_log)
            lead_users_called = any(r["path"] == "/api/auth/leads/unique-users" for r in requests_log)
            customers_called = any(r["path"] == "/api/auth/customers" for r in requests_log)
            orders_called = any(r["path"] == "/api/auth/order/car-rental" for r in requests_log)
            vehicles_called = any(r["path"] == "/api/auth/vehicle/vehicles" for r in requests_log)
            maintenance_called = any(r["path"] == "/api/auth/maintenance-schedules" for r in requests_log)

            wrong_store = any(r["path"] == "/api/auth/store/all" or r["path"] == "/store/all" for r in requests_log)
            wrong_lead = any(r["path"] == "/api/auth/lead" or r["path"] == "/lead" for r in requests_log)

            results["api_endpoints_verified"] = {
                "/api/auth/stores/all": stores_called,
                "/api/auth/leads": leads_called,
                "/api/auth/leads/unique-users": lead_users_called,
                "/api/auth/customers": customers_called,
                "/api/auth/order/car-rental": orders_called,
                "/api/auth/vehicle/vehicles": vehicles_called,
                "/api/auth/maintenance-schedules": maintenance_called,
                "no_wrong_store_endpoint": not wrong_store,
                "no_wrong_lead_endpoint": not wrong_lead,
                "all_endpoints_valid": (stores_called and leads_called and not wrong_store and not wrong_lead)
            }
            print(f"    Stores endpoint (/api/auth/stores/all): {stores_called}")
            print(f"    Leads endpoint (/api/auth/leads): {leads_called}")
            print(f"    No invalid legacy endpoints: {not wrong_store and not wrong_lead}")

            # --- SECTION 3: BUSINESS MODULE WORKFLOWS ---
            print("\n[4] Verifying Business Module Workflows")
            page.locator('a.sidebar-nav-item[href*="customers"]').first.click()
            page.wait_for_timeout(600)
            cust_row = page.locator("table tbody tr").first
            cust_text = cust_row.inner_text() if cust_row.count() > 0 else ""
            cust_has_data = "Nguyễn Văn An" in cust_text or "0912345678" in cust_text

            page.locator('a.sidebar-nav-item[href*="car-rental"]').first.click()
            page.wait_for_timeout(600)
            order_row = page.locator("table tbody tr").first
            order_text = order_row.inner_text() if order_row.count() > 0 else ""
            order_has_data = "Nguyễn Văn An" in order_text or "Đang thuê" in order_text

            page.locator('a.sidebar-nav-item[href*="leads"]').first.click()
            page.wait_for_timeout(600)
            lead_row = page.locator("table tbody tr").first
            lead_text = lead_row.inner_text() if lead_row.count() > 0 else ""
            lead_has_data = "Trần Thị Mai" in lead_text or "0987654321" in lead_text

            page.locator('a.sidebar-nav-item[href*="maintenance-schedule"]').first.click()
            page.wait_for_timeout(600)
            maint_row = page.locator("table tbody tr").first
            maint_text = maint_row.inner_text() if maint_row.count() > 0 else ""
            maint_has_data = "Honda Vision" in maint_text or "Thay dầu" in maint_text

            page.locator('a.sidebar-nav-item[href*="cash"]').first.click()
            page.wait_for_timeout(600)
            cash_row = page.locator("table tbody tr").first
            cash_text = cash_row.inner_text() if cash_row.count() > 0 else ""
            cash_has_data = "HIMOTO Đống Đa" in cash_text or "15.000.000" in cash_text or "10.000.000" in cash_text

            page.locator('a.sidebar-nav-item[href*="banks"]').first.click()
            page.wait_for_timeout(600)
            bank_row = page.locator("table tbody tr").first
            bank_text = bank_row.inner_text() if bank_row.count() > 0 else ""
            bank_has_data = "Techcombank" in bank_text or "125.000.000" in bank_text or "19031234567890" in bank_text

            results["module_workflows"] = {
                "customer_renders_correctly": cust_has_data,
                "order_renders_correctly": order_has_data,
                "lead_renders_correctly": lead_has_data,
                "maintenance_renders_correctly": maint_has_data,
                "cash_renders_correctly": cash_has_data,
                "bank_renders_correctly": bank_has_data,
                "all_modules_functional": all([cust_has_data, order_has_data, lead_has_data, maint_has_data, cash_has_data, bank_has_data])
            }
            print(f"    Customer render: {cust_has_data}")
            print(f"    Order render: {order_has_data}")
            print(f"    Lead render: {lead_has_data}")
            print(f"    Maintenance render: {maint_has_data}")
            print(f"    Cash render: {cash_has_data}")
            print(f"    Bank render: {bank_has_data}")

            # --- SECTION 4: CACHE TTL & KEEP-ALIVE VERIFICATION ---
            print("\n[5] Testing Cache TTL & Keep-Alive Request Counting")
            page.locator('a.sidebar-nav-item[href*="car-rental"]').first.click()
            page.wait_for_timeout(500)
            order_req_count_before = len([r for r in requests_log if r["path"] == "/api/auth/order/car-rental"])

            # Switch to Vehicles
            page.locator('a.sidebar-nav-item[href*="vehicles"]').first.click()
            page.wait_for_timeout(500)

            # Switch back to Orders within 5 seconds (TTL is 60s)
            page.locator('a.sidebar-nav-item[href*="car-rental"]').first.click()
            page.wait_for_timeout(500)
            order_req_count_after = len([r for r in requests_log if r["path"] == "/api/auth/order/car-rental"])

            ttl_cache_honored = (order_req_count_after == order_req_count_before)
            results["cache_ttl_and_request_counts"] = {
                "order_requests_before_switch": order_req_count_before,
                "order_requests_after_switch_back_within_ttl": order_req_count_after,
                "cache_preserved_no_redundant_request": ttl_cache_honored
            }
            print(f"    Orders request count before switch: {order_req_count_before}, after switch back: {order_req_count_after}")
            print(f"    Keep-Alive TTL Cache honored: {ttl_cache_honored}")

            # --- SECTION 5: ERROR STATE (500) HANDLING & ZERO UNHANDLED REJECTIONS ---
            print("\n[6] Testing HTTP 500 Error State & Catch Resilience")
            simulate_500["customer"] = True
            page.locator('a.sidebar-nav-item[href*="customers"]').first.click()
            page.wait_for_timeout(300)
            search_input = page.locator("input.el-input__inner").first
            if search_input.count() > 0:
                search_input.fill("test500")
                btn_search = page.locator(".card-header button.btn-primary").first
                if btn_search.count() > 0:
                    btn_search.click()
                else:
                    search_input.press("Enter")
            page.wait_for_timeout(800)

            error_container = page.locator(".himoto-error-state")
            error_has_message = error_container.count() > 0
            retry_button = page.locator(".himoto-error-state .error-actions button")
            error_has_retry = retry_button.count() > 0

            recovered_after_retry = False
            if error_has_retry:
                simulate_500["customer"] = False
                page.evaluate("() => { const input = document.querySelector('input.el-input__inner'); if (input) { input.value = ''; input.dispatchEvent(new Event('input')); } }")
                retry_button.first.click()
                page.wait_for_timeout(800)
                recovered_after_retry = (page.locator("table tbody tr").count() > 0 or page.locator(".himoto-empty-state").count() > 0)

            results["error_state_handling"] = {
                "error_state_rendered_on_500": error_has_message,
                "retry_button_available": error_has_retry,
                "recovered_after_retry": recovered_after_retry,
                "unhandled_page_errors_count": len(page_errors),
                "resilient": (len(page_errors) == 0 and error_has_message)
            }
            print(f"    Error state rendered on 500: {error_has_message}, retry button: {error_has_retry}, recovered: {recovered_after_retry}")
            print(f"    Zero page errors (unhandled rejections): {len(page_errors) == 0}")

            # --- SECTION 6: RESPONSIVE VIEWPORT TEST ACROSS 5 VIEWPORTS ---
            print("\n[7] Testing Responsive Viewports (360, 390, 768, 1024, 1440)")
            viewports = [360, 390, 768, 1024, 1440]
            viewport_results = {}
            page.goto(f"{base_url}dashboard", wait_until="domcontentloaded")
            page.wait_for_timeout(500)

            for w in viewports:
                page.set_viewport_size({"width": w, "height": 800})
                page.wait_for_timeout(300)
                overflow = page.evaluate("""() => {
                    const docWidth = document.documentElement.scrollWidth;
                    const winWidth = window.innerWidth;
                    return {
                        scrollWidth: docWidth,
                        innerWidth: winWidth,
                        hasOverflow: docWidth > winWidth
                    };
                }""")
                viewport_results[f"{w}px"] = {
                    "overflow": overflow["hasOverflow"],
                    "scroll_width": overflow["scrollWidth"],
                    "inner_width": overflow["innerWidth"]
                }
                print(f"    Viewport {w}px: overflow={overflow['hasOverflow']}")

            results["responsive_viewports"] = viewport_results
            results["page_errors"] = page_errors
            results["suite_completed"] = True

        finally:
            out_file = output_path / "comprehensive_audit_results.json"
            out_file.write_text(json.dumps(results, ensure_ascii=False, indent=2), encoding="utf-8")
            print(f"\n[Audit Complete] Output saved to: {out_file}")
            browser.close()

    return results

def evaluate_suite_success(results):
    failures = []

    if not results.get("suite_completed"):
        failures.append("Audit suite execution did not complete normally.")

    spa = results.get("spa_no_reload", {})
    if not spa.get("all_transitions_passed"):
        failures.append("One or more SPA menu transitions failed.")
    if not spa.get("back_forward_verified"):
        failures.append("Browser Back/Forward navigation failed to keep shell.")
    if spa.get("total_document_requests_during_all_menu_switching", 999) != 0:
        failures.append(f"Expected 0 extra document requests during menu navigation, found {spa.get('total_document_requests_during_all_menu_switching')}")

    endpoints = results.get("api_endpoints_verified", {})
    if not endpoints.get("all_endpoints_valid"):
        failures.append("API contract verification failed (invalid or missing endpoints).")

    modules = results.get("module_workflows", {})
    if not modules.get("all_modules_functional"):
        failures.append("One or more business modules failed to render.")

    cache = results.get("cache_ttl_and_request_counts", {})
    if not cache.get("cache_preserved_no_redundant_request"):
        failures.append("Cache TTL violated: redundant network requests were made.")

    err = results.get("error_state_handling", {})
    if not err.get("resilient"):
        failures.append("HTTP 500 error state resilience check failed.")
    if not err.get("recovered_after_retry"):
        failures.append("Failed to recover data display after clicking retry.")

    viewports = results.get("responsive_viewports", {})
    for vp, v in viewports.items():
        if v.get("overflow", True):
            failures.append(f"Horizontal scroll overflow detected at viewport {vp}.")

    if len(results.get("page_errors", [])) > 0:
        failures.append(f"Console errors detected during run: {results.get('page_errors')}")

    return len(failures) == 0, failures

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Verify HIMOTO SPA, modules, cache, error states, and responsive layout.")
    parser.add_argument("--base-url", default=DEFAULT_BASE_URL, help="Base URL of the running static dist server")
    parser.add_argument("--output-dir", default=str(DEFAULT_OUT), help="Directory to save audit JSON results")
    args = parser.parse_args()

    res = run_comprehensive_audit(base_url=args.base_url, output_dir=Path(args.output_dir))
    passed, issues = evaluate_suite_success(res)
    if passed:
        print("\n[SUCCESS] 100% of HIMOTO audit assertions PASSED cleanly.")
        sys.exit(0)
    else:
        print(f"\n[FAILURE] HIMOTO audit failed with {len(issues)} issue(s):")
        for iss in issues:
            print(f"  - {iss}")
        sys.exit(1)
