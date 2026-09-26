<template id="order-show">
    <div>
        <div class="order-show-toolbar" v-if="order">
            <div>
                <span :class="order ? ORDER_STATUS_DEFINE_CSS[order.order_status] : ''
                    ">{{
                        order ? ORDER_STATUS_DEFINE[order.order_status] : ""
                    }}</span>
                <h4 class="order-show-title">
                    Hợp đồng #{{ order.id }}
                    <span v-if="order.contract_number" class="badge badge-success ml-2 font-weight-bolder" style="font-size: 13px;">
                        Số HĐ: {{ order.contract_number }}
                    </span>
                </h4>
            </div>
            <div class="d-flex flex-wrap justify-content-end order-show-actions">
                <router-link
                    :to="orderWarehouseLink"
                    class="btn btn-sm btn-outline-warning font-weight-bold"
                    title="Mở kho xe thuê / điều chuyển"
                >
                    <i class="fas fa-warehouse mr-1"></i>Kho xe Thuê &rarr;
                </router-link>
                <button
                    v-if="currentVehicle"
                    type="button"
                    class="btn btn-sm btn-outline-primary font-weight-bold"
                    @click="openWarehouseForCurrentVehicle"
                >
                    Mở Kho xe
                </button>
                <button
                    v-if="currentVehicle && order.order_status === 'renting'"
                    type="button"
                    class="btn btn-sm btn-warning font-weight-bold"
                    @click="openVehicleExchange"
                >
                    Đổi xe
                </button>
                <button type="button" class="btn btn-sm btn-info font-weight-bold" @click="printContract">
                    In / Tải PDF
                </button>
            </div>
        </div>
        <el-tabs v-model="activeShowTab" type="border-card" class="contract-form-tabs mb-4">
            <el-tab-pane label="Thông tin phương tiện" name="vehicle">
                <table class="table table-bordered" v-if="order">
                    <tbody v-for="(item, index) in displayOrderItems" :key="index">
                        <tr class="text-primary">
                            <td>Thuê xe</td>
                            <td>
                                <p class="font-weight-bold mb-1">
                                     {{ item.vehicle ? item.vehicle.name : "" }} ({{
                                         item.vehicle ? item.vehicle.license : ""
                                    }})
                                </p>
                            </td>
                        </tr>
                        <tr v-if="item.driver_name || item.driver_license_number">
                            <td>Người lái xe</td>
                            <td>
                                <div><strong>Họ tên:</strong> {{ item.driver_name || 'Theo tên khách thuê' }}</div>
                                <div v-if="item.driver_license_number">
                                    <strong>GPLX:</strong> {{ item.driver_license_number }}
                                    <span v-if="item.driver_license_issued_on"> (Cấp ngày: {{ item.driver_license_issued_on | formatDate }})</span>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>Tại cửa hàng</td>
                            <td>{{ displayStoreName }}</td>
                        </tr>
                        <tr>
                            <td>Thuê lúc</td>
                            <td>{{ (item.rent_at) | formatDateTime }}</td>
                        </tr>
                        <tr>
                            <td>Hẹn trả</td>
                            <td>{{ (item.return_at) | formatDateTime }}</td>
                        </tr>
                        <tr>
                            <td>Thời gian trả</td>
                            <td>{{ displayCompletedAt(item) }}</td>
                        </tr>
                        <tr>
                            <td>Phụ kiện mượn</td>
                            <td>
                                <span>Mũ BH: <strong>{{ item.borrow_hats || 0 }}</strong> cái</span>
                                <span class="ml-4">Áo mưa: <strong>{{ item.borrow_raincoats || 0 }}</strong> cái</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </el-tab-pane>

            <el-tab-pane label="Thông tin hợp đồng" name="contract">
                <table class="table table-bordered" v-if="order">
                    <tbody>
                        <tr v-if="displayContractSignedOn">
                            <td>Ngày ký HĐ</td>
                            <td>{{ displayContractSignedOn | formatDate }}</td>
                        </tr>
                        <tr v-if="displayResponsibleUser && displayResponsibleUser.name">
                            <td>Phụ trách HĐ</td>
                            <td>{{ displayResponsibleUser.name }}</td>
                        </tr>
                        <tr v-if="displayAuthorization.date || displayAuthorization.party_name">
                            <td>HĐ ủy quyền</td>
                            <td>Ngày {{ displayAuthorization.date | formatDate }} - Bên {{ displayAuthorization.party_name }}</td>
                        </tr>
                        <tr v-if="displayCollateralDescription">
                            <td>Tài sản thế chấp</td>
                            <td>{{ displayCollateralDescription }}</td>
                        </tr>
                        <tr v-if="displaySigners.signer_a_name || displaySigners.signer_b_name">
                            <td>Đại diện ký</td>
                            <td>
                                <div v-if="displaySigners.signer_a_name">Bên A: {{ displaySigners.signer_a_name }}</div>
                                <div v-if="displaySigners.signer_b_name">Bên B: {{ displaySigners.signer_b_name }}</div>
                            </td>
                        </tr>
                        <tr v-if="displayReturnConfirmation.signer_a_name || displayReturnConfirmation.signer_b_name || displayReturnConfirmation.additional_note">
                            <td>Xác nhận trả xe</td>
                            <td>
                                <div v-if="displayReturnConfirmation.signer_a_name">Bên A nhận: {{ displayReturnConfirmation.signer_a_name }}</div>
                                <div v-if="displayReturnConfirmation.signer_b_name">Bên B trả: {{ displayReturnConfirmation.signer_b_name }}</div>
                                <div v-if="displayReturnConfirmation.additional_note">Ghi chú: {{ displayReturnConfirmation.additional_note }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td>Ghi chú</td>
                            <td>{{ order.note ? order.note : "Chưa có ghi chú" }}</td>
                        </tr>
                        <tr>
                            <td>Nguồn lead</td>
                            <td>
                                <template v-if="order.leads && order.leads.length">
                                    <span v-for="lead in order.leads" :key="lead.id">
                                        {{ lead.user ? lead.user.name : 'Landing page Himoto' }}
                                    </span>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </el-tab-pane>

            <el-tab-pane label="Thông tin khách hàng (Bên B)" name="customer">
                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <td>Tên khách hàng</td>
                            <td>{{ displayCustomer ? displayCustomer.name : "" }}</td>
                        </tr>
                        <tr>
                            <td>Số điện thoại</td>
                            <td>{{ displayCustomer ? displayCustomer.phone : "" }}</td>
                        </tr>
                        <tr>
                            <td>Số CMTND/CCCD</td>
                            <td>
                                <div>{{ displayCustomer ? displayCustomer.id_card : "" }}</div>
                                <div v-if="displayCustomer && (displayCustomer.id_card_issued_on || displayCustomer.id_card_issued_by)" class="text-muted small mt-1">
                                    <span v-if="displayCustomer.id_card_issued_on">Cấp ngày: {{ displayCustomer.id_card_issued_on | formatDate }}</span>
                                    <span v-if="displayCustomer.id_card_issued_by"> - Nơi cấp: {{ displayCustomer.id_card_issued_by }}</span>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="displayCustomer && displayCustomer.relatives && displayCustomer.relatives.length">
                            <td>Người thân</td>
                            <td>
                                <div v-for="(rel, rk) in displayRelatives" :key="rk">
                                    {{ rel.name }} <span v-if="rel.relationship">({{ rel.relationship }})</span>: {{ rel.phone }}
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>Địa chỉ</td>
                            <td>{{ displayCustomer ? displayCustomer.address : "" }}</td>
                        </tr>
                    </tbody>
                </table>
            </el-tab-pane>

            <el-tab-pane label="Chi phí" name="payment">
                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <td>Thu khách</td>
                            <td v-if="order">{{ order.pid | formatPrice }}</td>
                        </tr>
                        <tr id="tam_tinh" style="font-weight: 700; font-size: 1.2rem">
                            <td>Phí thuê</td>
                            <td v-if="order" id="pricing_temp" style="color: #060">
                                {{ order.total | formatPrice }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="otherFees.length > 0" class="mt-4">
                    <h5 class="mb-3 font-weight-bold">Chi phí khác</h5>
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <th>Tên chi phí</th>
                                <th>Số tiền</th>
                                <th>Ghi chú</th>
                            </tr>
                            <tr v-for="(fee, i) in otherFees" :key="i">
                                <td>{{ fee.name }}</td>
                                <td>{{ fee.value | formatPrice }}</td>
                                <td>{{ fee.note }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </el-tab-pane>

            <el-tab-pane v-if="exchangeTimeline.length" label="Lịch sử đổi xe" name="exchange">
                <div class="vehicle-exchange-card mb-2">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                        <div>
                            <h4 class="mb-1">Lịch sử đổi xe</h4>
                            <span class="text-muted small">Theo dõi xe và cơ sở bàn giao xuyên suốt hợp đồng</span>
                        </div>
                        <span class="badge badge-light-warning font-weight-bold">
                            {{ vehicleExchangeHistory.length }} lần đổi
                        </span>
                    </div>
                    <div class="vehicle-exchange-timeline">
                        <div
                            v-for="(step, index) in exchangeTimeline"
                            :key="`${step.vehicle.id}-${index}-${step.at || ''}`"
                            class="vehicle-exchange-step"
                        >
                            <div class="vehicle-exchange-marker">{{ index + 1 }}</div>
                            <div class="vehicle-exchange-content">
                                <div class="font-weight-bolder text-dark">
                                    {{ step.customerName || displayCustomer.name || 'Khách thuê' }}
                                    <span v-if="index > 0" class="text-warning mx-1">→</span>
                                    <span class="text-primary">
                                        Xe {{ step.store ? step.store.store_name : 'chưa xác định cơ sở' }}:
                                        {{ step.vehicle.license }}
                                    </span>
                                </div>
                                <div class="text-muted small mt-1">
                                    {{ step.vehicle.name || 'Chưa cập nhật tên xe' }}
                                    <span v-if="step.at"> · Ngày {{ step.at | formatDateTime }}</span>
                                    <span v-if="step.reason"> · {{ step.reason }}</span>
                                </div>
                                <router-link
                                    :to="warehouseLink(step)"
                                    class="btn btn-xs btn-light-primary font-weight-bold mt-2"
                                >
                                    Xem xe tại Kho
                                </router-link>
                            </div>
                        </div>
                    </div>
                </div>
            </el-tab-pane>

            <el-tab-pane label="Lịch sử" name="history">
                <el-tabs type="card">
                    <el-tab-pane label="Lịch sử thanh toán">
                        <transaction-history skin="order-show"
                            :transaction-logs="order ? order.transactions : []"></transaction-history>
                    </el-tab-pane>
                    <el-tab-pane label="Lịch sử đơn hàng">
                        <activity-history skin="order-show"
                            :activity-logs="order ? order.activity_logs : []"></activity-history>
                    </el-tab-pane>
                </el-tabs>
            </el-tab-pane>
        </el-tabs>

        <ModalContractPreview v-model="showPrintModal" :doc="printDocumentDto" />
    </div>
</template>

<script>
import {
    ORDER_STATUS_DEFINE,
    ORDER_STATUS_DEFINE_CSS,
} from "../../../../option/orderOption";
import ActivityHistory from "./ActivityHistory";
import TransactionHistory from "./TransactionHistory";
import ModalContractPreview from "./ModalContractPreview";
import { mapGetters } from "vuex";
import { SHOW_ORDER_CAR_RENTAL, GET_ORDER_DOCUMENT } from "../../../../core/services/store/order.module";

export default {
    name: "OrderShow",
    props: {
        order: {
            type: Object,
            default: () => {
                return {};
            },
        },
    },
    computed: {
        ...mapGetters(["currentUser"]),
        vehicleExchangeHistory() {
            return Array.isArray(this.order?.vehicle_exchange_history)
                ? this.order.vehicle_exchange_history
                : [];
        },
        currentVehicle() {
            const item = Array.isArray(this.order?.order_items)
                ? this.order.order_items.find(orderItem => orderItem && orderItem.vehicle)
                : null;
            return item ? item.vehicle : null;
        },
        exchangeTimeline() {
            const timeline = [];
            this.vehicleExchangeHistory.forEach((entry, index) => {
                if (entry.old_vehicle) {
                    const previous = timeline[timeline.length - 1];
                    if (!previous || Number(previous.vehicle.id) !== Number(entry.old_vehicle.id)) {
                        timeline.push({
                            vehicle: entry.old_vehicle,
                            store: entry.old_store,
                            at: index === 0 ? entry.initial_at : entry.effective_at,
                            customerName: entry.customer_name,
                            reason: index === 0 ? "Xe bàn giao ban đầu" : "Xe trước khi đổi",
                        });
                    }
                }
                if (entry.new_vehicle) {
                    timeline.push({
                        vehicle: entry.new_vehicle,
                        store: entry.new_store || entry.exchange_store,
                        at: entry.effective_at,
                        customerName: entry.customer_name,
                        reason: entry.reason,
                    });
                }
            });
            return timeline;
        },
        displayRelatives() {
            const relatives = this.displayCustomer && Array.isArray(this.displayCustomer.relatives)
                ? this.displayCustomer.relatives
                : [];
            return relatives.filter(relative => relative && (relative.name || relative.phone));
        },
        snapshot() {
            return (this.order && this.order.contract_snapshot) ? this.order.contract_snapshot : null;
        },
        displayCustomer() {
            if (this.snapshot && this.snapshot.customer) {
                return this.snapshot.customer;
            }
            return this.order?.customer || {};
        },
        displaySigners() {
            if (this.snapshot && this.snapshot.signers) {
                return this.snapshot.signers;
            }
            return {
                signer_a_name: this.order?.contract_signer_a_name || "",
                signer_b_name: this.order?.contract_signer_b_name || "",
            };
        },
        displayReturnConfirmation() {
            if (this.snapshot && this.snapshot.return_confirmation) {
                return this.snapshot.return_confirmation;
            }
            return {
                signer_a_name: this.order?.return_signer_a_name || "",
                signer_b_name: this.order?.return_signer_b_name || "",
                additional_note: this.order?.return_additional_note || "",
            };
        },
        displayContractSignedOn() {
            return this.snapshot?.signed_on || this.order?.contract_signed_on;
        },
        displayResponsibleUser() {
            if (this.snapshot && this.snapshot.responsible_user) {
                return this.snapshot.responsible_user;
            }
            return this.order?.responsible_user || null;
        },
        displayAuthorization() {
            if (this.snapshot && this.snapshot.authorization) {
                return this.snapshot.authorization;
            }
            return {
                date: this.order?.contract_authorization_date,
                party_name: this.order?.contract_authorization_party_name,
            };
        },
        displayCollateralDescription() {
            return this.snapshot?.payment?.collateral_description || this.order?.contract_collateral_description || "";
        },
        displayStoreName() {
            if (this.snapshot?.lessor?.branch_name) {
                return this.snapshot.lessor.branch_name;
            }
            return this.order?.store?.store_name || "";
        },
        displayOrderItems() {
            if (this.snapshot && this.snapshot.vehicles && this.snapshot.vehicles.length) {
                return this.snapshot.vehicles.map((v, i) => {
                    const rawItem = (this.order?.order_items && this.order.order_items[i]) || {};
                    return {
                        ...rawItem,
                        vehicle: {
                            ...(rawItem.vehicle || {}),
                            name: v.vehicle_name || rawItem.vehicle?.name || "",
                            license: v.license || rawItem.vehicle?.license || "",
                        },
                        driver_name: v.driver_name !== undefined ? v.driver_name : rawItem.driver_name,
                        driver_license_number: v.driver_license_number !== undefined ? v.driver_license_number : rawItem.driver_license_number,
                        driver_license_issued_on: v.driver_license_issued_on !== undefined ? v.driver_license_issued_on : rawItem.driver_license_issued_on,
                        borrow_hats: v.borrow_hats !== undefined ? v.borrow_hats : rawItem.borrow_hats,
                        borrow_raincoats: v.borrow_raincoats !== undefined ? v.borrow_raincoats : rawItem.borrow_raincoats,
                        rent_at: v.rent_at || rawItem.rent_at,
                        return_at: v.return_at || rawItem.return_at,
                        completed_at: v.completed_at || rawItem.completed_at,
                    };
                });
            }
            return this.order?.order_items || [];
        },
        orderWarehouseLink() {
            const storeId = this.currentVehicle?.current_store_id
                || this.currentVehicle?.store_id
                || this.order?.store_id
                || this.currentUser?.store_id;
            return {
                name: "warehouse",
                query: storeId ? { store_id: storeId } : {},
            };
        },
    },
    methods: {
        warehouseLink(step) {
            const storeId = step?.store?.id || this.currentUser?.store_id || this.order?.store_id;
            return {
                name: "warehouse",
                query: {
                    ...(storeId ? { store_id: storeId } : {}),
                    vehicle_id: step.vehicle.id,
                    keyword: step.vehicle.license,
                },
            };
        },
        openWarehouseForCurrentVehicle() {
            if (!this.currentVehicle) return;
            const storeId = this.currentVehicle.current_store_id
                || this.currentVehicle.store_id
                || this.currentUser?.store_id
                || this.order?.store_id;
            this.$router.push({
                name: "warehouse",
                query: {
                    ...(storeId ? { store_id: storeId } : {}),
                    vehicle_id: this.currentVehicle.id,
                    keyword: this.currentVehicle.license,
                },
            });
        },
        openVehicleExchange() {
            if (!this.currentVehicle || !this.order) return;
            const storeId = this.currentUser?.store_id
                || this.currentVehicle.current_store_id
                || this.currentVehicle.store_id
                || this.order.store_id;
            this.$router.push({
                name: "warehouse",
                query: {
                    action: "exchange",
                    order_id: this.order.id,
                    old_vehicle_id: this.currentVehicle.id,
                    old_vehicle_license: this.currentVehicle.license || "",
                    old_vehicle_name: this.currentVehicle.name || "",
                    ...(storeId ? { store_id: storeId } : {}),
                },
            });
        },
        displayCompletedAt(item) {
            if (item.completed_at) {
                return this.$options.filters.formatDateTime((item.completed_at));
            } else {
                return "Đang thuê";
            }
        },

        async getOtherFees(orderId) {
            this.loading = true;
            await this.$store
                .dispatch(SHOW_ORDER_CAR_RENTAL, orderId)
                .then((res) => {
                    let fees = [];
                    const data = res?.data?.order_items
                    if (data?.length > 0) {
                        fees = data.reduce((acc, item) => {
                            acc.push(...(item?.order_item_fees || []))
                            return acc;
                        }, [])
                    }

                    if (fees?.length > 0) {
                        this.otherFees = fees;
                    }
                });
        },

        async printContract() {
            if (!this.order) return;
            try {
                const res = await this.$store.dispatch(GET_ORDER_DOCUMENT, this.order.id);
                this.printDocumentDto = res.data || res;
                this.showPrintModal = true;
            } catch (err) {
                this.$message.error("Không thể tải tài liệu hợp đồng");
            }
        },
    },
    components: {
        ActivityHistory,
        TransactionHistory,
        ModalContractPreview,
    },
    data() {
        return {
            ORDER_STATUS_DEFINE: ORDER_STATUS_DEFINE,
            ORDER_STATUS_DEFINE_CSS: ORDER_STATUS_DEFINE_CSS,
            loading: false,
            otherFees: [],
            activeShowTab: "vehicle",
            showPrintModal: false,
            printDocumentDto: null,
        };
    },
    watch: {
        "order.id": {
            immediate: true,
            handler(id) {
                this.otherFees = [];
                if (id) this.getOtherFees(id);
            },
        },
    },
};
</script>

<style scoped>
.order-show-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 4px 8px 14px;
    border-bottom: 1px solid #e8ebef;
}

.order-show-title {
    margin: 9px 0 0;
    color: #243043;
    font-size: 18px;
    font-weight: 700;
}

.order-show-actions { gap: 8px; }

.vehicle-exchange-card {
    border: 1px solid #e6eaf0;
    border-radius: 10px;
    padding: 16px;
    background: #fbfcfe;
}

.vehicle-exchange-card h4 { font-size: 16px; font-weight: 700; }
.vehicle-exchange-timeline { position: relative; }
.vehicle-exchange-step { display: flex; gap: 12px; position: relative; padding-bottom: 16px; }
.vehicle-exchange-step:last-child { padding-bottom: 0; }
.vehicle-exchange-step:not(:last-child)::before {
    content: "";
    position: absolute;
    left: 14px;
    top: 30px;
    bottom: 0;
    width: 2px;
    background: #d8e6f7;
}
.vehicle-exchange-marker {
    width: 30px;
    height: 30px;
    flex: 0 0 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #3699ff;
    color: #fff;
    font-weight: 700;
    z-index: 1;
}
.vehicle-exchange-content { min-width: 0; padding-top: 3px; }

.table { margin-bottom: 14px; font-size: 13.5px; }
.table td, .table th { padding: 8px 10px; vertical-align: top; }
h4.my-5 { margin-top: 16px !important; margin-bottom: 10px !important; font-size: 16px; }

@media (max-width: 768px) {
    .order-show-toolbar { align-items: flex-start; }
    .order-show-title { font-size: 16px; }
    .order-show-actions { justify-content: flex-start !important; }
}
.contract-form-tabs.el-tabs--border-card {
    box-shadow: none;
    border: 1px solid #e4e6ef;
    border-radius: 8px;
    overflow: hidden;
}
.contract-form-tabs .el-tabs__header {
    background: #f5f8fa;
    margin: 0;
}
.contract-form-tabs .el-tabs__item {
    height: 44px;
    line-height: 44px;
    font-weight: 600;
}
.contract-form-tabs .el-tabs__content {
    padding: 16px;
}
</style>
