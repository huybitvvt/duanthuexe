#!/usr/bin/env python3
"""Contract form regression checks using a local build and intercepted API fixtures."""

import argparse
import json
import re
from pathlib import Path
from urllib.parse import urlparse

from playwright.sync_api import expect, sync_playwright


STAFF = {
    "id": 10, "name": "Nhân viên kiểm thử", "role_id": 3,
    "role": "nhan-vien", "store_id": 1, "status": "active",
}
CAPABILITIES = ["order.create", "order.update", "order.view_store", "order.count", "vehicle.view_all"]
STORES = [{"id": 1, "code": "CS1", "store_name": "CS 1"}]
VEHICLES = [{
    "id": 101, "name": "Xe kiểm thử", "license": "TEST-101", "type": "xega",
    "year": 2023, "status": "ready", "store_id": 1, "current_store_id": 1,
}]
PRICES = [{
    "id": 1, "type": "xega", "price_type": "day", "from_year": 2020,
    "to_year": 2026, "from_date": 1, "to_date": 30, "price": 200000,
}]
ELEMENT_THEME = Path(__file__).resolve().parents[1] / 'node_modules' / 'element-ui' / 'lib' / 'theme-chalk'


def check_form(browser, base_url, width, output_dir):
    context = browser.new_context(viewport={"width": width, "height": 900}, reduced_motion="reduce")
    context.add_init_script("localStorage.setItem('id_token', 'local-fixture-session');")
    submitted = []
    page_errors = []
    blocked_writes = []

    def intercept(route):
        request = route.request
        parsed = urlparse(request.url)
        path = parsed.path
        if "/api/" in path:
            if request.method == "OPTIONS":
                route.fulfill(status=204, headers={
                    "Access-Control-Allow-Origin": base_url,
                    "Access-Control-Allow-Headers": "Authorization, Content-Type",
                    "Access-Control-Allow-Methods": "GET, POST, PUT, DELETE, OPTIONS",
                })
                return
            if request.method != "GET":
                if path == "/api/auth/order/car-rental" and request.method == "POST":
                    submitted.append(request.post_data_json)
                    payload = {"data": {"id": 1001}, "message": "Fixture saved"}
                else:
                    blocked_writes.append({"method": request.method, "path": path})
                    route.abort()
                    return
            elif path == "/api/verify-token":
                payload = {"user": STAFF, "capabilities": CAPABILITIES}
            elif "/stores" in path:
                payload = {"data": STORES}
            elif path == "/api/auth/vehicle/vehicles":
                payload = {"data": VEHICLES}
            elif path == "/api/auth/priceVehicles":
                payload = {"data": PRICES}
            elif "/get-staff-by-store" in path:
                payload = {"data": [STAFF]}
            elif "/banks" in path:
                payload = {"data": {"data": []}} if path.endswith("/banks") else {"data": []}
            elif path == "/api/auth/order/car-rental":
                payload = {"data": {"data": [], "total": 0, "last_page": 1, "current_page": 1}}
            elif "/quick-report" in path:
                payload = {"data": {"total_order": 0, "total_contracts_completed": 0,
                                    "total_contracts_renting": 0, "total_out_of_date": 0}}
            else:
                payload = {"data": {"items": [], "unread_count": 0, "total": 0}}
            route.fulfill(json=payload, headers={"Access-Control-Allow-Origin": base_url})
        elif parsed.hostname == 'cdn.jsdelivr.net' and path.endswith('/lib/index.min.css'):
            route.fulfill(path=str(ELEMENT_THEME / 'index.css'), content_type='text/css')
        elif parsed.hostname == 'cdn.jsdelivr.net' and path.endswith(('/element-icons.woff', '/element-icons.ttf')):
            route.fulfill(path=str(ELEMENT_THEME / 'fonts' / Path(path).name))
        elif parsed.hostname in {"127.0.0.1", "localhost", "::1"}:
            route.continue_()
        else:
            # No browser request reaches the deployed API or an external service.
            route.abort()

    context.route("**/*", intercept)
    page = context.new_page()
    page.on("pageerror", lambda error: page_errors.append(str(error)))
    page.goto(base_url + "/car-rental", wait_until="domcontentloaded")
    create = page.get_by_role("button", name="Thêm mới đơn thuê xe phổ thông", exact=True)
    expect(create).to_be_visible(timeout=20000)
    expect(page.locator('.rental-quick-create')).to_have_count(0)
    create.click()
    modal = page.locator('.modal.show')
    expect(modal.get_by_text('Bước 1/5:', exact=False)).to_be_visible()
    page.evaluate("""() => { window.contractForm = () => {
        let node = document.querySelector('.modal.show .form');
        while (node && !node.__vue__) node = node.parentElement;
        let vm = node && node.__vue__;
        while (vm && vm.$options.name !== 'OrderUpdate') vm = vm.$parent;
        return vm;
    }; }""")
    page.wait_for_function('contractForm().priceVehicles.length === 1')
    expect(modal.get_by_placeholder('Hệ thống tự cấp mã khi lưu nháp', exact=True)).to_have_attribute('readonly', 'readonly')
    next_step = modal.get_by_role('button', name=re.compile('^Tiếp tục:'))
    next_step.click()
    page.wait_for_function("contractForm().activeContractTab === 'customer'")
    modal.get_by_placeholder('Tên khách hàng', exact=True).fill('Khách kiểm thử')
    page.wait_for_function("contractForm().order.contract_signer_b_name === 'Khách kiểm thử'")
    last_customer_field = modal.get_by_placeholder('SĐT người thân 2', exact=True)
    last_customer_field.focus()
    last_customer_field.press('Tab')
    page.wait_for_function("contractForm().activeContractTab === 'vehicle'")
    page.evaluate("""() => {
        const item = contractForm().order.order_items[0]; item.vehicle_id = 101;
        item.rent_at = new Date('2026-10-02T09:00:00'); item.return_at = new Date('2026-10-03T09:00:00');
    }""")
    page.wait_for_function('contractForm().order.order_items[0].hiringFee === 200000')
    last_vehicle_field = modal.get_by_placeholder('Số km khi khách nhận xe', exact=True)
    last_vehicle_field.focus()
    last_vehicle_field.press('Tab')
    page.wait_for_function("contractForm().activeContractTab === 'payment'")
    expect(modal.locator('#rental-fee')).to_be_disabled()
    next_step.click()
    page.wait_for_function("contractForm().activeContractTab === 'signing'")
    signer = modal.get_by_placeholder('Họ tên người thuê ký hợp đồng', exact=True)
    expect(signer).to_have_value('Khách kiểm thử')
    signer.fill('Người đại diện kiểm thử')
    page.evaluate("contractForm().order.customer_name = 'Khách đổi tên'")
    expect(signer).to_have_value('Người đại diện kiểm thử')
    expect(modal.locator('#tab-signing')).to_have_class(re.compile(r'\bis-active\b'))
    expect(modal.locator('#tab-signing')).to_have_attribute('aria-selected', 'true')
    bounds = modal.locator('.modal-dialog').bounding_box()
    assert bounds['x'] >= 0 and bounds['x'] + bounds['width'] <= width + 1
    expect(modal.get_by_role('button', name='Tạo đơn nháp và bổ sung thông tin', exact=True)).to_be_visible()
    if output_dir:
        page.screenshot(path=str(output_dir / f'contract-signing-{width}.png'), full_page=True)
    assert not submitted
    assert not page_errors, page_errors
    assert not blocked_writes, blocked_writes
    context.close()
    return {"width": width, "passed": True, "save": "intercepted fixture only"}


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--base-url", default="http://127.0.0.1:8098")
    parser.add_argument("--output-dir", type=Path)
    args = parser.parse_args()
    if urlparse(args.base_url).hostname not in {"127.0.0.1", "localhost", "::1"}:
        raise ValueError("Only a loopback UI build is allowed")
    if args.output_dir:
        args.output_dir.mkdir(parents=True, exist_ok=True)
    with sync_playwright() as playwright:
        try:
            browser = playwright.chromium.launch(channel="msedge", headless=True)
        except Exception:
            browser = playwright.chromium.launch(headless=True)
        results = [check_form(browser, args.base_url.rstrip("/"), width, args.output_dir) for width in [1440, 375]]
        browser.close()
    report = json.dumps({"all_passed": True, "viewports": results}, ensure_ascii=False, indent=2)
    if args.output_dir:
        (args.output_dir / "ui-results.json").write_text(report, encoding="utf-8")
    print(report)


if __name__ == "__main__":
    main()
