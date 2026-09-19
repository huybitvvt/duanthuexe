"""Browser regression for the real Vue/Element search component, with a local API stub.

Run: python tests/test_search_suggestions.py
No application server, credentials, or database required.
"""
import json
import re
from pathlib import Path
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]


def test_search_suggestions():
    source = (ROOT / "resources/js/src/view/components/SearchSuggest.vue").read_text(encoding="utf-8")
    template = re.search(r"<template>(.*?)</template>", source, re.S).group(1)
    script = re.search(r"<script>(.*?)</script>", source, re.S).group(1)
    script = re.sub(r"^import .*?;\s*$", "", script, flags=re.M)
    script = script.replace("export default", "const SearchSuggest =")
    helper = (ROOT / "resources/js/src/core/services/search-suggestions.js").read_text(encoding="utf-8").replace("export function", "function")
    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page(viewport={"width": 390, "height": 844})
        errors = []
        page.on("pageerror", lambda error: errors.append(str(error)))
        page.set_content('<div id="app"></div><button id="outside" style="margin-top:320px">Outside</button>')
        page.add_script_tag(path=str(ROOT / "node_modules/vue/dist/vue.js"))
        page.add_script_tag(path=str(ROOT / "node_modules/element-ui/lib/index.js"))
        page.add_style_tag(path=str(ROOT / "node_modules/element-ui/lib/theme-chalk/index.css"))
        page.add_style_tag(content=re.search(r"<style scoped>(.*?)</style>", source, re.S).group(1))
        page.add_script_tag(content=helper + """
window.calls = [];
const ApiService = { query(endpoint, params) {
  calls.push({ endpoint, params });
  const q = params.keyword;
  return new Promise((resolve, reject) => setTimeout(() => {
    if (q === 'error') { reject(new Error('403')); return; }
    const rows = q === '0968' ? [{ customer_name: 'Đào Văn Tròn', customer_phone: '0968402149' }]
      : q === 'wave' ? [{ vehicle_name: 'Honda Wave 110cc' }]
      : q === 'none' ? [] : [{ customer_name: q + ' result' }];
    resolve({ data: { data: { data: rows } } });
  }, q === 'old' ? 800 : 30));
} };
""" + script + "\nSearchSuggest.template = " + json.dumps(template) + ";" + """
Vue.component('search-suggest', SearchSuggest);
window.app = new Vue({
  el: '#app',
  data: { query: { keyword: '', store_id: 2 }, selected: '', submits: 0 },
  template: '<div><search-suggest v-model="query.keyword" :params="query" endpoint="/api/auth/leads" fields="customer_name,customer_phone,vehicle_name" placeholder="Tên, SĐT, loại xe" clearable @select="selected = $event.value" @submit="submits++" /></div>'
});
""")
        field = page.locator("input")
        options = page.locator('[role="option"]')
        field.fill("0968")
        options.first.wait_for()
        assert options.all_text_contents() == ["0968402149"]
        assert page.evaluate("document.documentElement.scrollWidth <= window.innerWidth")
        assert page.evaluate("calls[0].params.store_id") == 2
        assert page.evaluate("calls[0].params.page") == 1
        field.press("ArrowDown")
        field.press("Enter")
        assert field.input_value() == "0968402149"
        assert page.evaluate("app.selected") == "0968402149"
        assert options.count() == 0
        field.fill("wave")
        options.first.wait_for()
        options.first.click()
        assert field.input_value() == "Honda Wave 110cc"
        field.fill("old")
        page.wait_for_timeout(320)
        field.fill("new")
        page.wait_for_timeout(900)
        assert options.all_text_contents() == ["new result"]
        field.fill("old")
        page.wait_for_timeout(320)
        field.fill("")
        page.wait_for_timeout(900)
        assert options.count() == 0
        field.fill("none")
        page.get_by_text("Không có gợi ý phù hợp").wait_for()
        field.fill("error")
        page.get_by_text("Không tải được gợi ý.", exact=False).wait_for()
        field.press("Enter")
        assert page.evaluate("app.submits") == 1
        field.fill("wave")
        options.first.wait_for()
        field.press("Escape")
        assert options.count() == 0
        field.fill("0968")
        options.first.wait_for()
        page.locator("#outside").click()
        assert options.count() == 0
        assert page.evaluate("""() => {
          const row = {customer: {name: 'Đào Văn Tròn'}, vehicles: [{license:'29A-12345'}]};
          return buildSuggestions({data:{data:[row,row]}}, 'customer.name', 'dao').length === 1
            && buildSuggestions({data:{vehicles:{data:[row]}}}, 'vehicles.license', '29A')[0].value === '29A-12345'
            && buildSuggestions({data:[row]}, 'customer.name', '').length === 0;
        }""")
        assert not errors, errors
        browser.close()
    print("PASS: phone, vehicle, keyboard, mouse, debounce race, clear race, empty/error, Escape, blur, filters, paginator, accents, deduplication")


if __name__ == "__main__":
    test_search_suggestions()
