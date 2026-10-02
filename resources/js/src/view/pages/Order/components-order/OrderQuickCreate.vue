<template>
    <section class="rental-quick-create" aria-labelledby="quick-order-heading">
        <div class="quick-order-heading">
            <h4 id="quick-order-heading">{{ mode === 'draft' ? 'Tạo đơn thuê nháp' : mode === 'handover' ? 'Tạo hồ sơ 50cc' : 'Tạo đơn thuê mới (in ngay)' }}</h4>
            <span>Mã đơn: hệ thống tự cấp khi lưu</span>
        </div>
        <p v-if="error" class="alert alert-danger" role="alert">{{ error }} <button type="button" class="btn btn-sm btn-link" @click="loadVehicles">Thử lại</button></p>
        <form class="quick-order-grid" @submit.prevent="continueOrder">
            <div class="quick-order-field">
                <label for="quick-customer">Tên khách hàng <span v-if="mode === 'standard'">*</span></label>
                <input id="quick-customer" v-model.trim="form.customer_name" class="form-control" autocomplete="name" :required="mode === 'standard'" placeholder="Tên khách hàng">
            </div>
            <div class="quick-order-field">
                <label for="quick-phone">Số điện thoại <span v-if="mode === 'standard'">*</span></label>
                <input id="quick-phone" v-model.trim="form.customer_phone" class="form-control" type="tel" autocomplete="tel" :required="mode === 'standard'" placeholder="Số điện thoại">
            </div>
            <div class="quick-order-field">
                <label for="quick-store">Cửa hàng <span v-if="mode === 'standard'">*</span></label>
                <select id="quick-store" v-model.number="form.store_id" class="form-control" :required="mode === 'standard'" :disabled="!canChooseStore">
                    <option value="">Chọn cửa hàng</option>
                    <option v-for="store in availableStores" :key="store.id" :value="store.id">{{ store.store_name }}</option>
                </select>
            </div>
            <div class="quick-order-field">
                <label for="quick-vehicle">Xe bắt thuê <span v-if="mode === 'standard'">*</span></label>
                <select id="quick-vehicle" v-model.number="form.vehicle_id" class="form-control" :required="mode === 'standard'" :disabled="loading">
                    <option value="">{{ loading ? 'Đang tải xe...' : 'Chọn xe đang sẵn sàng' }}</option>
                    <option v-for="vehicle in availableVehicles" :key="vehicle.id" :value="vehicle.id">{{ vehicle.name }} — {{ vehicle.license }}</option>
                </select>
            </div>
            <div class="quick-order-field">
                <label for="quick-rent">Ngày thuê <span v-if="mode === 'standard'">*</span></label>
                <input id="quick-rent" v-model="form.rent_at" class="form-control" type="datetime-local" :required="mode === 'standard'">
            </div>
            <div class="quick-order-field">
                <label for="quick-return">Ngày trả <span v-if="mode === 'standard'">*</span></label>
                <input id="quick-return" v-model="form.return_at" class="form-control" type="datetime-local" :min="form.rent_at || undefined" :required="mode === 'standard'">
            </div>
            <div class="quick-order-field">
                <label for="quick-price">Giá tiền (tạm tính)</label>
                <input id="quick-price" class="form-control" :value="estimatedFee === null ? 'Chưa có giá phù hợp' : formatPrice(estimatedFee)" readonly aria-describedby="quick-order-help">
            </div>
            <div class="quick-order-field quick-order-note">
                <label for="quick-note">Ghi chú</label>
                <input id="quick-note" v-model.trim="form.note" class="form-control" placeholder="Ghi chú cho đơn thuê">
            </div>
            <button type="submit" class="btn btn-success font-weight-bold quick-order-submit" :disabled="loading || Boolean(error)">
                {{ mode === 'draft' ? 'Tạo đơn nháp & In' : mode === 'handover' ? 'Tạo hồ sơ 50cc & In' : 'Tạo đơn & In' }}
            </button>
        </form>
        <p id="quick-order-help" class="quick-order-help">Giá thuê tự tính theo bảng giá. Bổ sung thông tin khách và khoản thu ở các bước tiếp theo để lưu và mở bản in.</p>
        <p v-if="validationMessage" class="text-danger mb-0 mt-2" role="alert">{{ validationMessage }}</p>
    </section>
</template>

<script>
import moment from 'moment-timezone';
import { mapGetters } from 'vuex';
import { VEHICLE_GET_ALL, PRICE_VEHICLES_INDEX } from '@/core/services/store/vehicle.module';
import { getApiMessage } from '@/utils/apiErrorHandler';
import { formatPrice } from '@/filters';

export default {
    name: 'OrderQuickCreate',
    props: {
        mode: { type: String, default: 'standard' },
        stores: { type: Array, default: () => [] },
    },
    data() {
        return {
            form: this.emptyForm(), vehicles: [], prices: [], loading: false,
            error: '', validationMessage: '',
        };
    },
    computed: {
        ...mapGetters(['currentUser', 'capabilities']),
        canChooseStore() {
            return this.capabilities.includes('*') || Number(this.currentUser?.role_id) === 1;
        },
        availableStores() {
            return this.canChooseStore ? this.stores : this.stores.filter(store => Number(store.id) === Number(this.currentUser?.store_id));
        },
        availableVehicles() {
            return this.vehicles.filter(vehicle => Number(vehicle.current_store_id || vehicle.store_id) === Number(this.form.store_id));
        },
        estimatedFee() {
            const vehicle = this.availableVehicles.find(item => Number(item.id) === Number(this.form.vehicle_id));
            const hours = Math.floor((new Date(this.form.return_at) - new Date(this.form.rent_at)) / 3600000);
            if (!vehicle || !Number.isFinite(hours) || hours <= 0) return null;
            const remaining = hours % 24;
            const days = Math.floor(hours / 24) + (remaining >= 8 ? 1 : 0);
            const price = this.prices.find(item => item.price_type === 'day' && item.type === vehicle.type
                && Number(item.from_year) <= Number(vehicle.year) && Number(item.to_year) >= Number(vehicle.year)
                && Number(item.from_date) <= days && Number(item.to_date) >= days);
            if (!price) return null;
            const hourlyRate = { xeso: 15000, xega: 15000, xecon: 25000, sh: 35000 }[vehicle.type] || 0;
            return Number(price.price) * days + (remaining > 0 && remaining < 8 ? remaining * hourlyRate : 0);
        },
    },
    watch: {
        availableStores: { immediate: true, handler(stores) {
            if (stores.length === 1 && !this.form.store_id) this.form.store_id = stores[0].id;
        } },
        'form.store_id'() {
            if (!this.availableVehicles.some(vehicle => Number(vehicle.id) === Number(this.form.vehicle_id))) this.form.vehicle_id = '';
        },
        mode() { this.validationMessage = ''; },
    },
    created() { this.loadVehicles(); },
    methods: {
        formatPrice,
        emptyForm() {
            return { customer_name: '', customer_phone: '', store_id: '', vehicle_id: '',
                rent_at: moment().seconds(0).milliseconds(0).format('YYYY-MM-DDTHH:mm'),
                return_at: moment().seconds(0).milliseconds(0).add(1, 'day').format('YYYY-MM-DDTHH:mm'), note: '' };
        },
        async loadVehicles() {
            this.loading = true;
            this.error = '';
            try {
                const [vehicles, prices] = await Promise.all([
                    this.$store.dispatch(VEHICLE_GET_ALL, { is_all: true, status: 'ready', compact: 1 }),
                    this.$store.dispatch(PRICE_VEHICLES_INDEX, {}),
                ]);
                this.vehicles = vehicles.data || [];
                this.prices = prices.data || [];
            } catch (error) { this.error = getApiMessage(error, 'Không thể tải xe và bảng giá.'); }
            finally { this.loading = false; }
        },
        continueOrder() {
            this.validationMessage = '';
            if (this.loading || this.error) return;
            if (this.form.rent_at && this.form.return_at && new Date(this.form.return_at) <= new Date(this.form.rent_at)) {
                this.validationMessage = 'Ngày trả phải sau ngày thuê.';
                return;
            }
            if (this.mode === 'standard' && !(this.estimatedFee > 0)) {
                this.validationMessage = 'Chưa có giá niêm yết phù hợp cho xe và thời gian thuê này.';
                return;
            }
            this.$emit('continue', { ...this.form });
        },
        reset() {
            const storeId = this.form.store_id;
            this.form = { ...this.emptyForm(), store_id: storeId };
            this.validationMessage = '';
        },
    },
};
</script>

<style scoped>
.rental-quick-create { margin: 18px 0; padding: 18px; border: 1px solid #d6e2eb; border-radius: 8px; background: #f8fbfd; }
.quick-order-heading { display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }
.quick-order-heading h4 { margin: 0; font-size: 16px; font-weight: 700; text-transform: uppercase; }
.quick-order-heading span, .quick-order-help { color: #526879; font-size: 12px; }
.quick-order-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; align-items: end; }
.quick-order-field { min-width: 0; }
.quick-order-field label { display: block; margin-bottom: 5px; font-weight: 600; color: #243e50; }
.quick-order-field label span { color: #b42318; }
.quick-order-field .form-control { min-width: 0; width: 100%; }
.quick-order-note { grid-column: span 2; }
.quick-order-submit { grid-column: span 2; min-height: 40px; }
.quick-order-help { margin: 12px 0 0; }
.quick-order-grid :focus-visible { outline: 3px solid #257bb5; outline-offset: 2px; }
@media (max-width: 1100px) { .quick-order-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .quick-order-submit { grid-column: auto; } .quick-order-note { grid-column: auto; } }
@media (max-width: 575px) { .rental-quick-create { padding: 14px; } .quick-order-grid { grid-template-columns: minmax(0, 1fr); } }
</style>
