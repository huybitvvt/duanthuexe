<template>
    <div class="card card-custom gutter-b">
        <div class="card-header">
            <div class="card-title"><h3 class="card-label">Thu tiền qua QR SePay</h3></div>
            <div class="card-toolbar"><router-link :to="{ name: 'receipt' }" class="btn btn-light">Phiếu thu chi</router-link></div>
        </div>
        <div class="card-body">
            <p class="text-muted">Tạo mã riêng cho từng khoản thu. Chỉ khi tài khoản MB nhận tiền, phiếu thu mới được ghi vào hệ thống.</p>
            <div v-if="error" class="alert alert-danger" role="alert">{{ error }}</div>
            <form class="row align-items-end" @submit.prevent="createPayment">
                <div class="col-md-2 form-group">
                    <label for="sepay-store">Cơ sở</label>
                    <select id="sepay-store" v-model.number="form.store_id" class="form-control" :disabled="!isAdmin" required>
                        <option :value="null">Chọn cơ sở</option>
                        <option v-for="store in stores" :key="store.id" :value="store.id">{{ store.store_name }}</option>
                    </select>
                </div>
                <div class="col-md-2 form-group">
                    <label for="sepay-purpose">Loại khoản thu</label>
                    <select id="sepay-purpose" v-model="form.purpose" class="form-control" required>
                        <option value="general">Phiếu thu chung</option>
                        <option value="deposit">Cọc hợp đồng</option>
                        <option value="rental">Phí thuê</option>
                        <option value="additional_deposit">Cọc bổ sung</option>
                        <option value="extension">Gia hạn</option>
                    </select>
                </div>
                <div class="col-md-2 form-group">
                    <label for="sepay-amount">Số tiền (VNĐ)</label>
                    <input id="sepay-amount" v-model.number="form.amount" class="form-control" type="number" min="1" max="2000000000" step="1" required>
                </div>
                <div class="col-md-3 form-group">
                    <label for="sepay-note">Nội dung khoản thu</label>
                    <input id="sepay-note" v-model.trim="form.note" class="form-control" maxlength="180" required placeholder="Ví dụ: Thu phí thuê xe">
                </div>
                <div v-if="form.purpose !== 'general'" class="col-md-2 form-group">
                    <label for="sepay-order">ID hợp đồng</label>
                    <input id="sepay-order" v-model.number="form.order_id" class="form-control" type="number" min="1" step="1" required @blur="loadOrder">
                </div>
                <div v-if="form.purpose === 'extension'" class="col-md-2 form-group">
                    <label for="sepay-line-item">Xe cần gia hạn</label>
                    <select id="sepay-line-item" v-model.number="form.line_item_id" class="form-control" required>
                        <option :value="null">Chọn xe</option>
                        <option v-for="item in orderInfo ? orderInfo.items : []" :key="item.id" :value="item.id">#{{ item.id }} {{ item.vehicle || 'Xe' }} — trả {{ item.return_at | formatDateTime }}</option>
                    </select>
                </div>
                <div v-if="form.purpose === 'extension'" class="col-md-2 form-group">
                    <label for="sepay-return-at">Ngày trả mới</label>
                    <input id="sepay-return-at" v-model="form.extension_return_at" class="form-control" type="datetime-local" required>
                </div>
                <div class="col-md-2 form-group">
                    <button class="btn btn-primary w-100" type="submit" :disabled="creating || !form.store_id">{{ creating ? 'Đang tạo...' : 'Tạo mã QR' }}</button>
                </div>
            </form>
            <p v-if="orderInfo" class="text-muted">Hợp đồng #{{ orderInfo.id }}: tổng phí thuê {{ money(orderInfo.total) }} VNĐ; cọc {{ orderInfo.created_without_collect_deposit ? 'chưa thu' : 'đã ghi thu' }}; phí thuê {{ orderInfo.created_without_collect_rental_fees ? 'chưa thu' : 'đã ghi thu' }}.</p>

            <div v-if="selected" class="card border mt-4 mb-5">
                <div class="card-body row align-items-center">
                    <div class="col-md-4 text-center">
                        <img v-if="selected.qr_url" :src="selected.qr_url" alt="Mã QR chuyển khoản MBBank" class="img-fluid" style="max-width: 280px;">
                        <p v-else class="text-danger">Tài khoản SePay đã thay đổi. Không dùng mã QR cũ.</p>
                    </div>
                    <div class="col-md-8">
                        <h4>{{ selected.note }}</h4>
                        <p class="mb-1">Loại khoản thu: <strong>{{ purposeLabel(selected.purpose) }}</strong></p>
                        <p v-if="selected.order_id" class="mb-1">Hợp đồng: <strong>#{{ selected.order_id }}</strong></p>
                        <p class="mb-1">Ngân hàng: <strong>MBBank</strong></p>
                        <p class="mb-1">Chủ tài khoản: <strong>{{ selected.account_holder }}</strong></p>
                        <p class="mb-1">Số tài khoản: <strong>{{ selected.account_number }}</strong></p>
                        <p class="mb-1">Số tiền QR: <strong>{{ money(selected.expected_amount) }} VNĐ</strong></p>
                        <p class="mb-1">Nội dung chuyển khoản: <strong>{{ selected.code }}</strong></p>
                        <p class="mb-1">Đã nhận: <strong>{{ money(selected.received_amount) }} VNĐ</strong></p>
                        <p class="mb-0">Trạng thái: <strong>{{ statusLabel(selected.status) }}</strong></p>
                        <small class="text-muted">Khách cần giữ nguyên mã chuyển khoản. Trạng thái cập nhật tự động sau khi SePay gửi thông báo.</small>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0">Yêu cầu thu gần đây</h4>
                <button class="btn btn-light" type="button" @click="refresh">Làm mới</button>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead><tr><th>Mã</th><th>Loại</th><th>Nội dung</th><th>Cần thu</th><th>Đã nhận</th><th>Trạng thái</th><th>Ngày tạo</th></tr></thead>
                    <tbody>
                        <tr v-for="item in requests" :key="item.id">
                            <td><button type="button" class="btn btn-link p-0" @click.stop="selected = item">{{ item.code }}</button></td>
                            <td>{{ purposeLabel(item.purpose) }}</td>
                            <td>{{ item.note }}</td>
                            <td>{{ money(item.expected_amount) }}</td>
                            <td>{{ money(item.received_amount) }}</td>
                            <td>{{ statusLabel(item.status) }}</td>
                            <td>{{ item.created_at | formatDateTime }}</td>
                        </tr>
                        <tr v-if="!requests.length"><td colspan="7" class="text-center">Chưa có yêu cầu thu.</td></tr>
                    </tbody>
                </table>
            </div>
            <div v-if="isAdmin && unmatched.length" class="mt-5">
                <h4>Giao dịch cần đối chiếu</h4>
                <p class="text-muted">Mã không khớp chưa ghi phiếu thu. Khoản có trạng thái cần đối chiếu đã ghi phiếu thu ngân hàng nhưng chưa phân bổ hợp đồng.</p>
                <div class="table-responsive"><table class="table table-bordered table-sm">
                    <thead><tr><th>ID SePay</th><th>Trạng thái</th><th>Mã nhận được</th><th>Số tiền</th><th>Nội dung</th></tr></thead>
                    <tbody><tr v-for="item in unmatched" :key="item.sepay_transaction_id"><td>{{ item.sepay_transaction_id }}</td><td>{{ item.status === 'review' ? 'Cần đối chiếu hợp đồng' : 'Không khớp mã' }}</td><td>{{ item.payment_code || '—' }}</td><td>{{ money(item.amount) }}</td><td>{{ item.content }}</td></tr></tbody>
                </table></div>
            </div>
        </div>
    </div>
</template>

<script>
import { mapGetters } from 'vuex';
import ApiService from '@/core/services/api.service';
import { STORE_GET_ALL } from '@/core/services/store/store.module';
import { SET_BREADCRUMB } from '@/core/services/store/breadcrumbs.module';

const endpoint = '/api/auth/sepay/payment-requests';

export default {
    name: 'SepayPayments',
    data() {
        return {
            stores: [], requests: [], unmatched: [], selected: null,
            form: { store_id: null, purpose: 'general', amount: null, note: '', order_id: null, line_item_id: null, extension_return_at: '' }, orderInfo: null,
            creating: false, error: '', timer: null,
        };
    },
    computed: {
        ...mapGetters(['currentUser']),
        isAdmin() { return this.currentUser && Number(this.currentUser.role_id) === 1; },
    },
    watch: {
        'form.store_id'() { this.selected = null; this.refresh(); },
        'form.order_id'() { this.orderInfo = null; this.form.line_item_id = null; },
        'form.purpose'(value) { if (value === 'general') { this.form.order_id = null; this.orderInfo = null; } },
    },
    async mounted() {
        this.$store.dispatch(SET_BREADCRUMB, [{ title: 'Thu tiền qua QR SePay' }]);
        this.form.store_id = this.currentUser && this.currentUser.store_id ? Number(this.currentUser.store_id) : null;
        try {
            const response = await this.$store.dispatch(STORE_GET_ALL, {});
            this.stores = response.data || [];
        } catch (error) {
            this.error = 'Không tải được danh sách cơ sở.';
        }
        this.refresh();
        this.timer = window.setInterval(() => {
            if (!document.hidden) this.refresh();
        }, 15000);
    },
    beforeDestroy() { if (this.timer) window.clearInterval(this.timer); },
    methods: {
        money(value) { return new Intl.NumberFormat('vi-VN').format(Number(value) || 0); },
        purposeLabel(value) {
            return { general: 'Phiếu thu chung', deposit: 'Cọc', rental: 'Phí thuê', additional_deposit: 'Cọc bổ sung', extension: 'Gia hạn' }[value] || value;
        },
        statusLabel(value) {
            return { pending: 'Chờ chuyển khoản', partial: 'Đã nhận một phần', paid: 'Đã nhận đủ', overpaid: 'Chuyển dư', review: 'Cần đối chiếu' }[value] || value;
        },
        async loadOrder() {
            const id = this.form.order_id;
            if (!id || this.form.purpose === 'general') return;
            try {
                const response = await ApiService.get('/api/auth/sepay/orders', `${id}/options`);
                if (this.form.order_id === id) {
                    this.orderInfo = response.data.data;
                    if (this.form.purpose === 'rental') this.form.amount = Number(this.orderInfo.total);
                }
            } catch (error) {
                this.orderInfo = null;
                this.error = (error.response && error.response.data && error.response.data.message) || 'Không tải được hợp đồng.';
            }
        },
        async refresh() {
            if (!this.form.store_id) return;
            try {
                const response = await ApiService.query(endpoint, { store_id: this.form.store_id });
                this.requests = response.data.data || [];
                if (this.selected) {
                    this.selected = this.requests.find(item => item.id === this.selected.id) || this.selected;
                }
                if (this.isAdmin) {
                    const unmatched = await ApiService.get(`${endpoint}/unmatched`);
                    this.unmatched = unmatched.data.data || [];
                }
                this.error = '';
            } catch (error) {
                this.error = (error.response && error.response.data && error.response.data.message) || 'Không tải được trạng thái SePay.';
            }
        },
        async createPayment() {
            this.creating = true;
            this.error = '';
            try {
                const payload = { ...this.form, order_id: this.form.purpose === 'general' ? null : this.form.order_id || null };
                const response = await ApiService.post(endpoint, payload);
                this.selected = response.data.data;
                this.form.amount = null;
                this.form.note = '';
                this.form.order_id = null;
                this.orderInfo = null;
                this.form.line_item_id = null;
                this.form.extension_return_at = '';
                await this.refresh();
            } catch (error) {
                const body = error.response && error.response.data;
                this.error = (body && (body.message || Object.values(body.errors || {})[0])) || 'Không tạo được mã QR.';
            } finally {
                this.creating = false;
            }
        },
    },
};
</script>
