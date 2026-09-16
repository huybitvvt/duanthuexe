<template>
  <section class="report-section h-100">
    <div class="report-section-header"><h4>{{ title }}</h4></div>
    <div class="table-responsive">
      <table class="table table-bordered mb-0">
        <thead class="thead-light">
          <tr><th>Nhóm</th><th class="text-right">Lead</th><th class="text-right">Chuyển đổi</th><th class="text-right">Tỷ lệ</th></tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.label">
            <td class="font-weight-bold">{{ row.label }}</td>
            <td class="text-right">{{ row.total }}</td>
            <td class="text-right">{{ statusMode ? '-' : row.converted }}</td>
            <td class="text-right">{{ statusMode ? row.share : row.conversion_rate }}%</td>
          </tr>
          <tr v-if="!rows.length"><td colspan="4" class="text-center text-muted py-4">Chưa có dữ liệu.</td></tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<script>
export default {
  name: "KpiReportTable",
  props: {
    title: { type: String, required: true },
    rows: { type: Array, default: () => [] },
    statusMode: { type: Boolean, default: false },
  },
};
</script>

<style scoped>
.report-section { overflow: hidden; border: 1px solid #e2e7ef; border-radius: 12px; background: #fff; }
.report-section-header { display: flex; align-items: center; min-height: 58px; padding: 14px 18px; border-bottom: 1px solid #edf0f4; background: #f8fafc; }
.report-section-header h4 { margin: 0; font-size: 17px; }
</style>
