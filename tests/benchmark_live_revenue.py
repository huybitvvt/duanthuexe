"""Read-only browser timing for the live vehicle-revenue page.

Pass a short-lived JWT through HIMOTO_READONLY_TOKEN. The script never prints
the token, response bodies, or business records.
"""

import json
import os
import time

from playwright.sync_api import sync_playwright


BASE_URL = os.environ.get("HIMOTO_WEB_URL", "https://himoto-web.onrender.com")
TOKEN = os.environ.get("HIMOTO_READONLY_TOKEN")
if not TOKEN:
    raise SystemExit("HIMOTO_READONLY_TOKEN is required")


def measure(page, label, navigate):
    requests = {}
    api_calls = []

    def on_request(request):
        if "vehicles_with_revenue" in request.url:
            requests[request] = time.perf_counter()

    def on_response(response):
        if response.request in requests:
            api_calls.append({
                "http": response.status,
                "milliseconds": round((time.perf_counter() - requests[response.request]) * 1000),
            })

    page.on("request", on_request)
    page.on("response", on_response)
    started = time.perf_counter()
    navigate()
    page.locator(".report-car-rental tbody tr").first.wait_for(state="visible", timeout=45000)
    elapsed_ms = round((time.perf_counter() - started) * 1000)
    rows = page.locator(".report-car-rental tbody tr").count()
    slow_resources = page.evaluate("""() => performance.getEntriesByType('resource')
        .filter(resource => ['script', 'css', 'link'].includes(resource.initiatorType))
        .sort((a, b) => b.duration - a.duration)
        .slice(0, 5)
        .map(resource => ({
            name: new URL(resource.name).pathname.split('/').pop(),
            milliseconds: Math.round(resource.duration),
            transferred_bytes: resource.transferSize
        }))""")
    print(json.dumps({
        "run": label,
        "visible_ms": elapsed_ms,
        "rows": rows,
        "revenue_api": api_calls,
        "slow_resources": slow_resources,
    }))
    page.remove_listener("request", on_request)
    page.remove_listener("response", on_response)


with sync_playwright() as playwright:
    try:
        browser = playwright.chromium.launch(channel="msedge", headless=True)
    except Exception:
        browser = playwright.chromium.launch(headless=True)

    context = browser.new_context()
    context.add_init_script("localStorage.setItem('id_token', %s)" % json.dumps(TOKEN))
    page = context.new_page()
    revenue_url = BASE_URL.rstrip("/") + "/report/vehicle-revenue"

    measure(page, "cold_navigation", lambda: page.goto(revenue_url, wait_until="domcontentloaded", timeout=60000))

    page.goto(BASE_URL.rstrip("/") + "/dashboard", wait_until="domcontentloaded", timeout=60000)
    page.get_by_role("link", name="Doanh thu theo xe").first.wait_for(state="visible", timeout=30000)
    measure(page, "spa_navigation", lambda: page.get_by_role("link", name="Doanh thu theo xe").first.click())

    browser.close()
