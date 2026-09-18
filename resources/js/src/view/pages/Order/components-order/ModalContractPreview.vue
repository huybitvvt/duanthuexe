<template>
	<b-modal
		id="modal-contract-preview"
		ref="modalContractPreview"
		size="xl"
		hide-footer
		no-close-on-backdrop
		scrollable
		body-class="p-0 modal-preview-body"
		dialog-class="modal-preview-dialog"
		v-model="visible"
		@hidden="onHidden"
		@shown="autoFitToViewport"
	>
		<template #modal-header="{ close }">
			<div class="d-flex justify-content-between align-items-center w-100 preview-header">
				<div class="d-flex align-items-center">
					<h5 class="modal-title font-weight-bolder mb-0 mr-3 text-dark">
						Xem trước hợp đồng thuê xe
					</h5>
					<span v-if="doc && doc.is_preview" class="badge badge-warning font-weight-bold">
						Bản xem trước (Chưa cấp số)
					</span>
					<span v-else-if="doc && doc.contract_number" class="badge badge-success font-weight-bold">
						Số HĐ: {{ doc.contract_number }}
					</span>
				</div>

				<!-- Toolbar Zoom & Print -->
				<div class="preview-toolbar d-flex align-items-center">
					<div class="btn-group btn-group-sm mr-3">
						<button class="btn btn-outline-secondary" @click="zoomOut" :disabled="zoomLevel <= 50" title="Thu nhỏ">
							Thu nhỏ (-)
						</button>
						<span class="btn btn-outline-secondary disabled font-weight-bold" style="min-width: 60px;">
							{{ zoomLevel }}%
						</span>
						<button class="btn btn-outline-secondary" @click="zoomIn" :disabled="zoomLevel >= 150" title="Phóng to">
							Phóng to (+)
						</button>
						<button class="btn btn-outline-secondary" @click="resetZoom" title="Kích thước chuẩn (100%)">
							100%
						</button>
						<button class="btn btn-outline-secondary" @click="fitWidth" title="Vừa chiều ngang màn hình">
							Vừa màn hình
						</button>
					</div>

					<button class="btn btn-sm btn-primary font-weight-bold mr-2" @click="printDocument">
						In hợp đồng
					</button>

					<button
						type="button"
						class="btn btn-sm btn-success font-weight-bold mr-2"
						:disabled="pdfDownloading"
						@click="downloadPdf"
					>
						{{ pdfDownloading ? "Đang tạo PDF..." : "Tải PDF" }}
					</button>

					<button type="button" class="btn btn-sm btn-outline-secondary ml-2 font-weight-bold" @click="close">
						Đóng
					</button>
				</div>
			</div>
		</template>

		<!-- Alert banner -->
		<div v-if="doc && doc.is_preview" class="preview-alert-banner alert alert-custom alert-light-warning fade show m-3 py-2 px-3" role="alert">
			<div class="alert-text small">
				<strong>Lưu ý:</strong> Đây là bản xem trước dựa trên dữ liệu đang nhập trên form. Bản in thử sẽ mang nhãn <em>Bản xem trước - Chưa cấp số</em> và <strong>không làm tiêu thụ số HĐ hoặc tạo giao dịch</strong>. Để cấp số chính thức, hãy bấm nút <strong>Lưu hợp đồng</strong>.
			</div>
		</div>

		<!-- Viewport chứa tài liệu in A4 ngang -->
		<div class="preview-document-viewport" ref="viewport">
			<div class="preview-document-scaler" :style="scalerStyle">
				<ContractPrintDocument v-if="doc" :doc="doc" />
			</div>
		</div>
	</b-modal>
</template>

<script>
import ContractPrintDocument from "./ContractPrintDocument.vue";

export default {
	name: "ModalContractPreview",
	components: {
		ContractPrintDocument,
	},
	props: {
		value: {
			type: Boolean,
			default: false,
		},
		doc: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {
			visible: this.value,
			zoomLevel: 100,
			pdfDownloading: false,
		};
	},
	watch: {
		value(newVal) {
			this.visible = newVal;
			if (newVal) {
				this.$nextTick(() => {
					this.autoFitToViewport();
				});
			}
		},
		visible(newVal) {
			this.$emit("input", newVal);
		},
	},
	computed: {
		scalerStyle() {
			const scale = this.zoomLevel / 100;
			return {
				transform: `scale(${scale})`,
				transformOrigin: "top center",
				transition: "transform 0.15s ease",
			};
		},
	},
	methods: {
		zoomIn() {
			if (this.zoomLevel < 150) {
				this.zoomLevel = Math.min(150, this.zoomLevel + 10);
			}
		},
		zoomOut() {
			if (this.zoomLevel > 50) {
				this.zoomLevel = Math.max(50, this.zoomLevel - 10);
			}
		},
		resetZoom() {
			this.zoomLevel = 100;
		},
		fitWidth() {
			this.autoFitToViewport();
		},
		autoFitToViewport() {
			if (!this.$refs.viewport) return;
			const viewportWidth = this.$refs.viewport.clientWidth - 40;
			// 285mm ~ 1077px tại 96 DPI
			const docWidthPx = 1080;
			if (viewportWidth > 300) {
				const targetScale = Math.min(1.1, Math.max(0.2, viewportWidth / docWidthPx));
				this.zoomLevel = Math.round(targetScale * 100);
			}
		},
		async printDocument() {
			const source = this.$refs.viewport.querySelector('.contract-print-wrapper');
			if (!source) return;
			const frame = document.createElement('iframe');
			frame.title = 'In hợp đồng';
			frame.style.cssText = 'position:fixed;left:-10000px;top:0;width:1120px;height:800px;border:0';
			document.body.appendChild(frame);
			const printDoc = frame.contentDocument;
			printDoc.documentElement.style.background = '#fff';
			printDoc.body.style.background = '#fff';
			const stylesReady = [];
			for (const style of document.querySelectorAll('style,link[rel="stylesheet"]')) {
				const copy = style.cloneNode(true);
				if (copy.tagName === 'LINK') {
					copy.href = style.href;
					stylesReady.push(new Promise(resolve => { copy.onload = resolve; copy.onerror = resolve; }));
				}
				printDoc.head.appendChild(copy);
			}
			printDoc.body.appendChild(source.cloneNode(true));
			await Promise.all(stylesReady);
			if (printDoc.fonts) await printDoc.fonts.ready;
			await Promise.all(Array.from(printDoc.images).map(img => img.complete ? Promise.resolve() : new Promise(resolve => { img.onload = resolve; img.onerror = resolve; })));
			frame.contentWindow.addEventListener('afterprint', () => frame.remove(), { once: true });
			frame.contentWindow.focus();
			frame.contentWindow.print();
		},
		async downloadPdf() {
			const source = this.$refs.viewport
				? this.$refs.viewport.querySelector(".contract-print-wrapper")
				: null;
			if (!source || this.pdfDownloading) return;

			this.pdfDownloading = true;
			const exportHost = document.createElement("div");
			exportHost.setAttribute("aria-hidden", "true");
			exportHost.style.cssText = "position:fixed;left:-100000px;top:0;width:297mm;background:#fff;z-index:-1";
			const clone = source.cloneNode(true);
			clone.style.transform = "none";
			clone.style.margin = "0 auto";
			exportHost.appendChild(clone);
			document.body.appendChild(exportHost);

			try {
				const html2pdf = await this.loadPdfLibrary();
				if (document.fonts) await document.fonts.ready;
				await Promise.all(
					Array.from(clone.images).map((img) =>
						img.complete
							? Promise.resolve()
							: new Promise((resolve) => {
								img.onload = resolve;
								img.onerror = resolve;
							})
					)
				);

				const reference = String(
					this.doc?.contract_number || (this.doc?.is_preview ? "xem-truoc" : "hop-dong")
				)
					.normalize("NFD")
					.replace(/[\u0300-\u036f]/g, "")
					.replace(/[^a-zA-Z0-9_-]+/g, "-")
					.replace(/^-+|-+$/g, "");

				await html2pdf()
					.set({
						margin: [5, 5, 5, 5],
						filename: `hop-dong-${reference || "himoto"}.pdf`,
						image: { type: "jpeg", quality: 0.98 },
						html2canvas: {
							scale: 2,
							useCORS: true,
							logging: false,
							backgroundColor: "#ffffff",
							windowWidth: 1120,
						},
						jsPDF: { unit: "mm", format: "a4", orientation: "landscape" },
						pagebreak: { mode: ["css", "legacy"], before: ".page-break" },
					})
					.from(clone)
					.save();
			} catch (error) {
				this.$message.error("Không thể tạo file PDF. Vui lòng thử lại.");
			} finally {
				exportHost.remove();
				this.pdfDownloading = false;
			}
		},
		loadPdfLibrary() {
			if (window.html2pdf) {
				return Promise.resolve(window.html2pdf);
			}
			if (window.__himotoHtml2PdfPromise) {
				return window.__himotoHtml2PdfPromise;
			}

			window.__himotoHtml2PdfPromise = new Promise((resolve, reject) => {
				const script = document.createElement("script");
				script.src = "/vendor/html2pdf.bundle.min.js?v=0.14.0";
				script.async = true;
				script.onload = () => {
					if (window.html2pdf) {
						resolve(window.html2pdf);
					} else {
						reject(new Error("Thư viện PDF không khởi tạo được"));
					}
				};
				script.onerror = () => reject(new Error("Không tải được thư viện PDF"));
				document.head.appendChild(script);
			}).catch((error) => {
				window.__himotoHtml2PdfPromise = null;
				throw error;
			});

			return window.__himotoHtml2PdfPromise;
		},
		onHidden() {
			this.$emit("hidden");
		},
	},
};
</script>

<style>
.modal-preview-dialog {
	width: calc(100vw - 48px) !important;
	max-width: 95vw !important;
}

.modal-preview-dialog .modal-content {
	min-width: 0;
}

.preview-header {
	padding: 8px 12px;
	gap: 16px;
	min-width: 0;
}

.preview-header > div:first-child {
	flex: 1 1 auto;
	min-width: 260px;
}

.preview-header .modal-title {
	flex: 0 0 auto;
	white-space: nowrap;
}

.preview-toolbar {
	flex: 0 1 auto;
	justify-content: flex-end;
	flex-wrap: wrap;
	gap: 8px;
}

.preview-toolbar .btn-group {
	flex-wrap: wrap;
	margin-right: 0 !important;
}

.modal-preview-body {
	background: #f3f4f6;
	overflow-y: auto;
	max-height: calc(88vh - 70px);
}

.preview-alert-banner {
	margin-bottom: 12px;
}

.preview-document-viewport {
	display: flex;
	justify-content: center;
	padding: 20px 10px 50px 10px;
	overflow-x: auto;
	min-height: 500px;
}

.preview-document-scaler {
	display: inline-block;
}

@media (max-width: 767px) {
	.modal-preview-dialog { width: calc(100vw - 16px) !important; max-width: calc(100vw - 16px) !important; margin: 8px auto; }
	.modal-preview-dialog .preview-header { flex-direction: column; align-items: stretch !important; padding: 0; gap: 12px; }
	.modal-preview-dialog .preview-header > div:first-child { min-width: 0; flex-wrap: wrap; gap: 8px; }
	.modal-preview-dialog .modal-title { flex: 1 0 100%; font-size: 17px; }
	.modal-preview-dialog .preview-toolbar { flex-wrap: wrap; gap: 8px; }
	.modal-preview-dialog .preview-toolbar .btn-group { display: flex; flex: 1 0 100%; margin-right: 0 !important; }
	.modal-preview-dialog .preview-toolbar .btn-group .btn { flex: 1 1 auto; white-space: nowrap; }
	.modal-preview-dialog .preview-toolbar .close { margin-left: auto !important; }
	.modal-preview-dialog .modal-preview-body { max-height: 75vh; }
}
</style>
