<template>
    <div v-drag-scroll class="table-responsive rental-table-wrap" role="region" :aria-label="label + ', có thể cuộn ngang'" tabindex="0">
        <table class="table table-bordered table-hover table-vertical-center rental-order-table">
            <thead><tr>
                <th v-if="selectable" scope="col" class="rental-select-col">
                    <input type="checkbox" :checked="allSelected" aria-label="Chọn tất cả đơn trong trang" @change="$emit('select-all', $event.target.checked)">
                </th>
                <th scope="col">Mã đơn</th><th v-if="showCategory" scope="col">Loại hồ sơ</th>
                <th scope="col">Tên khách</th><th scope="col">Số điện thoại</th><th scope="col">Xe</th>
                <th scope="col">Ngày thuê</th><th scope="col">Ngày trả</th><th scope="col">Tổng tiền</th>
                <th scope="col">Trạng thái</th><th scope="col">Hành động</th>
            </tr></thead>
            <tbody>
                <tr v-for="item in orders" :key="item.id">
                    <td v-if="selectable" class="rental-select-col">
                        <input type="checkbox" :checked="Boolean(selected[item.id])" :aria-label="'Chọn đơn ' + (item.contract_number || item.id)" @change="$emit('select', { id: item.id, checked: $event.target.checked })">
                    </td>
                    <th scope="row"><strong>{{ item.contract_number || item.draft_reference || ('#' + item.id) }}</strong><small class="d-block text-muted">Tạo: {{ item.created_at || '—' }}</small></th>
                    <td v-if="showCategory">{{ categoryLabel(item.order_mode) }}</td>
                    <td><strong>{{ item.customer_name || 'Chưa nhập khách hàng' }}</strong><small v-if="item.store" class="d-block text-muted">{{ item.store.store_name }}</small><small v-if="item.note" class="d-block text-muted rental-note" :title="item.note">{{ item.note }}</small></td>
                    <td class="text-nowrap">{{ item.customer_phone || '—' }}</td>
                    <td><div v-for="vehicle in item.vehicles" :key="vehicle.id">{{ vehicle.name }}<small class="d-block text-muted">{{ vehicle.license }}</small></div><small v-if="item.vehicle_exchange_count" class="text-warning">Đã đổi xe {{ item.vehicle_exchange_count }} lần</small></td>
                    <td class="text-nowrap"><div v-for="(line, index) in orderItems(item)" :key="index">{{ line.rent_at | formatDate }}</div><span v-if="!orderItems(item).length">—</span></td>
                    <td class="text-nowrap"><div v-for="(line, index) in orderItems(item)" :key="index">{{ line.return_at | formatDate }}</div><span v-if="!orderItems(item).length">—</span>
                        <button v-if="item.out_date" type="button" class="btn btn-xs btn-light-danger mt-1" @click="$emit('reminder', item)">Quá hạn: {{ item.out_date }}</button>
                    </td>
                    <td class="text-nowrap"><strong>{{ item.total | formatPrice }}</strong><small v-if="item.order_status === 'draft'" class="d-block text-muted">Chưa ghi nhận thu</small><small v-else class="d-block text-muted">Cọc: {{ deposit(item) | formatPrice }}</small></td>
                    <td><span class="font-weight-bold" :class="statusCss[item.order_status]">{{ statuses[item.order_status] || item.order_status }}</span></td>
                    <td><div class="rental-table-actions">
                        <b-dropdown right no-caret size="sm" variant="light" toggle-class="rental-actions-toggle" menu-class="rental-actions-menu">
                            <template #button-content>
                                <i class="fas fa-ellipsis-v" aria-hidden="true"></i><span class="sr-only">Hành động</span>
                            </template>
                            <b-dropdown-item-button @click="$emit('edit', item)">{{ ['cancel_pending_settlement', 'cancelled'].includes(item.order_status) ? 'Lịch sử' : 'Sửa' }}</b-dropdown-item-button>
                            <b-dropdown-item-button @click="$emit('view', item)">Xem</b-dropdown-item-button>
                            <b-dropdown-item-button @click="$emit('print', item)">In</b-dropdown-item-button>
                            <b-dropdown-item-button v-if="item.order_status === 'renting'" @click="$emit('edit', item)">Thu thêm / Trả xe</b-dropdown-item-button>
                            <b-dropdown-item-button v-if="canDelete" button-class="text-danger" @click="$emit('delete', item.id)">Xóa</b-dropdown-item-button>
                        </b-dropdown>
                    </div></td>
                </tr>
                <tr v-if="!orders.length"><td :colspan="columnCount" class="text-center text-muted py-4">{{ emptyMessage }}</td></tr>
            </tbody>
        </table>
    </div>
</template>

<script>
import { ORDER_STATUS_DEFINE, ORDER_STATUS_DEFINE_CSS } from '@/option/orderOption';
export default {
    name: 'RentalOrderTable',
    props: {
        orders: { type: Array, default: () => [] },
        categories: { type: Array, default: () => [] },
        label: { type: String, required: true },
        emptyMessage: { type: String, default: 'Không có đơn thuê phù hợp với bộ lọc.' },
        showCategory: { type: Boolean, default: false },
        selectable: { type: Boolean, default: false },
        canDelete: { type: Boolean, default: false },
        selected: { type: Object, default: () => ({}) },
    },
    data() { return { statuses: ORDER_STATUS_DEFINE, statusCss: ORDER_STATUS_DEFINE_CSS }; },
    computed: {
        allSelected() { return this.orders.length > 0 && this.orders.every(item => this.selected[item.id]); },
        columnCount() { return (this.showCategory ? 10 : 9) + (this.selectable ? 1 : 0); },
    },
    methods: {
        categoryLabel(mode) { return (this.categories.find(category => category.value === (mode || 'standard')) || {}).label || 'Đơn thuê xe phổ thông'; },
        orderItems(item) { return item.orderItems || item.order_items || []; },
        deposit(item) {
            if (item.created_without_collect_deposit) return 0;
            return item.first_deposit_amount ? Number(item.first_deposit_amount) + Number(item.additional_deposit_amount || 0) : Number(item.pid || 0);
        },
    },
};
</script>

<style scoped>
.rental-table-wrap { border-radius: 6px; }
.rental-order-table { margin-bottom: 0; font-size: 13px; }
.rental-order-table thead { background: #e8f0f5; color: #243e50; }
.rental-order-table th, .rental-order-table td { padding: 12px 10px; }
.rental-order-table th { white-space: nowrap; }
.rental-order-table tbody th { font-weight: 400; }
.rental-order-table small { font-size: 11px; line-height: 1.6; }
.rental-select-col { width: 42px; text-align: center; vertical-align: middle; }
.rental-table-actions { display: flex; align-items: center; justify-content: flex-end; gap: 8px; min-width: 0; }
.rental-actions-toggle { width: 34px; height: 34px; padding: 0; border: 1px solid #d6e2eb; color: #526879; }
.rental-actions-toggle:hover, .rental-actions-toggle:focus { color: #243e50; background: #e8f0f5; }
.rental-actions-menu { min-width: 180px; }
.rental-note { max-width: 180px; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
.rental-table-wrap:focus-visible { outline: 3px solid #257bb5; outline-offset: 2px; }
</style>
