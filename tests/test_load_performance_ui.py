#!/usr/bin/env python3
"""Check lazy loading, navigation/auth reuse and vehicle details on a loopback build."""
import argparse
import json
from pathlib import Path
from urllib.parse import urlparse
from playwright.sync_api import expect, sync_playwright
from test_contract_form_ui import STAFF, CAPABILITIES, STORES, VEHICLES, PRICES


def check(browser, base_url, width):
    context = browser.new_context(viewport={'width': width, 'height': 900}, reduced_motion='reduce')
    context.add_init_script("localStorage.setItem('id_token', 'performance-fixture');")
    reads, scripts, writes, errors, missing_assets = [], [], [], [], []
    verification_status = [200]
    detail = dict(VEHICLES[0], store=STORES[0], images=[], maintenance_vehicle=[],
                  maintenance_log=[{'id': 1, 'maintenance_at': '2026-10-01', 'note': 'Lịch sử fixture',
                                    'maintenance_type': {'name': 'Bảo dưỡng fixture'}}],
                  maintenance_schedule=[])

    def intercept(route):
        request, parsed = route.request, urlparse(route.request.url)
        path = parsed.path
        if '/api/' in path:
            if request.method == 'OPTIONS':
                route.fulfill(status=204, headers={'Access-Control-Allow-Origin': base_url,
                                                   'Access-Control-Allow-Headers': 'Authorization, Content-Type'})
                return
            if request.method != 'GET':
                writes.append(path)
                route.abort()
                return
            reads.append(path)
            if path == '/api/verify-token':
                if verification_status[0] != 200:
                    route.fulfill(status=verification_status[0], json={'message': 'fixture failure'})
                    return
                payload = {'user': STAFF, 'capabilities': CAPABILITIES}
            elif path == '/api/auth/vehicle/vehicles/101':
                payload = {'data': detail}
            elif '/stores' in path:
                payload = {'data': STORES}
            elif path == '/api/auth/vehicle/vehicles':
                payload = {'data': VEHICLES} if 'is_all=' in parsed.query else {'data': {'data': VEHICLES, 'last_page': 1, 'total': 1}}
            elif path == '/api/auth/priceVehicles':
                payload = {'data': PRICES}
            elif path == '/api/auth/warehouses/summary':
                payload = {'data': [dict(STORES[0], can_view_details=True)]}
            elif '/warehouses/' in path and '/vehicles' in path:
                payload = {'data': {'vehicles': {'data': VEHICLES, 'total': 1}}}
            elif path in {'/api/auth/order/car-rental', '/api/auth/customers'}:
                payload = {'data': {'data': [], 'last_page': 1, 'total': 0}}
            elif path == '/api/auth/dashboard/overview':
                payload = {'data': {'report': {'total_vehicle': 1}, 'chart': {'labels': [], 'values': []}}}
            elif '/banks' in path:
                payload = {'data': {'data': []}} if path.endswith('/banks') else {'data': []}
            elif '/get-staff-by-store' in path:
                payload = {'data': [STAFF]}
            else:
                payload = {'data': []}
            route.fulfill(json=payload, headers={'Access-Control-Allow-Origin': base_url})
        elif parsed.hostname in {'127.0.0.1', 'localhost', '::1'}:
            if request.resource_type == 'script':
                scripts.append(path)
            route.continue_()
        else:
            route.abort()

    context.route('**/*', intercept)
    page = context.new_page()
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.on('response', lambda response: missing_assets.append(response.url)
            if response.status >= 400 and urlparse(response.url).hostname in {'localhost', '127.0.0.1'}
            and '/api/' not in urlparse(response.url).path else None)
    page.goto(base_url + '/car-rental', wait_until='domcontentloaded')
    create = page.get_by_role('button', name='Thêm mới đơn thuê xe phổ thông', exact=True)
    expect(create).to_be_visible(timeout=20000)
    assert not any('contract-form.' in path or 'contract-print.' in path or 'contract-detail.' in path for path in scripts), scripts
    session = page.evaluate('window.__HIMOTO_STORE__.getters.authSessionId')
    create.click()
    expect(page.locator('.modal.show').get_by_text('Bước 1/5:', exact=False)).to_be_visible()
    assert sum('contract-form.' in path for path in scripts) == 1, scripts
    assert not any('contract-print.' in path for path in scripts), scripts
    page.locator('.modal.show button.close').click()
    expect(page.locator('.modal.show')).to_have_count(0)
    create.click()
    expect(page.locator('.modal.show').get_by_text('Bước 1/5:', exact=False)).to_be_visible()
    assert sum('contract-form.' in path for path in scripts) == 1
    page.locator('.modal.show button.close').click()
    expect(page.locator('.modal.show')).to_have_count(0)

    for target in ['/vehicles', '/customers', '/warehouses', '/dashboard', '/vehicles']:
        print(f'Navigation fixture {width}: {target}', flush=True)
        page.evaluate('path => { window.__HIMOTO_ROUTER__.push(path); }', target)
        page.wait_for_function('path => window.__HIMOTO_ROUTER__.currentRoute.path === path', arg=target)
        page.wait_for_timeout(300)
        assert page.evaluate('window.__HIMOTO_STORE__.getters.authSessionId') == session
    assert reads.count('/api/verify-token') == 1, reads
    assert reads.count('/api/auth/stores/all') == 1, reads
    assert '/api/auth/vehicle/vehicles/101' not in reads
    page.locator('button[title="Xem chi tiết"]').first.click()
    modal = page.locator('#modal-vehicle-details')
    expect(modal).to_be_visible()
    expect(modal.get_by_text('Bảo dưỡng fixture', exact=True)).to_be_visible()
    expect(modal.get_by_text('Lịch sử fixture', exact=True)).to_be_visible()
    assert reads.count('/api/auth/vehicle/vehicles/101') == 1
    modal.locator('button.close').click()
    expect(modal).not_to_be_visible()

    verification_status[0] = 503
    status = page.evaluate("async () => { try { await window.__HIMOTO_STORE__.dispatch('verifyAuth', {force:true}); } catch(error) { return error.status; } }")
    assert status == 503
    assert page.evaluate('window.__HIMOTO_STORE__.getters.currentUser.id') == STAFF['id']
    verification_status[0] = 401
    page.evaluate("() => window.__HIMOTO_STORE__.dispatch('verifyAuth', {force:true})")
    assert not page.evaluate('window.__HIMOTO_STORE__.getters.isAuthenticated')
    assert page.evaluate('window.__HIMOTO_STORE__.state.store.storeList.length') == 0
    page.evaluate("""() => { window.__HIMOTO_ROUTER__.push('/customers').catch(error => {
        if (!error._isRouter || error.type !== 2) throw error;
    }); }""")
    page.wait_for_url('**/login')
    assert not errors, errors
    assert not writes, writes
    assert not missing_assets, missing_assets
    context.close()
    return {'width': width, 'passed': True, 'checks': 'lazy form/print; five transitions/one verification; detail history; 503 retention; 401 logout'}


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--base-url', default='http://127.0.0.1:8098')
    parser.add_argument('--output', type=Path)
    args = parser.parse_args()
    if urlparse(args.base_url).hostname not in {'127.0.0.1', 'localhost', '::1'}:
        raise ValueError('Only loopback builds are allowed')
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(channel='msedge', headless=True)
        results = [check(browser, args.base_url.rstrip('/'), width) for width in [1440, 375]]
        browser.close()
    report = json.dumps({'passed': True, 'cases': results}, ensure_ascii=False, indent=2)
    if args.output:
        args.output.parent.mkdir(parents=True, exist_ok=True)
        args.output.write_text(report, encoding='utf-8')
    print(report)


if __name__ == '__main__':
    main()
