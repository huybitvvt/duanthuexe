#!/usr/bin/env python3
"""Destructive CRUD smoke test, restricted to a loopback Laravel instance."""

import argparse
import json
import time
import urllib.error
import urllib.parse
import urllib.request


class LocalCrudClient:
    def __init__(self, base_url, email, password):
        parsed = urllib.parse.urlparse(base_url)
        if parsed.hostname not in {"localhost", "127.0.0.1", "::1"}:
            raise ValueError("CRUD smoke test only accepts a loopback URL")

        self.base_url = base_url.rstrip("/")
        self.email = email
        self.password = password
        self.token = None
        self.results = []

    def request(self, method, path, data=None, expected=(200,)):
        body = json.dumps(data).encode("utf-8") if data is not None else None
        headers = {"Accept": "application/json", "Content-Type": "application/json"}
        if self.token:
            headers["Authorization"] = f"Bearer {self.token}"
        request = urllib.request.Request(
            f"{self.base_url}{path}", data=body, headers=headers, method=method
        )

        try:
            with urllib.request.urlopen(request, timeout=20) as response:
                status = response.status
                payload = json.loads(response.read().decode("utf-8") or "{}")
        except urllib.error.HTTPError as error:
            status = error.code
            raw = error.read().decode("utf-8")
            try:
                payload = json.loads(raw)
            except json.JSONDecodeError:
                payload = raw

        if status not in expected:
            raise AssertionError(f"{method} {path}: HTTP {status}, response={payload}")
        return payload

    @staticmethod
    def data(payload):
        return payload.get("data") if isinstance(payload, dict) else payload

    @staticmethod
    def rows(payload):
        data = payload.get("data") if isinstance(payload, dict) else payload
        if isinstance(data, dict) and isinstance(data.get("data"), list):
            return data["data"]
        return data if isinstance(data, list) else []

    def check(self, name, callback):
        started = time.perf_counter()
        callback()
        elapsed = round((time.perf_counter() - started) * 1000, 2)
        self.results.append({"name": name, "pass": True, "elapsed_ms": elapsed})
        print(f"[PASS] {name} ({elapsed} ms)")

    def login(self):
        payload = self.request(
            "POST",
            "/api/auth/login",
            {"email": self.email, "password": self.password},
        )
        self.token = payload.get("access_token")
        if not self.token:
            raise AssertionError("Login response did not contain access_token")

    def run(self):
        suffix = str(int(time.time() * 1000))
        state = {}
        self.login()

        def customer_flow():
            created = self.data(self.request("POST", "/api/auth/customers", {
                "name": f"CRUD Customer {suffix}",
                "email": f"crud-customer-{suffix}@example.test",
                "phone": f"09{suffix[-8:]}",
                "id_card": f"A{suffix[-11:]}",
                "address": "Local CRUD audit",
                "status": 1,
            }))
            state["customer_id"] = created["id"]
            shown = self.data(self.request("GET", f"/api/auth/customers/{created['id']}"))
            assert shown["name"].startswith("CRUD Customer")
            self.request("PUT", f"/api/auth/customers/{created['id']}", {
                **created,
                "name": f"CRUD Customer Updated {suffix}",
            })
            self.request("DELETE", f"/api/auth/customers/{created['id']}")

        def store_flow():
            created = self.data(self.request("POST", "/api/auth/stores", {
                "store_name": f"CRUD Store {suffix}",
                "store_phone": f"08{suffix[-8:]}",
                "store_address": "Local CRUD audit",
                "status": "opening",
                "kind": "physical",
            }))
            state["store"] = created
            shown = self.data(self.request("GET", f"/api/auth/stores/{created['id']}"))
            assert shown["store_name"] == created["store_name"]
            updated = {**created, "store_name": f"CRUD Store Updated {suffix}"}
            self.request("PUT", f"/api/auth/stores/{created['id']}", updated)

        def bank_flow():
            created = self.data(self.request("POST", "/api/auth/banks", {
                "bank_name": "CRUD Bank",
                "account_number": f"CRUD{suffix}",
                "account_type": 0,
                "owner_type": "company",
                "owner_name": f"CRUD Owner {suffix}",
                "store_id": state["store"]["id"],
                "opening_balance": 100000,
            }))
            state["bank_id"] = created["id"]
            self.request("GET", f"/api/auth/banks/{created['id']}")
            self.request("PUT", f"/api/auth/banks/update/{created['id']}", {
                **created,
                "current_balance": 125000,
                "owner_name": f"CRUD Owner Updated {suffix}",
            })
            self.request("DELETE", f"/api/auth/banks/{created['id']}")

        def cash_flow():
            created = self.data(self.request("POST", "/api/auth/cash", {
                "store_id": state["store"]["id"],
                "status": "Active",
                "opening_balance": 200000,
            }))
            state["cash_id"] = created["id"]
            self.request("GET", f"/api/auth/cash/{created['id']}")
            self.request("PUT", f"/api/auth/cash/update/{created['id']}", {
                **created,
                "current_balance": 225000,
            })
            self.request("DELETE", f"/api/auth/cash/{created['id']}")

        def lead_flow():
            rows = self.data(self.request("POST", "/api/auth/leads", {
                "customer_name": f"CRUD Lead {suffix}",
                "customer_phone": f"07{suffix[-8:]}",
                "store_id": state["store"]["id"],
                "status": "pending",
                "note": "Local CRUD audit",
            }))
            created = rows[0]
            self.request("GET", f"/api/auth/leads/{created['id']}")
            self.request("PUT", f"/api/auth/leads/{created['id']}", {
                **created,
                "status": "contacted",
                "note": "Local CRUD audit updated",
            })
            self.request("POST", f"/api/auth/leads/{created['id']}", {"note": "Audit cleanup"})

        def vehicle_and_maintenance_flow():
            vehicle = self.data(self.request("POST", "/api/auth/vehicle/vehicles/store", {
                "name": f"CRUD Vehicle {suffix}",
                "brand": "honda",
                "type": "xega",
                "year": 2026,
                "store_id": state["store"]["id"],
                "current_store_id": state["store"]["id"],
                "license": f"CRUD-{suffix[-8:]}",
                "chassis": f"CHASSIS-{suffix}",
                "engine": f"ENGINE-{suffix}",
                "status": "ready",
                "cost_price": 10000000,
                "price_range": 200000,
                "price_min": 150000,
                "price_max": 250000,
                "type_of_service_id": 1,
            }))
            state["vehicle"] = vehicle
            self.request("POST", "/api/auth/vehicle/vehicles/update", {
                **vehicle,
                "name": f"CRUD Vehicle Updated {suffix}",
            })

            self.request("POST", "/api/auth/maintenance-types", [{
                "name": f"CRUD Maintenance Type {suffix}",
                "note": "Local CRUD audit",
            }])
            type_rows = self.data(self.request("GET", "/api/auth/maintenance-types?is_all=1"))
            maintenance_type = next(row for row in type_rows if row["name"] == f"CRUD Maintenance Type {suffix}")
            state["maintenance_type"] = maintenance_type
            self.request("POST", "/api/auth/maintenance-types", [{
                **maintenance_type,
                "note": "Local CRUD audit updated",
            }])

            self.request("POST", "/api/auth/maintenance-rules", [{
                "maintenance_type_id": maintenance_type["id"],
                "value": 30,
            }])
            rule_rows = self.rows(self.request("GET", "/api/auth/maintenance-rules"))
            rule = next(
                row for row in rule_rows
                if str(row["maintenance_type_id"]) == str(maintenance_type["id"])
            )
            state["maintenance_rule"] = rule
            self.request("POST", "/api/auth/maintenance-rules", [{**rule, "value": 45}])

            self.request("POST", "/api/auth/maintenance-schedules", {
                "vehicle_id": vehicle["id"],
                "maintenance_type_id": maintenance_type["id"],
                "next_time_manual": "2026-10-01 09:00:00",
            })
            schedule_rows = self.rows(self.request("GET", "/api/auth/maintenance-schedules"))
            schedule = next(
                row for row in schedule_rows
                if str(row["vehicle_id"]) == str(vehicle["id"])
            )
            self.request("POST", "/api/auth/maintenance-schedules", {
                **schedule,
                "next_time_manual": "2026-10-02 09:00:00",
            })
            self.request("DELETE", f"/api/auth/maintenance-schedules/{schedule['id']}")
            self.request("DELETE", f"/api/auth/maintenance-rules/{rule['id']}")
            self.request("DELETE", f"/api/auth/maintenance-types/{maintenance_type['id']}")
            self.request("DELETE", f"/api/auth/vehicle/vehicles/{vehicle['id']}")

        self.check("customer create/show/update/delete", customer_flow)
        self.check("store create/show/update", store_flow)
        self.check("bank create/show/update/delete", bank_flow)
        self.check("cash create/show/update/delete", cash_flow)
        self.check("lead create/show/update/delete", lead_flow)
        self.check("vehicle and maintenance CRUD", vehicle_and_maintenance_flow)
        self.request("DELETE", f"/api/auth/stores/{state['store']['id']}")
        print(json.dumps({"all_passed": True, "checks": self.results}, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--base-url", default="http://127.0.0.1:8088")
    parser.add_argument("--email", default="codex-crud-audit@example.test")
    parser.add_argument("--password", default="AuditOnly-2026!")
    args = parser.parse_args()
    LocalCrudClient(args.base_url, args.email, args.password).run()
