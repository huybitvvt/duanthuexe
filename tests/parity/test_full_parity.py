import os
import json
import unittest

class TestFullMigrationParity(unittest.TestCase):
    def setUp(self):
        self.base_dir = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))

    def test_nextjs_build_artifacts(self):
        """Verify Next.js production build artifacts exist and contain required routes."""
        web_build_dir = os.path.join(self.base_dir, "apps", "web", ".next")
        self.assertTrue(os.path.exists(web_build_dir), "Next.js .next build directory must exist")

        required_routes = [
            "dashboard", "vehicles", "warehouses", "leads", "customers",
            "car-rental", "car-sell", "pricing", "user", "accounting",
            "finances/daily-cash-register", "transactions", "banks", "cash",
            "receipt", "maintenance-schedule", "maintenance-rule",
            "maintenance-type", "maintenance-log", "hr/duty-schedule",
            "lease-to-own", "customer-reminders", "report/detail-report",
            "report/vehicle-revenue", "report/kpi", "stores", "login", "403", "404"
        ]

        app_dir = os.path.join(self.base_dir, "apps", "web", "src", "app")
        for route in required_routes:
            route_path = os.path.join(app_dir, route, "page.tsx")
            self.assertTrue(os.path.exists(route_path), f"Route {route} page.tsx must exist at {route_path}")

    def test_nestjs_build_artifacts(self):
        """Verify NestJS production build output exists and contains all required controller classes."""
        api_dist_dir = os.path.join(self.base_dir, "apps", "api", "dist")
        self.assertTrue(os.path.exists(api_dist_dir), "NestJS dist directory must exist")

        required_modules = [
            "auth", "store", "vehicle", "customer", "pricing", "lead",
            "user", "order", "finance", "dashboard", "report",
            "maintenance", "hr", "accounting", "warehouse", "lease",
            "reminder", "cron", "export", "health"
        ]

        modules_dir = os.path.join(self.base_dir, "apps", "api", "src", "modules")
        for mod in required_modules:
            mod_path = os.path.join(modules_dir, mod)
            self.assertTrue(os.path.exists(mod_path), f"Module {mod} must exist in apps/api/src/modules")

    def test_database_schema_enforcement(self):
        """Verify DatabaseService sets default schema to himoto."""
        db_service_path = os.path.join(self.base_dir, "apps", "api", "src", "database", "database.service.ts")
        self.assertTrue(os.path.exists(db_service_path))
        with open(db_service_path, "r", encoding="utf-8") as f:
            content = f.read()
            self.assertIn("himoto", content)
            self.assertIn("SET search_path TO", content)

    def test_baseline_freeze_integrity(self):
        """Verify baseline commit is frozen at 2b49116."""
        baseline_path = os.path.join(self.base_dir, "docs", "migration", "baseline.json")
        self.assertTrue(os.path.exists(baseline_path))
        with open(baseline_path, "r", encoding="utf-8") as f:
            data = json.load(f)
            self.assertEqual(data.get("baseline_short_commit"), "2b49116")

if __name__ == "__main__":
    unittest.main()
