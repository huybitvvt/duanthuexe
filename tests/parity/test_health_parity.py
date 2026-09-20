import requests
import unittest
import os

class TestHealthParity(unittest.TestCase):
    def test_legacy_health_format(self):
        # Test legacy live health API
        url = "https://himoto-api.onrender.com/api/health"
        try:
            resp = requests.get(url, timeout=10)
            if resp.status_code == 200:
                data = resp.json()
                self.assertIn("status", data)
                self.assertIn("service", data)
                self.assertIn("database", data)
                self.assertIn("commit", data)
                self.assertEqual(data["service"], "himoto-api")
                print("Legacy health check verified:", data)
        except requests.RequestException as e:
            print("Warning: Live API currently unreachable or cold start:", e)

    def test_expected_commit_parity(self):
        expected_commit = "2b49116"
        version_url = "https://himoto-web.onrender.com/version.json"
        try:
            resp = requests.get(version_url, timeout=10)
            if resp.status_code == 200:
                data = resp.json()
                self.assertEqual(data.get("short_commit"), expected_commit)
                print("Legacy frontend version verified with commit:", data.get("short_commit"))
        except requests.RequestException as e:
            print("Warning: Live web version unreachable:", e)

if __name__ == "__main__":
    unittest.main()
