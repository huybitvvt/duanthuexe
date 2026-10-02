"""Inspect coverage claims and reproduce false positives without live requests."""
from collections import Counter
import csv
import importlib.util
import json
from pathlib import Path
import re
import unittest
from unittest.mock import Mock, patch

OUT = Path(__file__).resolve().parent
ROOT = OUT.parents[3]


def main():
    with (ROOT / 'docs/migration/feature-matrix.csv').open(encoding='utf-8', newline='') as file:
        rows = list(csv.DictReader(file))
    ui = [row for row in rows if row['type'] == 'UI_PAGE']
    missing_images = [row['screenshot'] for row in ui if row['screenshot'] and not (ROOT / row['screenshot']).exists()]
    router = (ROOT / 'resources/js/src/router.js').read_text(encoding='utf-8')
    legacy = {'/' + value.lstrip('/') for value in re.findall(r'path:\s*"([^"]+)"', router) if value != '*'}
    app_dir = ROOT / 'apps/web/src/app'
    new = set()
    for file in app_dir.rglob('page.tsx'):
        path = file.parent.relative_to(app_dir).as_posix()
        new.add('/' if path == '.' else '/' + re.sub(r'\[([^]]+)\]', r':\1', path))
    module_spec = importlib.util.spec_from_file_location('review_health_test', ROOT / 'tests/parity/test_health_parity.py')
    module = importlib.util.module_from_spec(module_spec)
    module_spec.loader.exec_module(module)
    outcomes = []
    for name, behavior in [('HTTP_500', {'return_value': Mock(status_code=500)}), ('TIMEOUT', {'side_effect': module.requests.Timeout('isolated review fixture')})]:
        with patch.object(module.requests, 'get', **behavior):
            result = unittest.TestResult()
            unittest.defaultTestLoader.loadTestsFromModule(module).run(result)
            outcomes.append({'condition': name, 'tests': result.testsRun, 'incorrectly_reports_success': result.wasSuccessful()})
    evidence = {
        'reviewed_commit': '7f01121',
        'matrix_rows': len(rows),
        'matrix_types': dict(Counter(row['type'] for row in rows)),
        'rows_marked_next_pass': sum('PASS' in row['next_status'] for row in rows),
        'referenced_screenshots_missing': len(missing_images),
        'missing_named_ui_routes': sorted(legacy - new),
        'health_false_positive_reproduction': outcomes,
    }
    (OUT / 'coverage-evidence.json').write_text(json.dumps(evidence, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    print(json.dumps(evidence, ensure_ascii=True, indent=2))


if __name__ == '__main__':
    main()
