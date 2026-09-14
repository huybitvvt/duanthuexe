import os
import sys
import time
import json
import argparse
from playwright.sync_api import sync_playwright


def parse_args():
    parser = argparse.ArgumentParser(description="HIMOTO Login UI Verification Suite")
    parser.add_argument("--base-url", default="http://localhost:8091", help="Base URL of the SPA server")
    parser.add_argument("--output-dir", default=r"E:\duanthuexe\audit-prototype\login-redesign", help="Output directory for screenshots")
    return parser.parse_args()

def save_screenshot(page, output_dir, filename):
    os.makedirs(output_dir, exist_ok=True)
    target_path = os.path.join(output_dir, filename)
    page.screenshot(path=target_path, full_page=False)
    print(f"    [SCREENSHOT] Saved: {target_path}")


def run_tests():
    args = parse_args()
    base_url = args.base_url.rstrip("/")
    login_url = f"{base_url}/login"
    output_dir = os.path.abspath(args.output_dir)

    print("=" * 80)
    print("HIMOTO VERIFICATION SUITE: Login Page Redesign (A-E)")
    print(f"Target URL:   {login_url}")
    print(f"Output Dir:   {output_dir}")
    print("=" * 80)

    results = []

    with sync_playwright() as p:
        try:
            browser = p.chromium.launch(channel="msedge", headless=True)
        except Exception:
            browser = p.chromium.launch(headless=True)

        context = browser.new_context(viewport={"width": 1440, "height": 900})
        page = context.new_page()
        # Keep fixture runs isolated from the configured API, including dashboard GETs.
        page.route("**/api/**", lambda route: route.fulfill(
            status=200, content_type="application/json", body=json.dumps({"data": []})
        ))

        # Helper to reset to login
        def goto_clean_login():
            page.goto(f"{base_url}/login")
            page.evaluate("""() => {
                try {
                    localStorage.clear();
                    sessionStorage.clear();
                    if (window.__HIMOTO_STORE__) {
                        window.__HIMOTO_STORE__.commit('logOut');
                    }
                } catch(e) {}
            }""")
            page.wait_for_selector(".himoto-auth", timeout=10000)
            # Clear inputs
            page.evaluate("""() => {
                const email = document.getElementById('himoto-login-email');
                const pwd = document.getElementById('himoto-login-password');
                if (email) { email.value = ''; email.dispatchEvent(new Event('input')); }
                if (pwd) { pwd.value = ''; pwd.dispatchEvent(new Event('input')); }
            }""")

        # -------------------------------------------------------------
        # TEST 1: Desktop 2-Column Layout & Branding Elements
        # -------------------------------------------------------------
        print("\n[TEST 1] Verifying Desktop 2-Column Brand Layout (1440x900)...")
        page.set_viewport_size({"width": 1440, "height": 900})
        goto_clean_login()

        brand_visible = page.is_visible(".himoto-auth-brand")
        main_visible = page.is_visible(".himoto-auth-main")
        title_text = page.inner_text(".himoto-form-title").strip()
        hero_title = page.inner_text(".himoto-hero-title").strip()
        submit_text = page.inner_text("#himoto_btn_submit").strip()

        assert brand_visible, "Brand panel (.himoto-auth-brand) should be visible on desktop >= 1024px"
        assert main_visible, "Main form panel (.himoto-auth-main) should be visible"
        assert title_text == "Đăng nhập", f"Expected title 'Đăng nhập', got '{title_text}'"
        assert "Vận hành đội xe" in hero_title, f"Expected hero title with brand tagline, got '{hero_title}'"
        assert "Đăng nhập" in submit_text, f"Expected submit button 'Đăng nhập', got '{submit_text}'"

        save_screenshot(page, output_dir, "redesign_desktop.png")
        print("  -> [PASS] Desktop layout verified with 2 columns, brand panel, and Vietnamese content.")
        results.append({"test": "Desktop 2-Column Layout", "status": "PASS"})

        # -------------------------------------------------------------
        # TEST 2: Mobile 1-Column Responsive Layout (390x844)
        # -------------------------------------------------------------
        print("\n[TEST 2] Verifying Mobile 1-Column Responsive Layout (390x844)...")
        page.set_viewport_size({"width": 390, "height": 844})
        page.wait_for_timeout(300)

        # Brand panel should be hidden on mobile
        brand_display = page.evaluate("() => window.getComputedStyle(document.querySelector('.himoto-auth-brand')).display")
        mobile_logo_visible = page.is_visible(".himoto-mobile-header")
        card_visible = page.is_visible(".himoto-auth-card")

        # Check no horizontal overflow
        has_h_scroll = page.evaluate("() => document.documentElement.scrollWidth > window.innerWidth")

        assert brand_display == "none", f"Brand panel should have display: none on mobile, got {brand_display}"
        assert mobile_logo_visible, "Mobile logo header should be visible on < 1024px"
        assert page.locator('.himoto-mobile-logo').get_attribute('src').endswith('logo-himoto-dark.svg')
        assert page.locator('.himoto-mobile-logo').evaluate('(img) => img.complete && img.naturalWidth > 0')
        assert card_visible, "Auth card should be visible on mobile"
        assert not has_h_scroll, "Mobile view has horizontal overflow!"

        save_screenshot(page, output_dir, "redesign_mobile.png")
        print("  -> [PASS] Mobile 1-column layout verified without horizontal overflow.")
        results.append({"test": "Mobile 1-Column Layout", "status": "PASS"})

        # -------------------------------------------------------------
        # TEST 3: Multi-Viewport Matrix (360, 390, 768, 1024, 1440)
        # -------------------------------------------------------------
        print("\n[TEST 3] Verifying Multi-Viewport Matrix...")
        viewports = [
            {"width": 360, "height": 780, "name": "Small Mobile (360x780)"},
            {"width": 390, "height": 844, "name": "Standard Mobile (390x844)"},
            {"width": 768, "height": 1024, "name": "Tablet Portrait (768x1024)"},
            {"width": 1024, "height": 768, "name": "Tablet Landscape (1024x768)"},
            {"width": 1440, "height": 900, "name": "Desktop (1440x900)"},
        ]
        for vp in viewports:
            page.set_viewport_size({"width": vp["width"], "height": vp["height"]})
            page.wait_for_timeout(200)
            overflow = page.evaluate("() => document.documentElement.scrollWidth > window.innerWidth")
            assert not overflow, f"Horizontal scroll detected on {vp['name']}"
            print(f"    - {vp['name']}: No overflow (scrollWidth <= {vp['width']}) [OK]")

        print("  -> [PASS] All 5 viewports verified clean without horizontal overflow.")
        results.append({"test": "Multi-Viewport Matrix", "status": "PASS"})

        # Reset to desktop for interaction tests
        page.set_viewport_size({"width": 1440, "height": 900})

        # -------------------------------------------------------------
        # TEST 4: Client-Side Inline Validation (Empty & Invalid Format)
        # -------------------------------------------------------------
        print("\n[TEST 4] Verifying Client-Side Inline Validation...")
        goto_clean_login()

        # Click submit with empty inputs
        page.click("#himoto_btn_submit")
        page.wait_for_selector("#himoto-email-error", timeout=3000)
        page.wait_for_selector("#himoto-password-error", timeout=3000)

        email_err = page.inner_text("#himoto-email-error").strip()
        pwd_err = page.inner_text("#himoto-password-error").strip()
        email_aria = page.get_attribute("#himoto-login-email", "aria-invalid")

        assert "Email không được để trống" in email_err, f"Unexpected email error: {email_err}"
        assert "Mật khẩu không được để trống" in pwd_err, f"Unexpected password error: {pwd_err}"
        assert email_aria == "true", "aria-invalid should be 'true' when email has error"

        # Check invalid email format
        page.fill("#himoto-login-email", "invalid_email_format")
        page.click("#himoto_btn_submit")
        page.wait_for_timeout(200)
        email_err_invalid = page.inner_text("#himoto-email-error").strip()
        assert "Địa chỉ email không hợp lệ" in email_err_invalid, f"Unexpected invalid email error: {email_err_invalid}"

        save_screenshot(page, output_dir, "redesign_error_state.png")
        print("  -> [PASS] Client-side inline Vietnamese validation verified.")
        results.append({"test": "Client-Side Inline Validation", "status": "PASS"})

        # -------------------------------------------------------------
        # TEST 5: Password Visibility Toggle
        # -------------------------------------------------------------
        print("\n[TEST 5] Verifying Password Visibility Toggle...")
        goto_clean_login()

        pwd_type_initial = page.get_attribute("#himoto-login-password", "type")
        toggle_label_initial = page.get_attribute(".himoto-toggle-pwd", "aria-label")
        assert pwd_type_initial == "password", "Default password input type should be 'password'"
        assert toggle_label_initial == "Hiện mật khẩu", f"Expected aria-label 'Hiện mật khẩu', got '{toggle_label_initial}'"

        # Fill secret password and toggle
        page.fill("#himoto-login-password", "HimotoSecret2026")
        page.click(".himoto-toggle-pwd")
        page.wait_for_timeout(100)

        pwd_type_toggled = page.get_attribute("#himoto-login-password", "type")
        toggle_label_toggled = page.get_attribute(".himoto-toggle-pwd", "aria-label")
        assert pwd_type_toggled == "text", "Password input type should become 'text' after clicking toggle"
        assert toggle_label_toggled == "Ẩn mật khẩu", f"Expected aria-label 'Ẩn mật khẩu', got '{toggle_label_toggled}'"

        save_screenshot(page, output_dir, "redesign_password_visible.png")

        # Toggle back to hidden
        page.click(".himoto-toggle-pwd")
        pwd_type_restored = page.get_attribute("#himoto-login-password", "type")
        assert pwd_type_restored == "password", "Password input type should return to 'password'"

        print("  -> [PASS] Password toggle behaves correctly with accessible labels.")
        results.append({"test": "Password Visibility Toggle", "status": "PASS"})

        # -------------------------------------------------------------
        # TEST 6: Real Pending State & Double Submit Prevention (No 2s Delay)
        # -------------------------------------------------------------
        print("\n[TEST 6] Verifying Pending State & Double Submit Prevention...")
        goto_clean_login()

        request_count = [0]
        hold_route = []

        def handle_login_pending(route):
            u = route.request.url
            if "auth/login" in u:
                request_count[0] += 1
                hold_route.append(route)
            else:
                route.continue_()

        page.route("**/*auth/login*", handle_login_pending)

        page.fill("#himoto-login-email", "admin@himoto.vn")
        page.fill("#himoto-login-password", "admin_pass")

        # Click submit
        page.click("#himoto_btn_submit")
        page.wait_for_timeout(100)

        # Check submitting state
        btn_disabled = page.is_disabled("#himoto_btn_submit")
        btn_text = page.inner_text("#himoto_btn_submit").strip()
        spinner_visible = page.is_visible(".himoto-spinner")

        assert btn_disabled, "Submit button must be disabled during pending request"
        assert "Đang đăng nhập..." in btn_text, f"Expected 'Đang đăng nhập...', got '{btn_text}'"
        assert spinner_visible, "Spinner must be visible during pending request"

        save_screenshot(page, output_dir, "redesign_loading_state.png")

        # Try to double submit by clicking or pressing Enter
        page.press("#himoto-login-password", "Enter")
        page.wait_for_timeout(200)

        assert request_count[0] == 1, f"Double submission occurred! Request count was {request_count[0]}"

        # Fulfill pending route with 401 error to complete cleanly
        if hold_route:
            hold_route[0].fulfill(
                status=401,
                content_type="application/json",
                body=json.dumps({"data": {"error": "Thông tin đăng nhập không chính xác."}})
            )

        page.unroute("**/*auth/login*")
        page.wait_for_timeout(300)

        # Button should re-enable after completion
        btn_disabled_after = page.is_disabled("#himoto_btn_submit")
        assert not btn_disabled_after, "Submit button should re-enable after request completes"

        print("  -> [PASS] Pending state verified, zero artificial delay, double submission blocked.")
        results.append({"test": "Pending State & Double Submit Guard", "status": "PASS"})

        # -------------------------------------------------------------
        # TEST 7: Network & Offline Error Handling
        # -------------------------------------------------------------
        print("\n[TEST 7] Verifying Network Error & 401 Backend Error Handling...")
        goto_clean_login()

        # 7a: 401 Invalid Credentials
        page.route("**/*auth/login*", lambda route: route.fulfill(
            status=401,
            content_type="application/json",
            body=json.dumps({"data": {"error": "Tài khoản hoặc mật khẩu không chính xác."}})
        ))

        page.fill("#himoto-login-email", "wrong@himoto.vn")
        page.fill("#himoto-login-password", "wrongpass")
        page.click("#himoto_btn_submit")

        page.wait_for_selector("#himoto-general-alert", timeout=3000)
        alert_text = page.inner_text("#himoto-general-alert").strip()
        assert "Tài khoản hoặc mật khẩu không chính xác." in alert_text or "Email hoặc mật khẩu" in alert_text, f"Unexpected alert text: {alert_text}"
        page.unroute("**/*auth/login*")

        # 7b: Offline / Network Failure
        goto_clean_login()
        page.route("**/*auth/login*", lambda route: route.abort("failed"))

        page.fill("#himoto-login-email", "test@himoto.vn")
        page.fill("#himoto-login-password", "testpass")
        page.click("#himoto_btn_submit")

        page.wait_for_selector("#himoto-general-alert", timeout=3000)
        network_alert = page.inner_text("#himoto-general-alert").strip()
        assert "Không thể kết nối" in network_alert, f"Unexpected network alert: {network_alert}"
        page.unroute("**/*auth/login*")

        print("  -> [PASS] 401 and offline network errors correctly surfaced in Vietnamese alert.")
        results.append({"test": "Error Handling (401 & Offline)", "status": "PASS"})

        # -------------------------------------------------------------
        # TEST 8: Successful Login & Role-Based Routing
        # -------------------------------------------------------------
        print("\n[TEST 8] Verifying Successful Login & Role-Based Navigation...")

        # 8a: Role 1 (Admin) -> routes to /dashboard
        goto_clean_login()
        mock_admin = {
            "access_token": "mock_admin_jwt_token_12345",
            "token": "mock_admin_jwt_token_12345",
            "user": {
                "id": 1,
                "name": "HIMOTO Admin",
                "email": "admin@himoto.vn",
                "role_id": 1,
                "store_id": "all"
            }
        }
        page.route("**/*auth/login*", lambda route: route.fulfill(
            status=200,
            content_type="application/json",
            body=json.dumps(mock_admin)
        ))
        page.route("**/*verify-token*", lambda route: route.fulfill(
            status=200,
            content_type="application/json",
            body=json.dumps(mock_admin)
        ))
        page.route("**/*dashboard*", lambda route: route.fulfill(
            status=200,
            content_type="application/json",
            body=json.dumps({"total_vehicle": 100})
        ))

        page.fill("#himoto-login-email", "admin@himoto.vn")
        page.fill("#himoto-login-password", "admin_pass")
        page.click("#himoto_btn_submit")

        page.wait_for_function("() => window.location.pathname.includes('/dashboard') || window.location.hash.includes('dashboard')", timeout=8000)
        current_url = page.url
        assert "dashboard" in current_url, f"Role 1 expected route to dashboard, got {current_url}"
        page.unroute("**/*auth/login*")
        page.unroute("**/*verify-token*")
        page.unroute("**/*dashboard*")
        print("    - Role 1 (Admin) successfully navigated to /dashboard [OK]")

        # 8b: Role 4 (Sales/Leads) -> routes to /leads
        goto_clean_login()
        mock_leads_user = {
            "access_token": "mock_leads_jwt_token_67890",
            "token": "mock_leads_jwt_token_67890",
            "user": {
                "id": 4,
                "name": "Sale Rep",
                "email": "sale@himoto.vn",
                "role_id": 4,
                "store_id": 1
            }
        }
        page.route("**/*auth/login*", lambda route: route.fulfill(
            status=200,
            content_type="application/json",
            body=json.dumps(mock_leads_user)
        ))
        page.route("**/*verify-token*", lambda route: route.fulfill(
            status=200,
            content_type="application/json",
            body=json.dumps(mock_leads_user)
        ))
        page.route("**/*lead*", lambda route: route.fulfill(
            status=200,
            content_type="application/json",
            body=json.dumps({"data": []})
        ))

        page.fill("#himoto-login-email", "sale@himoto.vn")
        page.fill("#himoto-login-password", "sale_pass")
        page.click("#himoto_btn_submit")

        page.wait_for_function("() => window.location.pathname.includes('/leads') || window.location.hash.includes('leads')", timeout=8000)
        leads_url = page.url
        assert "leads" in leads_url, f"Role 4 expected route to leads, got {leads_url}"
        page.unroute("**/*auth/login*")
        page.unroute("**/*verify-token*")
        page.unroute("**/*lead*")
        print("    - Role 4 (Sales) successfully navigated to /leads [OK]")

        print("  -> [PASS] Role-based routing verified without page reload.")
        results.append({"test": "Successful Login & Role Routing", "status": "PASS"})

        # Registration is a pre-existing route; preserve its required confirmation payload.
        goto_clean_login()
        page.goto(f"{base_url}/ref/123")
        page.locator('#himoto-register-confirmation').wait_for()
        signup_requests = []
        def capture_signup(route):
            signup_requests.append(route.request.post_data_json)
            route.fulfill(status=422, content_type="application/json", body=json.dumps({"error": "fixture"}))
        page.route("**/*auth/register*", capture_signup)
        page.locator('input[type=text]').fill('Test User')
        page.locator('input[type=email]').fill('test@example.com')
        page.locator('input[type=password]').nth(0).fill('fixture-password')
        page.locator('#himoto-register-confirmation').fill('fixture-password')
        page.get_by_role('button', name='Đăng ký', exact=True).click()
        page.wait_for_timeout(500)
        assert len(signup_requests) == 1
        assert signup_requests[0]['password_confirmation'] == signup_requests[0]['password']
        assert signup_requests[0]['referral_code'] == '123'
        results.append({"test": "Legacy registration confirmation payload", "status": "PASS"})

        browser.close()

    print("\n" + "=" * 80)
    print("ALL LOGIN REDESIGN TESTS PASSED!")
    print(f"Total Test Cases: {len(results)} / {len(results)} PASS")
    print("=" * 80)

if __name__ == "__main__":
    run_tests()
