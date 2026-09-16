<template>
  <div id="lead-view" class="d-inline">
    <b-modal
      id="modal-lead-view"
      title="Chi tiết đăng ký thuê xe"
      size="xl"
      modal-class="himoto-lead-detail-modal"
      :centered="true"
      :scrollable="true"
      hide-footer
    >
      <section v-if="lead" id="section-lead-view" class="lead-detail-layout">
        <div class="lead-detail-card">
          <div class="lead-section-heading">
            <div>
              <h4>Thông tin đăng ký</h4>
              <span>Mã Lead #{{ lead.id }}</span>
            </div>
            <span :class="['lead-status', status_define_css[lead.status]]">
              {{ lead.status || "Chưa cập nhật" }}
            </span>
          </div>

          <dl class="lead-detail-grid">
            <div>
              <dt>Khách hàng</dt>
              <dd>{{ lead.customer_name || "—" }}</dd>
            </div>
            <div>
              <dt>Số điện thoại</dt>
              <dd>{{ lead.customer_phone || "—" }}</dd>
            </div>
            <div>
              <dt>Loại xe</dt>
              <dd>{{ mapBrand(lead.vehicle_name) || "—" }}</dd>
            </div>
            <div>
              <dt>Địa điểm nhận xe</dt>
              <dd>{{ location(lead) || "—" }}</dd>
            </div>
            <div>
              <dt>Ngày nhận xe</dt>
              <dd>{{ lead.rent_at | formatDate }}</dd>
            </div>
            <div>
              <dt>Ngày trả xe</dt>
              <dd>{{ lead.return_at | formatDate }}</dd>
            </div>
            <div class="full-width">
              <dt>Ghi chú</dt>
              <dd>{{ lead.note || "Chưa có ghi chú" }}</dd>
            </div>
          </dl>
        </div>

        <div class="lead-detail-card">
          <div class="lead-section-heading">
            <div>
              <h4>Lịch sử cập nhật</h4>
              <span>{{ leadLogs.length }} lần thay đổi</span>
            </div>
          </div>

          <div
            v-if="leadLogs.length"
            v-drag-scroll
            class="table-responsive lead-history-table"
            role="region"
            aria-label="Lịch sử cập nhật Lead, có thể kéo ngang bằng chuột"
          >
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th>Mã</th>
                  <th class="min-w-130px">Ngày</th>
                  <th>Người thực hiện</th>
                  <th>Loại chỉnh sửa</th>
                  <th v-if="lead.metadata" class="min-w-300px">Nội dung chỉnh sửa</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in leadLogs" :key="item.id">
                  <td>{{ item.id }}</td>
                  <td>{{ item.created_at | formatDateTime }}</td>
                  <td>{{ (item.user && item.user.name) || "—" }}</td>
                  <td>{{ item.content || "—" }}</td>
                  <td v-if="lead.metadata">
                    <div
                      v-for="(change, index) in formatMetadata(item.metadata)"
                      :key="index"
                      class="history-change"
                    >
                      <strong>{{ leadOptions[change.colName] || change.colName }}:</strong>
                      <span>{{ change.old || "—" }}</span>
                      <span aria-hidden="true">→</span>
                      <span>{{ change.new || "—" }}</span>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="lead-history-empty">Chưa có lịch sử cập nhật.</div>
        </div>
      </section>
    </b-modal>
  </div>
</template>

<script>
import { brands, status_define_css } from "@/option/vehicle";
import { leadOptions } from "@/option/leadOption";

export default {
  name: "LeadView",
  props: {
    lead: { type: Object, default: () => ({}) },
    stores: { type: Array, default: () => [] }
  },
  data() {
    return { brands, status_define_css, leadOptions };
  },
  computed: {
    leadLogs() {
      return this.lead && Array.isArray(this.lead.lead_logs) ? this.lead.lead_logs : [];
    }
  },
  methods: {
    location(item) {
      if (item.store_id) return this.mapStore(item.store_id);
      return item.pickup_location || "";
    },
    mapBrand(id) {
      return this.mapVal(this.brands || [], id, "name");
    },
    mapStore(id) {
      return this.mapVal(this.stores || [], id, "store_name");
    },
    mapVal(items, id, key) {
      if (!items.length) return id;
      const match = items.find(item => item && String(item.id) === String(id));
      return match ? match[key] : id;
    },
    formatMetadata(metadata) {
      try {
        const parsed = typeof metadata === "string" ? JSON.parse(metadata) : metadata;
        return Object.entries(parsed || {})
          .filter(([key, value]) => value && typeof value === "object" && Object.prototype.hasOwnProperty.call(this.leadOptions, key))
          .map(([key, value]) => {
            const change = { colName: key, old: value.old, new: value.new };
            if (key === "store_id") {
              change.old = this.mapStore(change.old);
              change.new = this.mapStore(change.new);
            }
            if (key === "vehicle_name") {
              change.old = this.mapBrand(change.old);
              change.new = this.mapBrand(change.new);
            }
            return change;
          });
      } catch (error) {
        return [];
      }
    }
  }
};
</script>

<style scoped>
.lead-detail-layout { display: grid; gap: 16px; }
.lead-detail-card {
  border: 1px solid #e2e7ee;
  border-radius: 10px;
  background: #fff;
  overflow: hidden;
}
.lead-section-heading {
  min-height: 62px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 13px 18px;
  border-bottom: 1px solid #e8ebef;
  background: #f8fafc;
}
.lead-section-heading h4 { margin: 0; color: #243043; font-size: 16px; font-weight: 700; }
.lead-section-heading span { color: #667085; font-size: 12px; }
.lead-status { padding: 5px 10px; border-radius: 999px; background: #eef2f6; font-weight: 700; }
.lead-detail-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  margin: 0;
}
.lead-detail-grid > div { min-height: 72px; padding: 13px 18px; border-right: 1px solid #edf0f3; border-bottom: 1px solid #edf0f3; }
.lead-detail-grid > div:nth-child(3n) { border-right: 0; }
.lead-detail-grid > div.full-width { grid-column: 1 / -1; border-right: 0; border-bottom: 0; }
.lead-detail-grid dt { margin-bottom: 5px; color: #667085; font-size: 12px; font-weight: 600; }
.lead-detail-grid dd { margin: 0; color: #243043; font-size: 14px; font-weight: 600; overflow-wrap: anywhere; }
.lead-history-table { max-height: 330px; }
.lead-history-table th { position: sticky; top: 0; z-index: 1; background: #fff; }
.history-change { display: grid; grid-template-columns: minmax(110px, auto) 1fr auto 1fr; gap: 8px; padding: 3px 0; }
.lead-history-empty { padding: 24px; color: #667085; text-align: center; }

@media (max-width: 768px) {
  .lead-detail-grid { grid-template-columns: 1fr; }
  .lead-detail-grid > div,
  .lead-detail-grid > div:nth-child(3n) { border-right: 0; }
  .lead-section-heading { align-items: flex-start; }
}
</style>
