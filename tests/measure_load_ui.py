#!/usr/bin/env python3
"""Measure local page loading with deterministic read-only API fixtures."""
import argparse
import gzip
import json
import re
from pathlib import Path
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
from test_contract_form_ui import STAFF, CAPABILITIES, STORES, VEHICLES, PRICES, ELEMENT_THEME


def measure(browser, base_url, route_path):
    context = browser.new_context(viewport={'width': 1440, 'height': 900})
    context.add_init_script("localStorage.setItem('id_token', 'local-fixture-session');")
    api_requests, page_errors, scripts, writes = [], [], [], []

    def intercept(route):
        request = route.request
        parsed = urlparse(request.url)
        path = parsed.path
        if '/api/' in path:
            if request.method == 'OPTIONS':
                route.fulfill(status=204, headers={'Access-Control-Allow-Origin': base_url, 'Access-Control-Allow-Headers': 'Authorization, Content-Type'})
                return
            if request.method != 'GET':
                writes.append(path)
                route.abort()
                return
            api_requests.append(path)
            if path == '/api/verify-token':
                payload = {'user': STAFF, 'capabilities': CAPABILITIES}
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
            elif path == '/api/auth/dashboard/overview':
                payload = {'data': {'report': {'total_vehicle': 1}, 'chart': {'labels': [], 'values': []}}}
            elif path == '/api/auth/order/car-rental' or path == '/api/auth/customers':
                payload = {'data': {'data': [], 'last_page': 1, 'total': 0}}
            elif '/banks' in path:
                payload = {'data': {'data': []}} if path.endswith('/banks') else {'data': []}
            else:
                payload = {'data': []}
            route.fulfill(json=payload, headers={'Access-Control-Allow-Origin': base_url})
        elif parsed.hostname == 'cdn.jsdelivr.net' and path.endswith('/lib/index.min.css'):
            route.fulfill(path=str(ELEMENT_THEME / 'index.css'), content_type='text/css')
        elif parsed.hostname == 'cdn.jsdelivr.net' and path.endswith(('/element-icons.woff', '/element-icons.ttf')):
            route.fulfill(path=str(ELEMENT_THEME / 'fonts' / Path(path).name))
        elif parsed.hostname in {'localhost', '127.0.0.1', '::1'}:
            if request.resource_type == 'script':
                scripts.append(path)
            route.continue_()
        else:
            route.abort()

    context.route('**/*', intercept)
    page = context.new_page()
    page.on('pageerror', lambda error: page_errors.append(str(error)))
    page.goto(base_url + route_path, wait_until='domcontentloaded')
    page.wait_for_selector('.himoto-app-shell', timeout=20000)
    page.wait_for_timeout(1200)
    result = {'route': route_path, 'scripts': scripts, 'api_requests': api_requests, 'page_errors': page_errors,
              'timings': page.evaluate("() => ({dom_content_loaded_ms: performance.getEntriesByType('navigation')[0].domContentLoadedEventEnd, resources: performance.getEntriesByType('resource').length})")}
    assert not writes, writes
    context.close()
    return result


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--base-url', default='http://127.0.0.1:8098')
    parser.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()
    if urlparse(args.base_url).hostname not in {'127.0.0.1', 'localhost', '::1'}:
        raise ValueError('Only loopback builds are allowed')
    root = Path(__file__).resolve().parents[1] / 'static-dist'
    assets = {str(file.relative_to(root)).replace('\\', '/'): {'bytes': file.stat().st_size, 'gzip_bytes': len(gzip.compress(file.read_bytes()))}
              for file in root.rglob('*') if file.is_file() and file.suffix in {'.js', '.css'}}
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(channel='msedge', headless=True)
        results = [measure(browser, args.base_url.rstrip('/'), route) for route in ['/car-rental', '/vehicles', '/customers', '/warehouses', '/dashboard']]
        browser.close()
    for result in results:
        result['script_bytes'] = sum(assets.get(url.lstrip('/'), {}).get('bytes', 0) for url in result['scripts'])
        result['script_gzip_bytes'] = sum(assets.get(url.lstrip('/'), {}).get('gzip_bytes', 0) for url in result['scripts'])
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps({'assets': assets, 'pages': results}, ensure_ascii=False, indent=2), encoding='utf-8')
    print(json.dumps([{key: value for key, value in result.items() if key not in {'scripts', 'api_requests'}} | {'api_count': len(result['api_requests'])} for result in results], ensure_ascii=False, indent=2))


if __name__ == '__main__':
    main()
