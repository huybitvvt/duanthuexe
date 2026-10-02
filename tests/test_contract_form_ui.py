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
    assert page.get_by_role("link", name=re.compile("Xem kho xe Thuê")).count() == 1
    create.click()
    modal = page.locator(".modal.show")
    expect(modal.get_by_text("Bước 1/5:", exact=False)).to_be_visible()
    page.evaluate("""() => { window.contractForm = () => {
        let node = document.querySelector('.modal.show .form');
        while (node && !node.__vue__) node = node.parentElement;
        let vm = node && node.__vue__;
        while (vm && vm.$options.name !== 'OrderUpdate') vm = vm.$parent;
        if (!vm) throw new Error('OrderUpdate was not mounted');
        return vm;
    }; }""")
    page.wait_for_function("contractForm().priceVehicles.length === 1")
    assert "is-disabled" in modal.locator("#tab-vehicle").get_attribute("class")
    assert page.get_by_role("link", name=re.compile("Xem kho xe Thuê")).count() == 1

    # Keyboard completion of the final optional field advances automatically.
    reference = modal.get_by_placeholder("VD: 260924-0001 hoặc số trên hợp đồng giấy")
    reference.fill("LOCAL-FORM-0001")
    reference.press("Tab")
    page.wait_for_function("contractForm().activeContractTab === 'customer'")
    next_step = modal.get_by_role("button", name=re.compile("^Tiếp tục:"))

    def go_to_step(target):
        names = ['contract', 'customer', 'vehicle', 'payment', 'signing']
        while page.evaluate('contractForm().activeContractTab') != target:
            current = names.index(page.evaluate('contractForm().activeContractTab'))
            destination = names.index(target)
            if current > destination:
                modal.get_by_role('button', name='Quay lại', exact=True).click()
                expected = names[current - 1]
            else:
                next_step.click()
                expected = names[current + 1]
            page.wait_for_function('name => contractForm().activeContractTab === name', arg=expected)

    next_step.click()
    assert page.evaluate("contractForm().activeContractTab") == "customer"
    assert not submitted

    modal.get_by_placeholder("Tên khách hàng", exact=True).fill("Khách kiểm thử")
    modal.get_by_placeholder("SĐT khách hàng", exact=True).fill("0912345678")
    modal.get_by_placeholder("Số CMTND/CCCD", exact=True).fill("123456789")
    page.wait_for_function("contractForm().order.contract_signer_b_name === 'Khách kiểm thử'")
    last_customer_field = modal.get_by_placeholder("SĐT người thân 2", exact=True)
    last_customer_field.focus()
    last_customer_field.press("Tab")
    page.wait_for_function("contractForm().activeContractTab === 'vehicle'")

    # Invalid dates and an incomplete additional vehicle cannot advance or submit.
    next_step.click()
    assert page.evaluate("contractForm().activeContractTab") == "vehicle"
    modal.get_by_role("button", name="Thêm phương tiện", exact=True).click()
    assert page.evaluate("contractForm().order.order_items.length") == 2
    assert not submitted
    page.evaluate("""() => {
        const form = contractForm();
        const item = form.order.order_items[0];
        item.vehicle_id = 101;
        item.rent_at = new Date('2026-10-02T09:00:00');
        item.return_at = new Date('2026-10-03T09:00:00');
    }""")
    page.wait_for_function("contractForm().order.order_items[0].hiringFee === 200000")
    next_step.click()
    assert page.evaluate("contractForm().activeContractTab") == "vehicle"
    page.evaluate("contractForm().order.order_items.splice(1, 1)")
    last_vehicle_field = modal.get_by_placeholder('Số km khi khách nhận xe', exact=True)
    last_vehicle_field.focus()
    last_vehicle_field.press('Tab')
    page.wait_for_function("contractForm().activeContractTab === 'payment'")
    expect(modal.locator("#rental-fee")).to_be_disabled()
    assert page.evaluate("contractForm().order.order_items[0].custom_hiring_fee") is None

    page.evaluate("contractForm().order.first_deposit_amount = 1000000")
    page.wait_for_function("contractForm().order.first_deposit_payment_method.cash_amount === 1000000")
    page.evaluate("contractForm().order.first_deposit_payment_method.cash_amount = 999")
    next_step.click()
    assert page.evaluate("contractForm().activeContractTab") == "payment"
    expect(modal.get_by_text("Tiền cọc: cần chọn tài khoản nhận chuyển khoản.", exact=True)).to_be_visible()
    page.evaluate("contractForm().order.first_deposit_payment_method.cash_amount = 1000000")
    if output_dir:
        page.screenshot(path=str(output_dir / f"contract-payment-{width}.png"), full_page=True)
    last_payment_field = modal.get_by_placeholder('VD: Ví điện tử, bù trừ công nợ...', exact=True).last
    last_payment_field.focus()
    last_payment_field.press('Tab')
    page.wait_for_function("contractForm().activeContractTab === 'signing'")
    signer = modal.get_by_placeholder("Họ tên người thuê ký hợp đồng", exact=True)
    expect(signer).to_have_value("Khách kiểm thử")

    # Renaming the customer follows through, but an explicit representative is preserved.
    go_to_step('customer')
    modal.get_by_placeholder("Tên khách hàng", exact=True).fill("Khách kiểm thử lần 2")
    page.wait_for_function("contractForm().order.contract_signer_b_name === 'Khách kiểm thử lần 2'")
    go_to_step('signing')
    signer.fill("Người đại diện kiểm thử")
    go_to_step('customer')
    modal.get_by_placeholder("Tên khách hàng", exact=True).fill("Khách kiểm thử lần 3")
    go_to_step('signing')
    expect(signer).to_have_value("Người đại diện kiểm thử")
    expect(modal.locator('#tab-signing')).to_have_class(re.compile(r'\bis-active\b'))
    expect(modal.locator('#tab-signing')).to_have_attribute('aria-selected', 'true')
    bounds = modal.locator(".modal-dialog").bounding_box()
    assert bounds["x"] >= 0 and bounds["x"] + bounds["width"] <= width + 1, bounds
    if output_dir:
        page.screenshot(path=str(output_dir / f"contract-signing-{width}.png"), full_page=True)

    # Only the intercepted fixture receives the save; the built UI sends the correct amounts.
    modal.get_by_role("button", name="Lưu hợp đồng", exact=True).click()
    page.wait_for_function("!document.querySelector('.modal.show .form')")
    assert len(submitted) == 1, submitted
    saved = submitted[0]
    assert saved["store_id"] == 1
    assert saved["total_rental_fees"] == saved["total"] == 200000
    assert saved["pid"] == 1200000
    assert saved["contract_signer_b_name"] == "Người đại diện kiểm thử"
    assert saved["order_items"][0]["custom_total_money"] is None
    assert saved["order_items"][0]["total_money"] == 200000

    # Incomplete delivery drafts retain their existing save-from-any-step behavior.
    page.get_by_role('button', name='Đơn thuê xe nháp', exact=True).click()
    page.get_by_role('button', name='Thêm mới đơn thuê xe nháp', exact=True).click()
    modal.get_by_role('button', name='Lưu bản nháp', exact=True).click()
    page.wait_for_function("!document.querySelector('.modal.show .form')")
    assert len(submitted) == 2
    assert submitted[1]['save_as_draft'] is True
    assert submitted[1]['order_mode'] == 'draft'
    assert submitted[1]['customer_name'] == ''
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
