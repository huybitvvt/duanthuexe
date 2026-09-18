#!/usr/bin/env python3
"""Browser smoke test for the client-side contract PDF generator."""

from pathlib import Path

from playwright.sync_api import sync_playwright


ROOT = Path(__file__).resolve().parents[1]
BUNDLE = ROOT / "node_modules" / "html2pdf.js" / "dist" / "html2pdf.bundle.min.js"


def main():
    if not BUNDLE.exists():
        raise FileNotFoundError("Run npm install before the PDF smoke test")

    with sync_playwright() as playwright:
        try:
            browser = playwright.chromium.launch(channel="msedge", headless=True)
        except Exception:
            browser = playwright.chromium.launch(headless=True)

        page = browser.new_page(viewport={"width": 1280, "height": 900})
        page.set_content(
            """
            <style>
              body { font-family: Arial, sans-serif; background: #fff; }
              .contract-print-wrapper { width: 285mm; color: #111; background: #fff; }
              .contract-page { width: 285mm; min-height: 190mm; padding: 5mm; box-sizing: border-box; }
              .page-break { page-break-before: always; }
              table { width: 100%; border-collapse: collapse; }
              td { border: 1px solid #222; padding: 8px; }
            </style>
            <div class="contract-print-wrapper">
              <section class="contract-page">
                <h1>HỢP ĐỒNG THUÊ XE HIMOTO</h1>
                <p>Khách hàng: Nguyễn Văn A</p>
                <table><tr><td>Xe</td><td>99AA-123.45</td></tr></table>
              </section>
              <section class="contract-page page-break">
                <h2>BIÊN BẢN BÀN GIAO</h2>
                <p>Nội dung kiểm tra trang thứ hai.</p>
              </section>
            </div>
            """
        )
        page.add_script_tag(path=str(BUNDLE))
        result = page.evaluate(
            """
            async () => {
              const buffer = await window.html2pdf()
                .set({
                  margin: [5, 5, 5, 5],
                  html2canvas: { scale: 1, logging: false, backgroundColor: '#ffffff' },
                  jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' },
                  pagebreak: { mode: ['css', 'legacy'], before: '.page-break' }
                })
                .from(document.querySelector('.contract-print-wrapper'))
                .outputPdf('arraybuffer');
              const bytes = new Uint8Array(buffer);
              return {
                size: bytes.length,
                signature: String.fromCharCode(...bytes.slice(0, 5)),
              };
            }
            """
        )
        browser.close()

    if result["signature"] != "%PDF-" or result["size"] < 5_000:
        raise AssertionError(f"Invalid PDF output: {result}")

    print(f"[PASS] Contract PDF generated in browser ({result['size']} bytes)")


if __name__ == "__main__":
    main()
