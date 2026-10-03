"""Customer rental flow against a local build with all API requests intercepted."""
import argparse
import json
import re
from datetime import datetime
from pathlib import Path
from urllib.parse import urlparse

from playwright.sync_api import expect, sync_playwright
from test_contract_form_ui import STAFF, CAPABILITIES, STORES, VEHICLES, PRICES, ELEMENT_THEME


def check_draft(browser, base_url, width, admin, output_dir):
    context = browser.new_context(viewport={"width": width, "height": 1000}, reduced_motion="reduce")
    context.add_init_script("localStorage.setItem('id_token', 'local-fixture-session');")
    user = dict(STAFF, role_id=1 if admin else 3)
    writes, errors, blocked = [], [], []
    saved = {}

    def intercept(route):
        request = route.request
        parsed = urlparse(request.url)
        path = parsed.path
        headers = {"Access-Control-Allow-Origin": base_url}
        if "/api/" in path:
            if request.method == "OPTIONS":
                route.fulfill(status=204, headers=dict(headers,
                    **{"Access-Control-Allow-Headers": "Authorization, Content-Type",
                       "Access-Control-Allow-Methods": "GET, POST, PUT, DELETE, OPTIONS"}))
                return
            if request.method == "POST" and path == "/api/auth/order/car-rental":
                writes.append(request.post_data_json)
                if len(writes) == 1:
                    route.fulfill(status=422, json={"message": "Fixture validation error",
                        "errors": {"customer_name": ["Lỗi thử nghiệm: giữ lại thông tin đã nhập."]}}, headers=headers)
                    return
                saved.update(request.post_data_json)
                payload = {"data": {"id": 1001, "order_status": "draft", "draft_reference": "261003-0001"}}
            elif request.method != "GET":
                blocked.append({"method": request.method, "path": path})
                route.abort()
                return
            elif path == "/api/verify-token":
                payload = {"user": user, "capabilities": ["*"] if admin else CAPABILITIES}
            elif "/stores" in path:
                payload = {"data": STORES}
            elif path == "/api/auth/vehicle/vehicles":
                payload = {"data": VEHICLES}
            elif path == "/api/auth/priceVehicles":
                payload = {"data": PRICES}
            elif "/get-staff-by-store" in path:
                payload = {"data": [user]}
            elif "/banks" in path:
                payload = {"data": {"data": []}} if path.endswith("/banks") else {"data": []}
            elif path == "/api/auth/order/car-rental":
                payload = {"data": {"data": [], "total": 0, "last_page": 1, "current_page": 1}}
            elif path == "/api/auth/order/car-rental/1001":
                items = []
                for original in saved["order_items"]:
                    item = dict(original, id=1, order_item_fees=[])
                    for field in ["rent_at", "return_at"]:
                        item[field] = datetime.strptime(item[field], "%d-%m-%Y %H:%M:%S").strftime("%Y-%m-%d %H:%M:%S")
                    items.append(item)
                payload = {"data": dict(saved, id=1001, order_status="draft", draft_reference="261003-0001",
                    contract_number=None, contract_snapshot=None,
                    customer={"name": saved["customer_name"], "phone": "", "id_card": "", "relatives": []},
                    order_items=items, draft_payload={"order_items": items})}
            elif "/quick-report" in path:
                payload = {"data": {"total_order": 0}}
            elif "/unique" in path:
                payload = {"data": []}
            else:
                payload = {"data": {"items": [], "unread_count": 0, "total": 0}}
            route.fulfill(json=payload, headers=headers)
        elif parsed.hostname == "cdn.jsdelivr.net" and path.endswith("/lib/index.min.css"):
            route.fulfill(path=str(ELEMENT_THEME / "index.css"), content_type="text/css")
        elif parsed.hostname == "cdn.jsdelivr.net" and path.endswith(("/element-icons.woff", "/element-icons.ttf")):
            route.fulfill(path=str(ELEMENT_THEME / "fonts" / Path(path).name))
        elif parsed.hostname in {"127.0.0.1", "localhost", "::1"}:
            route.continue_()
        else:
            route.abort()

    context.route("**/*", intercept)
    page = context.new_page()
    page.on("pageerror", lambda error: errors.append(str(error)))
    page.goto(base_url + "/car-rental", wait_until="domcontentloaded")
    create = page.get_by_role("button", name="Thêm mới đơn thuê xe phổ thông", exact=True)
    expect(create).to_be_visible(timeout=20000)
    expect(page.locator(".rental-quick-create")).to_have_count(0)
    expect(page.get_by_role("link", name=re.compile("Kho thuê xe"))).to_have_count(1)
    create.click()
    page.evaluate("""() => { window.contractForm = () => {
        let node = document.querySelector('.modal.show .form');
        while (node && !node.__vue__) node = node.parentElement;
        let vm = node && node.__vue__;
        while (vm && vm.$options.name !== 'OrderUpdate') vm = vm.$parent;
        return vm;
    }; }""")
    page.wait_for_function("contractForm() && contractForm().priceVehicles.length === 1")
    modal = page.locator(".modal.show").filter(has=page.locator(".form"))
    reference = modal.get_by_placeholder("Hệ thống tự cấp mã khi lưu nháp", exact=True)
    expect(reference).to_have_attribute("readonly", "readonly")
    modal.get_by_role("button", name="Tiếp tục: Thông tin khách hàng (Bên B)", exact=True).click()
    modal.get_by_placeholder("Tên khách hàng", exact=True).fill("Khách bổ sung sau")
    modal.get_by_role("button", name="Tiếp tục: Thông tin phương tiện", exact=True).click()
    page.evaluate("""() => {
        const form = contractForm(); form.order.store_id = 1;
        const item = form.order.order_items[0]; item.vehicle_id = 101;
        item.rent_at = new Date('2026-10-03T09:00:00');
        item.return_at = new Date('2026-10-06T09:00:00');
    }""")
    page.wait_for_function("contractForm().order.order_items[0].hiringFee === 600000")
    default_price = modal.locator("#default-unit-price-0")
    applied_price = modal.locator("#applied-unit-price-0")
    expect(default_price).to_be_disabled()
    expect(default_price).to_have_value("200.000 VNĐ")
    if admin:
        expect(applied_price).to_be_enabled()
        applied_price.click()
        page.wait_for_timeout(50)  # v-money restores the caret asynchronously on focus.
        applied_price.fill("150000")
        expect(applied_price).to_have_value("150.000 VNĐ")
        applied_price.press("Tab")
        page.wait_for_function("contractForm().order.order_items[0].hiringFee === 450000")
        expect(default_price).to_have_value("200.000 VNĐ")
        # Leaving the last vehicle input advances the guided form to payment.
        page.wait_for_function("!contractForm().navigatingContractStep")
        if not applied_price.is_visible():
            modal.get_by_role("button", name="Quay lại", exact=True).click()
            expect(applied_price).to_be_visible()
        if output_dir:
            applied_price.scroll_into_view_if_needed()
            page.screenshot(path=str(output_dir / f"rental-pricing-{width}-admin.png"), full_page=True)
        modal.get_by_role("button", name="Dùng giá mặc định", exact=True).click()
        page.wait_for_function("contractForm().order.order_items[0].hiringFee === 600000")
        applied_price.click()
        page.wait_for_timeout(50)
        applied_price.fill("150000")
        expect(applied_price).to_have_value("150.000 VNĐ")
        applied_price.press("Tab")
        page.wait_for_function("contractForm().order.order_items[0].hiringFee === 450000")
    else:
        expect(applied_price).to_be_disabled()
    modal.get_by_role("button", name="Tạo đơn nháp và bổ sung thông tin", exact=True).click()
    expect(modal.get_by_text("Lỗi thử nghiệm: giữ lại thông tin đã nhập.", exact=False).first).to_be_visible()
    assert len(writes) == 1
    assert page.evaluate("contractForm().order.customer_name") == "Khách bổ sung sau"
    page.wait_for_function("!contractForm().loading")
    modal.get_by_role("button", name="Tạo đơn nháp và bổ sung thông tin", exact=True).click()
    page.wait_for_function("contractForm() && contractForm().id === 1001 && !contractForm().loadingComponent")
    expect(modal.get_by_text("Sửa hợp đồng 1001", exact=False)).to_be_visible()
    assert len(writes) == 2
    assert saved["save_as_draft"] is True and saved["order_mode"] == "standard"
    assert not saved["customer_phone"] and not saved["customer_id_card"]
    assert saved["total"] == saved["total_rental_fees"] == (450000 if admin else 600000)
    assert saved["order_items"][0]["substitute_unit_price"] == (150000 if admin else 0)
    assert page.evaluate("contractForm().order.manual_contract_number") == "261003-0001"
    assert page.evaluate("contractForm().order.customer_name") == "Khách bổ sung sau"
    modal.get_by_role("button", name="Tiếp tục: Thông tin khách hàng (Bên B)", exact=True).click()
    expect(modal.locator("#tab-customer")).to_have_attribute("aria-selected", "true")
    expect(modal.locator("#tab-customer")).to_have_class(re.compile(r"\bis-active\b"))
    expect(modal.locator("#tab-customer")).not_to_have_class(re.compile(r"\bis-disabled\b"))
    expect(modal.get_by_placeholder("SĐT khách hàng", exact=True)).to_be_visible()
    expect(modal.get_by_placeholder("SĐT khách hàng", exact=True)).to_have_value("")
    expect(modal.get_by_placeholder("Số CMTND/CCCD", exact=True)).to_have_value("")
    assert page.evaluate("document.documentElement.scrollWidth") <= width + 1
    bounds = modal.locator(".modal-dialog").bounding_box()
    assert bounds["x"] >= 0 and bounds["x"] + bounds["width"] <= width + 1
    if output_dir:
        page.wait_for_timeout(300)  # Allow Element's tab-scroll transition to finish for evidence.
        page.screenshot(path=str(output_dir / f"rental-draft-{width}-{'admin' if admin else 'staff'}.png"), full_page=True)
    assert not errors, errors
    assert not blocked, blocked
    context.close()
    return {"width": width, "role": "admin" if admin else "staff", "passed": True,
            "writes": "intercepted local fixtures only"}


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--base-url", default="http://127.0.0.1:8103")
    parser.add_argument("--output-dir", type=Path)
    args = parser.parse_args()
    if urlparse(args.base_url).hostname not in {"127.0.0.1", "localhost", "::1"}:
        raise ValueError("Only loopback UI builds are allowed")
    if args.output_dir:
        args.output_dir.mkdir(parents=True, exist_ok=True)
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(channel="msedge", headless=True)
        results = [check_draft(browser, args.base_url.rstrip("/"), width, admin, args.output_dir)
                   for width in [1440, 375] for admin in [False, True]]
        browser.close()
    report = json.dumps({"all_passed": True, "cases": results}, ensure_ascii=False, indent=2)
    if args.output_dir:
        (args.output_dir / "ui-results.json").write_text(report, encoding="utf-8")
    print(report)


if __name__ == "__main__":
    main()
