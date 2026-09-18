#!/usr/bin/env python3
"""Authenticated UI smoke test, restricted to a loopback HIMOTO instance."""

import argparse
import json
import urllib.parse

from playwright.sync_api import sync_playwright


ROUTES = [
    "/dashboard",
    "/car-rental",
    "/vehicles",
    "/warehouses",
    "/leads",
    "/maintenance-schedule",
    "/maintenance-log",
    "/maintenance-rule",
    "/maintenance-type",
    "/customers",
    "/stores",
    "/banks",
    "/cash",
    "/transactions",
    "/receipt",
    "/finances/daily-cash-register",
    "/hr/duty-schedule",
    "/pricing",
    "/user",
    "/car-sell",
    "/lease-to-own",
    "/customer-reminders",
    "/report/detail-report",
    "/report/vehicle-revenue",
    "/report/kpi",
    "/accounting",
]

ACTION_PAGES = {
    "/vehicles": ("Thêm mới", "Xem", "Sửa", "Xóa"),
    "/leads": ("Thêm mới", "Xem", "Sửa"),
    "/customers": ("Thêm mới", "Xem", "Sửa", "Xóa"),
    "/stores": ("Thêm mới", "Sửa", "Xóa"),
    "/banks": ("Thêm mới", "Xem", "Sửa", "Xóa"),
    "/cash": ("Thêm mới", "Xem", "Sửa", "Xóa"),
    "/user": ("Thêm mới", "Sửa", "Xóa"),
}


def assert_loopback(base_url):
    hostname = urllib.parse.urlparse(base_url).hostname
    if hostname not in {"localhost", "127.0.0.1", "::1"}:
        raise ValueError("UI smoke test only accepts a loopback URL")


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--base-url", default="http://127.0.0.1:8088")
    parser.add_argument("--email", default="codex-crud-audit@example.test")
    parser.add_argument("--password", default="AuditOnly-2026!")
    parser.add_argument("--route", action="append", dest="routes")
    args = parser.parse_args()
    base_url = args.base_url.rstrip("/")
    assert_loopback(base_url)

    results = []
    with sync_playwright() as playwright:
        try:
            browser = playwright.chromium.launch(channel="msedge", headless=True)
        except Exception:
            browser = playwright.chromium.launch(headless=True)

        page = browser.new_page(viewport={"width": 1440, "height": 900})
        page_errors = []
        api_errors = []
        page.on("pageerror", lambda error: page_errors.append(str(error)))

        def record_response(response):
            parsed = urllib.parse.urlparse(response.url)
            if parsed.hostname in {"localhost", "127.0.0.1", "::1"} and "/api/" in parsed.path and response.status >= 400:
                try:
                    body = response.text()[:500]
                except Exception:
                    body = "<unavailable>"
                api_errors.append({"status": response.status, "url": response.url, "body": body})

        page.on("response", record_response)
        page.goto(f"{base_url}/login", wait_until="domcontentloaded")
        page.wait_for_selector("#himoto-login-email", timeout=15_000)
        page.fill("#himoto-login-email", args.email)
        page.fill("#himoto-login-password", args.password)
        page.click("#himoto_btn_submit")
        page.wait_for_url(lambda url: "/login" not in url, timeout=20_000)
        page.wait_for_timeout(1_000)

        for route in args.routes or ROUTES:
            print(f"[CHECK] {route}")
            errors_before = len(page_errors)
            api_errors_before = len(api_errors)
            page.goto(f"{base_url}{route}", wait_until="domcontentloaded", timeout=30_000)
            try:
                page.wait_for_selector(".himoto-app-shell", timeout=15_000)
            except Exception as error:
                raise AssertionError(
                    f"{route}: app shell did not render; url={page.url}; page_errors={page_errors[errors_before:]}"
                ) from error
            page.wait_for_timeout(1_200)

            body_text = page.locator("body").inner_text()
            if "Đăng nhập" in body_text and page.locator("#himoto-login-email").count():
                raise AssertionError(f"Session was lost while opening {route}")

            new_page_errors = page_errors[errors_before:]
            new_api_errors = api_errors[api_errors_before:]
            if new_page_errors or new_api_errors:
                raise AssertionError(
                    f"{route}: page errors={new_page_errors}; API errors={new_api_errors}"
                )

            expected_actions = ACTION_PAGES.get(route, ())
            missing_actions = [label for label in expected_actions if label not in body_text]
            if missing_actions:
                raise AssertionError(f"{route}: missing actions {missing_actions}; body={body_text[:1000]!r}")

            result = {"route": route, "actions": list(expected_actions), "pass": True}
            results.append(result)
            print(f"[PASS] {route}")

        browser.close()

    print(json.dumps({"all_passed": True, "routes": results}, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
