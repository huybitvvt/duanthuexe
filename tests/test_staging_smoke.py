#!/usr/bin/env python3
"""
HIMOTO Fleet Dashboard — Staging API Smoke Test Suite
Verifies live Laravel backend, database connection, JWT authentication,
and JSON contract compliance for all primary business endpoints.

Usage:
    python tests/test_staging_smoke.py --base-url https://himoto-api.onrender.com --email vubathuc@gmail.com --password [PASSWORD]
"""

import argparse
import getpass
import json
import os
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path


class StagingClient:
    def __init__(self, base_url, email, password, output_file=None):
        self.base_url = base_url.rstrip("/")
        self.email = email
        self.password = password
        self.output_file = output_file or Path(__file__).parent / "live-staging-smoke-results.json"
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
            with urllib.request.urlopen(req, timeout=20) as resp:
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
            print(f"    Health check failed (HTTP {status})")
            pass_test = False
        self.results.append({
            "name": "Health Check",
            "endpoint": "/api/health",
            "status": status,
            "pass": pass_test,
            "elapsed_ms": elapsed,
            "service": data.get("service") if isinstance(data, dict) else "unknown",
            "database": data.get("database") if isinstance(data, dict) else "unknown"
        })
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
            token = data.get("access_token") or data.get("token") or (data.get("data", {}).get("token") if isinstance(data.get("data"), dict) else None)
            if token:
                self.token = token
                print("    JWT token acquired successfully.")
                self.results.append({
                    "name": "Login",
                    "endpoint": "/api/auth/login",
                    "status": status,
                    "pass": True,
                    "elapsed_ms": elapsed,
                    "token_acquired": True
                })
                return True

        msg = data.get("error", "Authentication failed") if isinstance(data, dict) else "Login error"
        print(f"    Login failed: {msg} (HTTP {status})")
        self.results.append({
            "name": "Login",
            "endpoint": "/api/auth/login",
            "status": status,
            "pass": False,
            "elapsed_ms": elapsed
        })
        return False

    def verify_token(self):
        print("\n[3] Verifying token via /api/verify-token...")
        status, data, elapsed = self.request("GET", "/api/verify-token")
        print(f"    Status: {status} ({elapsed}ms)")
        pass_test = status == 200 and isinstance(data, dict)
        self.results.append({
            "name": "Verify Token",
            "endpoint": "/api/verify-token",
            "status": status,
            "pass": pass_test,
            "elapsed_ms": elapsed
        })
        return pass_test

    def check_endpoint(self, name, path, check_paginator=False):
        print(f"\n[*] Checking {name} ({path})...")
        status, data, elapsed = self.request("GET", path)
        print(f"    Status: {status} ({elapsed}ms)")
        pass_test = status == 200
        paginator_records = None
        if pass_test and check_paginator:
            target = data.get("data") if isinstance(data, dict) else None
            is_paginator = False
            if isinstance(target, dict) and "data" in target and isinstance(target["data"], list):
                is_paginator = True
                paginator_records = len(target["data"])
                print(f"    Paginator detected: {paginator_records} records on current page.")
            elif isinstance(data, dict) and "data" in data and isinstance(data["data"], list):
                is_paginator = True
                paginator_records = len(data["data"])
                print(f"    Direct paginator/list detected: {paginator_records} records.")
            elif isinstance(data, list):
                is_paginator = True
                paginator_records = len(data)
                print(f"    List detected: {paginator_records} records.")
            pass_test = pass_test and is_paginator

        entry = {
            "name": name,
            "endpoint": path,
            "status": status,
            "pass": pass_test,
            "elapsed_ms": elapsed
        }
        if check_paginator:
            entry["paginator"] = pass_test
            if paginator_records is not None:
                entry["records_page"] = paginator_records

        self.results.append(entry)
        return pass_test

    def run_all(self):
        print("=" * 60)
        print("HIMOTO Fleet Dashboard — Staging API Verification")
        print(f"Target: {self.base_url}")
        print("=" * 60)

        health_ok = self.check_health()
        if not health_ok:
            print("\n[WARNING] Health check failed or returned 503. Database may be offline.")

        if not self.login():
            print("\n[ERROR] Cannot proceed with authenticated tests without login.")
            self.print_summary()
            self.save_results()
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
        self.save_results()
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

    def save_results(self):
        all_passed = all(r.get("pass", False) for r in self.results)
        payload = {
            "target": self.base_url,
            "executed_at": time.strftime("%Y-%m-%dT%H:%M:%S%z"),
            "auth_user": self.email,
            "summary": {
                "all_passed": all_passed,
                "total_checks": len(self.results),
                "passed_checks": sum(1 for r in self.results if r.get("pass")),
                "failed_checks": sum(1 for r in self.results if not r.get("pass"))
            },
            "results": self.results
        }
        try:
            out_p = Path(self.output_file)
            out_p.parent.mkdir(parents=True, exist_ok=True)
            out_p.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
            print(f"\n[Artifact Saved] Test results written to: {out_p}")
        except Exception as err:
            print(f"[Warning] Could not save results to file: {err}")


def main():
    parser = argparse.ArgumentParser(description="Test HIMOTO staging API endpoints.")
    parser.add_argument("--base-url", default=os.environ.get("STAGING_API_URL", "https://himoto-api.onrender.com"), help="Backend URL")
    parser.add_argument("--email", default=os.environ.get("STAGING_ADMIN_EMAIL", "vubathuc@gmail.com"), help="Admin user email")
    parser.add_argument("--password", default=os.environ.get("STAGING_ADMIN_PASSWORD"), help="Admin user password")
    parser.add_argument("--output", default=None, help="Path to save JSON test results")
    args = parser.parse_args()

    password = args.password
    if not password:
        if sys.stdin.isatty():
            password = getpass.getpass("Enter admin password: ")
        else:
            print("ERROR: Password required via --password argument or STAGING_ADMIN_PASSWORD environment variable.")
            sys.exit(1)

    client = StagingClient(args.base_url, args.email, password, output_file=args.output)
    success = client.run_all()
    sys.exit(0 if success else 1)


if __name__ == "__main__":
    main()
