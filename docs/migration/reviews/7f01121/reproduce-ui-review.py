"""Local UI evidence only. All API traffic is intercepted; external traffic is blocked."""
import json
import os
from pathlib import Path
import shutil
import socket
import subprocess
import time
from urllib.parse import urlparse
from urllib.request import urlopen

from playwright.sync_api import sync_playwright

OUT = Path(__file__).resolve().parent
ROOT = OUT.parents[3]
WEB = ROOT / 'apps' / 'web'


def main():
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    base = f'http://127.0.0.1:{port}'
    env = {**os.environ, 'API_PROXY_URL': 'http://127.0.0.1:9', 'NEXT_TELEMETRY_DISABLED': '1'}
    with (OUT / 'next-server.log').open('w', encoding='utf-8') as log:
        process = subprocess.Popen(
            [shutil.which('node'), str(WEB / 'node_modules/next/dist/bin/next'), 'start', '-H', '127.0.0.1', '-p', str(port)],
            cwd=WEB, env=env, stdout=log, stderr=subprocess.STDOUT,
            creationflags=subprocess.CREATE_NO_WINDOW if os.name == 'nt' else 0,
        )
        try:
            deadline = time.monotonic() + 40
            while time.monotonic() < deadline:
                if process.poll() is not None:
                    raise RuntimeError('Local Next server stopped; see next-server.log')
                try:
                    with urlopen(base + '/login', timeout=1):
                        break
                except Exception:
                    time.sleep(0.2)
            else:
                raise RuntimeError('Local Next server did not become ready')
            with sync_playwright() as p:
                try:
                    browser = p.chromium.launch(channel='msedge', headless=True)
                except Exception:
                    browser = p.chromium.launch(headless=True)
                context = browser.new_context(viewport={'width': 1440, 'height': 900}, service_workers='block')
                api_requests, dialogs, page_errors = [], [], []

                def intercept(route):
                    parsed = urlparse(route.request.url)
                    if '/api/' in parsed.path:
                        api_requests.append({'method': route.request.method, 'path': parsed.path})
                        route.fulfill(status=503, content_type='application/json', body='{"message":"isolated review: API unavailable"}')
                    elif parsed.hostname == '127.0.0.1' and parsed.port == port:
                        route.continue_()
                    else:
                        route.abort()

                context.route('**/*', intercept)
                page = context.new_page()
                page.on('pageerror', lambda err: page_errors.append(str(err)))

                def dialog_handler(dialog):
                    dialogs.append(dialog.message)
                    dialog.accept()

                page.on('dialog', dialog_handler)
                missing = {}
                for route in ['/customers-create', '/customers-update/1', '/receipt/create']:
                    response = page.goto(base + route, wait_until='networkidle')
                    missing[route] = response.status

                page.goto(base + '/finances/daily-cash-register', wait_until='networkidle')
                before_count = len(api_requests)
                page.get_by_role('button', name='Chốt két ngày', exact=True).click()
                page.get_by_role('button', name='Xác nhận chốt két', exact=True).click()
                page.get_by_role('button', name='Mở lại két', exact=True).wait_for()
                cash_closed = 'ĐÃ CHỐT KÉT' in page.locator('body').inner_text()
                cash_calls = len(api_requests) - before_count
                page.screenshot(path=str(OUT / 'cash-register-false-success.png'), full_page=True)
                page.reload(wait_until='networkidle')
                cash_lost = page.get_by_role('button', name='Chốt két ngày', exact=True).is_visible()

                page.goto(base + '/car-rental', wait_until='networkidle')
                sample_visible = 'HD-202609-042' in page.locator('body').inner_text()
                page.get_by_role('button', name='Thêm mới hợp đồng', exact=True).click()
                page.get_by_placeholder('Nguyễn Văn A', exact=True).fill('Review synthetic customer')
                page.get_by_placeholder('0912345678', exact=True).fill('0900000000')
                page.get_by_placeholder('Honda Vision 2023', exact=True).fill('Review synthetic vehicle')
                page.get_by_placeholder('29B1-12345', exact=True).fill('TEST-001')
                dates = page.locator('input[type="datetime-local"]')
                dates.nth(0).fill('2026-09-20T08:00')
                dates.nth(1).fill('2026-09-21T08:00')
                page.locator('button[type="submit"]').click()
                page.get_by_placeholder('Nguyễn Văn A', exact=True).wait_for(state='hidden')
                failed_save_closed = not page.get_by_placeholder('Nguyễn Văn A', exact=True).is_visible()
                page.screenshot(path=str(OUT / 'rental-api-down-sample-data.png'), full_page=True)
                browser.close()
                result = {
                    'reviewed_commit': '7f01121',
                    'isolation': 'Local production Next build, anonymous browser; all API requests receive synthetic 503; external requests blocked',
                    'font_note': 'External fonts blocked; screenshots demonstrate behavior, not pixel parity against baseline',
                    'missing_routes_http': missing,
                    'cash_register': {'showed_closed': cash_closed, 'api_requests_for_close': cash_calls, 'closed_state_lost_on_reload': cash_lost},
                    'rental': {'sample_data_visible_with_api_503': sample_visible, 'create_modal_closed_after_api_503': failed_save_closed},
                    'dialogs': dialogs,
                    'api_requests': api_requests,
                    'page_errors': page_errors,
                }
                (OUT / 'ui-evidence.json').write_text(json.dumps(result, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
                print(json.dumps(result, ensure_ascii=True, indent=2))
        finally:
            if process.poll() is None:
                process.terminate()
                try:
                    process.wait(timeout=10)
                except subprocess.TimeoutExpired:
                    process.kill()
                    process.wait(timeout=10)


if __name__ == '__main__':
    main()
