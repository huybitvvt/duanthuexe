"""Local browser regression: actual header components, stubbed store, no database."""
import json
import re
from pathlib import Path
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT.parent / "audit-prototype"


def component(name):
    source = (ROOT / f"resources/js/src/view/layout/himoto/{name}.vue").read_text(encoding="utf-8")
    script = re.search(r"<script>(.*?)</script>", source, re.S).group(1)
    script = re.sub(r"^import .*?;\s*$", "", script, flags=re.M).replace("export default", f"const {name} =")
    template = re.search(r"<template>(.*?)</template>", source, re.S).group(1)
    css = re.search(r"<style scoped>(.*?)</style>", source, re.S).group(1)
    return script + f"\n{name}.template = " + json.dumps(template) + ";", css


with sync_playwright() as p:
    browser = p.chromium.launch()
    page = browser.new_page()
    page.route("http://theme.test/**", lambda route: route.fulfill(body='<div id="app"></div>', content_type="text/html"))
    page.goto("http://theme.test/")
    errors = []
    page.on("pageerror", lambda e: errors.append(str(e)))
    page.add_script_tag(path=str(ROOT / "node_modules/vue/dist/vue.js"))
    page.add_script_tag(content=(ROOT / "resources/js/src/core/services/theme.js").read_text(encoding="utf-8").replace("export function", "function"))
    page.add_script_tag(content="""
const mapGetters = () => ({ currentUser() { return {name:'Quản lý vận hành', role_id:1}; } });
const STORE_GET_ALL = 'stores', SET_SELECTED_STORE_ID = 'set-store', LOGOUT = 'logout';
Vue.prototype.$store = {getters:{},dispatch:() => Promise.resolve({data:[]})};
""")
    for name in ["HeaderPreferences", "HimotoHeader"]:
        script, css = component(name)
        page.add_script_tag(content=script)
        page.add_style_tag(content=css)
    page.add_style_tag(path=str(OUT / "theme-test.css"))
    page.add_style_tag(content="*{box-sizing:border-box}body{margin:0;font:14px Arial}.card{padding:20px}.header-right{display:flex}.d-none{display:none}@media(min-width:769px){.d-md-flex{display:flex}}")
    page.add_script_tag(content="""
Vue.component('himoto-header', HimotoHeader);
window.app = new Vue({el:'#app',template:'<div class="himoto-app-shell"><himoto-header/><div class="card"><p class="text-muted">Thông tin khách hàng</p><input class="form-control" placeholder="Tên khách hàng" /></div></div>'});
""")
    toggle = page.locator(".theme-toggle")
    for width in [390, 768, 1024, 1440]:
        page.set_viewport_size({"width": width, "height": 850})
        assert toggle.is_visible()
        assert page.locator(".header-clock").is_visible()
        assert page.evaluate("document.documentElement.scrollWidth <= innerWidth"), width
        assert page.locator(".text-muted").evaluate("e => getComputedStyle(e).color") == "rgb(36, 36, 36)"
        toggle.click()
        assert page.locator("html").get_attribute("data-theme") == "dark"
        assert page.locator(".card").evaluate("e => getComputedStyle(e).backgroundColor") == "rgb(31, 41, 55)"
        assert page.locator(".text-muted").evaluate("e => getComputedStyle(e).color") == "rgb(226, 232, 240)"
        page.screenshot(path=str(OUT / f"theme-dark-{width}.png"))
        assert page.evaluate("initTheme()") == "dark"
        toggle.click()
        page.screenshot(path=str(OUT / f"theme-light-{width}.png"))
    first = page.locator(".header-clock strong").inner_text()
    page.wait_for_timeout(1100)
    assert first != page.locator(".header-clock strong").inner_text()
    assert page.locator(".header-clock span").inner_text() == page.evaluate("new Date().toLocaleDateString('vi-VN',{timeZone:'Asia/Ho_Chi_Minh',day:'2-digit',month:'2-digit',year:'numeric'})")
    toggle.focus()
    page.keyboard.press("Enter")
    assert page.locator("html").get_attribute("data-theme") == "dark"
    page.evaluate("app.$destroy()")
    assert not errors, errors
    browser.close()
print('PASS: theme toggle, persistence, clock, Vietnam date, keyboard, readable text, no overflow at 390/768/1024/1440px')
