#!/usr/bin/env python3
"""Reference-layout checks against a local build; every API call is intercepted."""
import argparse
import json
import re
from pathlib import Path
from urllib.parse import parse_qs, urlparse
from playwright.sync_api import expect, sync_playwright
from test_contract_form_ui import STAFF, CAPABILITIES, STORES, VEHICLES, PRICES, ELEMENT_THEME


def check_layout(browser, base_url, width, admin, output_dir):
    context = browser.new_context(viewport={"width": width, "height": 1000}, reduced_motion="reduce")
    context.add_init_script("localStorage.setItem('id_token', 'local-fixture-session');")
    user = dict(STAFF, role_id=1 if admin else 3)
    capabilities = ['*'] if admin else CAPABILITIES
    stores = STORES + [{"id": 2, "store_name": "CS 2"}]
    vehicles = VEHICLES + [dict(VEHICLES[0], id=202, license='TEST-202', store_id=2, current_store_id=2)]
    orders = [dict(id=100 + index, contract_number=f'20261002-000{index}',
                   order_mode=mode, customer_name=f'Khách {mode}', customer_phone='0912345678',
                   vehicles=VEHICLES, store=STORES[0], total=200000, first_deposit_amount=1000000,
                   order_status='draft' if mode != 'standard' else 'renting', created_at='02-10-2026 09:00:00',
                   orderItems=[{'rent_at': '2026-10-02 09:00:00', 'return_at': '2026-10-03 09:00:00'}])
              for index, mode in enumerate(['standard', 'draft', 'handover'], start=1)]
    document = {key: {} for key in ['lessor', 'customer', 'signed_date', 'pricing', 'deposit', 'equipment', 'signers', 'return_confirmation']}
    document.update(contract_number='20261002-1001', vehicles=VEHICLES, vehicles_count=1,
                    rent_time={'start': {}, 'end': {}}, is_preview=False)
    submitted, documents, reads, errors, blocked = [], [], [], [], []

    def intercept(route):
        request = route.request
        parsed = urlparse(request.url)
        path = parsed.path
        if '/api/' in path:
            if request.method == 'OPTIONS':
                route.fulfill(status=204, headers={'Access-Control-Allow-Origin': base_url, 'Access-Control-Allow-Headers': 'Authorization, Content-Type', 'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS'})
                return
            if request.method == 'POST' and path == '/api/auth/order/car-rental':
                submitted.append(request.post_data_json)
                if len(submitted) == 1:
                    route.fulfill(status=422, json={'message': 'Không thể lưu', 'errors': {'customer_id_card': ['CCCD kiểm thử bị từ chối.']}}, headers={'Access-Control-Allow-Origin': base_url})
                    return
                payload = {'data': {'id': 1001}, 'message': 'Fixture saved'}
            elif request.method != 'GET':
                blocked.append({'method': request.method, 'path': path})
                route.abort()
                return
            elif path == '/api/verify-token':
                payload = {'user': user, 'capabilities': capabilities}
            elif '/stores' in path:
                payload = {'data': stores}
            elif path == '/api/auth/vehicle/vehicles':
                payload = {'data': vehicles}
            elif path == '/api/auth/priceVehicles':
                payload = {'data': PRICES}
            elif '/get-staff-by-store' in path:
                payload = {'data': [user]}
            elif '/banks' in path:
                payload = {'data': {'data': []}} if path.endswith('/banks') else {'data': []}
            elif path == '/api/auth/order/car-rental':
                query = parse_qs(parsed.query)
                reads.append(query)
                mode = query.get('order_mode', [''])[0]
                data = [order for order in orders if not mode or order['order_mode'] == mode]
                payload = {'data': {'data': data, 'total': len(data), 'last_page': 2, 'current_page': int(query.get('page', ['1'])[0])}}
            elif path.endswith('/document'):
                documents.append(path)
                payload = {'data': document}
            elif '/quick-report' in path:
                payload = {'data': {'total_order': 3}}
            elif '/unique' in path:
                payload = {'data': []}
            else:
                payload = {'data': {'items': [], 'unread_count': 0, 'total': 0}}
            route.fulfill(json=payload, headers={'Access-Control-Allow-Origin': base_url})
        elif parsed.hostname == 'cdn.jsdelivr.net' and path.endswith('/lib/index.min.css'):
            route.fulfill(path=str(ELEMENT_THEME / 'index.css'), content_type='text/css')
        elif parsed.hostname == 'cdn.jsdelivr.net' and path.endswith(('/element-icons.woff', '/element-icons.ttf')):
            route.fulfill(path=str(ELEMENT_THEME / 'fonts' / Path(path).name))
        elif parsed.hostname in {'127.0.0.1', 'localhost', '::1'}:
            route.continue_()
        else:
            route.abort()

    context.route('**/*', intercept)
    page = context.new_page()
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.goto(base_url + '/car-rental', wait_until='domcontentloaded')
    expect(page.locator('#quick-customer')).to_be_visible(timeout=20000)
    expect(page.locator('#quick-vehicle')).to_be_enabled()
    expect(page.get_by_role('link', name=re.compile('Xem kho xe Thuê'))).to_have_count(1)
    category = page.locator('.contract-category-list')
    master = page.locator('.contract-master-list')
    expect(category.locator('tbody tr')).to_have_count(1)
    expect(master.locator('tbody tr')).to_have_count(3)
    for heading in ['Số điện thoại', 'Ngày thuê', 'Ngày trả', 'Tổng tiền']:
        expect(category.get_by_role('columnheader', name=heading, exact=True)).to_be_visible()
    expect(category.get_by_text('02-10-2026', exact=True)).to_be_visible()
    expect(category.get_by_text('03-10-2026', exact=True)).to_be_visible()
    for label, mode in [('Đơn thuê xe nháp', 'draft'), ('Hợp đồng 50cc', 'handover'), ('Đơn thuê xe phổ thông', 'standard')]:
        page.get_by_role('button', name=label, exact=True).click()
        expect(category.locator('tbody')).to_contain_text('Khách ' + mode)
        expect(master.locator('tbody tr')).to_have_count(3)
    assert any('order_mode' not in query for query in reads), 'The summary must request all modes'
    category.locator('.pagination').get_by_text('2', exact=True).click()
    page.wait_for_timeout(100)
    assert any(query.get('order_mode') == ['standard'] and query.get('page') == ['2'] for query in reads)
    assert not any(query.get('page') == ['2'] and 'order_mode' not in query for query in reads)

    if admin:
        expect(page.locator('#quick-store')).to_be_enabled()
        page.locator('#quick-store').select_option('2')
        expect(page.locator('#quick-vehicle option')).to_have_count(2)
        page.locator('#quick-vehicle').select_option('202')
        page.locator('#quick-store').select_option('1')
        expect(page.locator('#quick-vehicle')).to_have_value('')
        expect(master.get_by_role('checkbox', name='Chọn tất cả đơn trong trang')).to_be_visible()
        master.get_by_role('checkbox', name='Chọn tất cả đơn trong trang').check()
        assert all(master.get_by_role('checkbox').nth(i).is_checked() for i in range(4))
        master.get_by_role('checkbox', name='Chọn tất cả đơn trong trang').uncheck()
    else:
        expect(page.locator('#quick-store')).to_be_disabled()
        expect(page.locator('#quick-vehicle option')).to_have_count(2)
        expect(master.get_by_role('button', name='Xóa', exact=True)).to_have_count(0)

    page.locator('#quick-customer').fill('Khách tạo nhanh')
    page.locator('#quick-phone').fill('0912345678')
    page.locator('#quick-vehicle').select_option('101')
    page.locator('#quick-rent').fill('2026-10-02T09:00')
    page.locator('#quick-return').fill('2026-10-03T09:00')
    page.locator('#quick-note').fill('Ghi chú tạo nhanh')
    expect(page.locator('#quick-price')).to_have_value('200.000đ')
    assert page.evaluate('document.documentElement.scrollWidth') <= width + 1
    if output_dir:
        page.screenshot(path=str(output_dir / f'rental-layout-{width}-{"admin" if admin else "staff"}.png'), full_page=True)

    page.get_by_role('button', name='Tạo đơn & In', exact=True).click()
    modal = page.locator('.modal.show').filter(has=page.locator('.form'))
    expect(modal.get_by_text('Bước 1/5:', exact=False)).to_be_visible()
    page.evaluate("""() => { window.contractForm = () => {
        let node = document.querySelector('.modal.show .form');
        while (node && !node.__vue__) node = node.parentElement;
        let vm = node && node.__vue__;
        while (vm && vm.$options.name !== 'OrderUpdate') vm = vm.$parent;
        return vm;
    }; }""")
    page.wait_for_function('contractForm().priceVehicles.length === 1')
    assert page.evaluate('contractForm().order.customer_name') == 'Khách tạo nhanh'
    assert page.evaluate('contractForm().order.note') == 'Ghi chú tạo nhanh'
    assert page.evaluate('contractForm().order.order_items[0].hiringFee') == 200000
    next_step = modal.get_by_role('button', name=re.compile('^Tiếp tục:'))
    next_step.click()
    page.wait_for_function("contractForm().activeContractTab === 'customer'")
    modal.get_by_placeholder('Số CMTND/CCCD', exact=True).fill('123456789')
    for target in ['vehicle', 'payment', 'signing']:
        next_step.click()
        page.wait_for_function('target => contractForm().activeContractTab === target', arg=target)
    modal.get_by_role('button', name='Lưu hợp đồng & In', exact=True).click()
    expect(modal.get_by_text('CCCD kiểm thử bị từ chối.', exact=False).first).to_be_visible()
    assert len(submitted) == 1 and not documents
    assert page.evaluate('contractForm().order.customer_name') == 'Khách tạo nhanh'
    assert page.evaluate('contractForm().order.note') == 'Ghi chú tạo nhanh'
    page.wait_for_function('!contractForm().loading')
    for target in ['vehicle', 'payment', 'signing']:
        next_step.click()
        page.wait_for_function('target => contractForm().activeContractTab === target', arg=target)
    modal.get_by_role('button', name='Lưu hợp đồng & In', exact=True).click()
    expect(page.locator('.modal.show .contract-print-wrapper')).to_be_visible(timeout=10000)
    assert len(submitted) == 2
    assert documents == ['/api/auth/order/car-rental/1001/document']
    assert submitted[1]['total'] == submitted[1]['total_rental_fees'] == 200000
    assert submitted[1]['contract_signer_b_name'] == 'Khách tạo nhanh'
    assert submitted[1]['order_items'][0]['vehicle_id'] == 101
    assert not submitted[1]['order_items'][0]['custom_total_money']
    expect(page.locator('#quick-customer')).to_have_value('')
    assert not errors, errors
    assert not blocked, blocked
    context.close()
    return {'width': width, 'role': 'admin' if admin else 'staff', 'passed': True, 'writes': 'intercepted fixtures only'}


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--base-url', default='http://127.0.0.1:8098')
    parser.add_argument('--output-dir', type=Path)
    args = parser.parse_args()
    if urlparse(args.base_url).hostname not in {'127.0.0.1', 'localhost', '::1'}:
        raise ValueError('Only a loopback UI build is allowed')
    if args.output_dir:
        args.output_dir.mkdir(parents=True, exist_ok=True)
    with sync_playwright() as playwright:
        try:
            browser = playwright.chromium.launch(channel='msedge', headless=True)
        except Exception:
            browser = playwright.chromium.launch(headless=True)
        results = [check_layout(browser, args.base_url.rstrip('/'), width, admin, args.output_dir) for width in [1440, 375] for admin in [False, True]]
        browser.close()
    report = json.dumps({'all_passed': True, 'cases': results}, ensure_ascii=False, indent=2)
    if args.output_dir:
        (args.output_dir / 'layout-results.json').write_text(report, encoding='utf-8')
    print(report)


if __name__ == '__main__':
    main()
