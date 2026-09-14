#!/usr/bin/env python3
"""
HIMOTO Fleet Dashboard — Staging API Smoke Test Suite
Verifies live Laravel backend, database connection, JWT authentication,
and JSON contract compliance for all primary business endpoints.

Usage:
    python tests/test_staging_smoke.py --base-url https://himoto-api.onrender.com --email admin@himoto.vn --password your_password
    python tests/test_staging_smoke.py --base-url http://localhost:8000 --email admin@himoto.vn --password your_password
"""

import argparse
import json
import os
import sys
import time
import urllib.error
import urllib.parse
import urllib.request


class StagingClient:
    def __init__(self, base_url, email, password):
        self.base_url = base_url.rstrip("/")
        self.email = email
        self.password = password
        self.token = None
        self.results = []

    def request(self, method, path, data=None, auth=True):
        url = f"{self.base_url}{path}"
        headers = {
            "Accept": "application/json",
            "Content-Type": "application/json",
        }
        if auth and self.token:
            headers["Authorization"] = f"Bearer {self.token}"

        body = json.dumps(data).encode("utf-8") if data is not None else None
        req = urllib.request.Request(url, data=body, headers=headers, method=method)

        start_time = time.time()
        try:
            with urllib.request.urlopen(req, timeout=15) as resp:
                elapsed = round((time.time() - start_time) * 1000, 2)
                resp_body = resp.read().decode("utf-8")
                try:
                    json_data = json.loads(resp_body)
                except Exception:
                    json_data = resp_body
                return resp.status, json_data, elapsed
        except urllib.error.HTTPError as err:
            elapsed = round((time.time() - start_time) * 1000, 2)
            err_body = err.read().decode("utf-8")
            try:
                json_data = json.loads(err_body)
            except Exception:
                json_data = err_body
            return err.code, json_data, elapsed
        except Exception as exc:
            elapsed = round((time.time() - start_time) * 1000, 2)
            return 0, str(exc), elapsed

    def check_health(self):
        print("\n[1] Checking /api/health...")
        status, data, elapsed = self.request("GET", "/api/health", auth=False)
        print(f"    Status: {status} ({elapsed}ms)")
        if status == 200 and isinstance(data, dict):
            print(f"    Service: {data.get('service')}, Database: {data.get('database')}")
            pass_test = data.get("database") == "ok"
        else:
            print(f"    Failed: {data}")
            pass_test = False
        self.results.append({"name": "Health Check", "status": status, "pass": pass_test, "elapsed_ms": elapsed})
        return pass_test

    def login(self):
        print(f"\n[2] Logging in as {self.email}...")
        status, data, elapsed = self.request(
            "POST",
            "/api/auth/login",
            {"email": self.email, "password": self.password},
            auth=False,
        )
        print(f"    Status: {status} ({elapsed}ms)")
        if status == 200 and isinstance(data, dict):
            # Try finding token
            token = data.get("access_token") or data.get("token") or (data.get("data", {}).get("token") if isinstance(data.get("data"), dict) else None)
            if token:
                self.token = token
                print("    JWT token acquired successfully.")
                self.results.append({"name": "Login", "status": status, "pass": True, "elapsed_ms": elapsed})
                return True
        print(f"    Login failed: {data}")
        self.results.append({"name": "Login", "status": status, "pass": False, "elapsed_ms": elapsed})
        return False

    def verify_token(self):
        print("\n[3] Verifying token via /api/verify-token...")
        status, data, elapsed = self.request("GET", "/api/verify-token")
        print(f"    Status: {status} ({elapsed}ms)")
        pass_test = status == 200 and isinstance(data, dict)
        self.results.append({"name": "Verify Token", "status": status, "pass": pass_test, "elapsed_ms": elapsed})
        return pass_test

    def check_endpoint(self, name, path, check_paginator=False):
        print(f"\n[*] Checking {name} ({path})...")
        status, data, elapsed = self.request("GET", path)
        print(f"    Status: {status} ({elapsed}ms)")
        pass_test = status == 200
        if pass_test and check_paginator:
            # Check if paginator shape matches { current_page, data: [...] } or data: { data: [...] }
            target = data.get("data") if isinstance(data, dict) else None
            is_paginator = False
            if isinstance(target, dict) and "data" in target and isinstance(target["data"], list):
                is_paginator = True
                print(f"    Paginator detected: {len(target['data'])} records on current page.")
            elif isinstance(data, dict) and "data" in data and isinstance(data["data"], list):
                is_paginator = True
                print(f"    Direct paginator/list detected: {len(data['data'])} records.")
            elif isinstance(data, list):
                print(f"    List detected: {len(data)} records.")
                is_paginator = True
            pass_test = pass_test and is_paginator

        self.results.append({"name": name, "path": path, "status": status, "pass": pass_test, "elapsed_ms": elapsed})
        return pass_test

    def run_all(self):
        print("=" * 60)
        print(f"HIMOTO Fleet Dashboard — Staging API Verification")
        print(f"Target: {self.base_url}")
        print("=" * 60)

        health_ok = self.check_health()
        if not health_ok:
            print("\n[WARNING] Health check failed or returned 503. Database may be offline.")

        if not self.login():
            print("\n[ERROR] Cannot proceed with authenticated tests without login.")
            self.print_summary()
            return False

        self.verify_token()
        self.check_endpoint("Stores List", "/api/auth/stores/all")
        self.check_endpoint("Vehicles List", "/api/auth/vehicle/vehicles?page=1", check_paginator=True)
        self.check_endpoint("Customers List", "/api/auth/customers?page=1", check_paginator=True)
        self.check_endpoint("Orders List", "/api/auth/order/car-rental?page=1", check_paginator=True)
        self.check_endpoint("Leads List", "/api/auth/leads?page=1", check_paginator=True)
        self.check_endpoint("Leads Unique Users", "/api/auth/leads/unique-users")
        self.check_endpoint("Maintenance Schedules", "/api/auth/maintenance-schedules?page=1", check_paginator=True)
        self.check_endpoint("Banks List", "/api/auth/banks/all")
        self.check_endpoint("Cash List", "/api/auth/cash/all")
        self.check_endpoint("Dashboard Report", "/api/auth/dashboard/report")

        self.print_summary()
        return all(r.get("pass", False) for r in self.results)

    def print_summary(self):
        print("\n" + "=" * 60)
        print("STAGING API VERIFICATION SUMMARY")
        print("=" * 60)
        all_passed = True
        for r in self.results:
            status_symbol = "[PASS]" if r.get("pass") else "[FAIL]"
            if not r.get("pass"):
                all_passed = False
            print(f"  {status_symbol} {r['name']:<25} | HTTP {r['status']:<3} | {r['elapsed_ms']}ms")
        print("-" * 60)
        if all_passed:
            print("ALL LIVE ENDPOINTS PASSED STAGING ACCEPTANCE.")
        else:
            print("SOME CHECKS FAILED. Please review above logs.")
        print("=" * 60)


def main():
    parser = argparse.ArgumentParser(description="Test HIMOTO staging API endpoints.")
    parser.add_argument("--base-url", default=os.environ.get("STAGING_API_URL", "http://localhost:8000"), help="Backend URL (e.g. https://himoto-api.onrender.com)")
    parser.add_argument("--email", default=os.environ.get("STAGING_ADMIN_EMAIL", "admin@himoto.vn"), help="Admin user email")
    parser.add_argument("--password", default=os.environ.get("STAGING_ADMIN_PASSWORD", "secret"), help="Admin user password")
    args = parser.parse_args()

    client = StagingClient(args.base_url, args.email, args.password)
    success = client.run_all()
    sys.exit(0 if success else 1)


if __name__ == "__main__":
    main()
