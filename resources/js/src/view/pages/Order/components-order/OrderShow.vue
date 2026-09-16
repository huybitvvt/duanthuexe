<template id="order-show">
    <div>
        <div class="row mt-2">
            <div class="col-md-6">
                <span :class="order ? ORDER_STATUS_DEFINE_CSS[order.order_status] : ''
                    ">{{
                        order ? ORDER_STATUS_DEFINE[order.order_status] : ""
                    }}</span>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 d-flex justify-content-between align-items-center">
                <h4 class="my-5 ml-2 mb-0" v-if="order">
                    Hợp đồng #{{ order.id }}
                    <span v-if="order.contract_number" class="badge badge-success ml-2 font-weight-bolder" style="font-size: 13px;">
                        Số HĐ: {{ order.contract_number }}
                    </span>
                </h4>
                <button v-if="order" type="button" class="btn btn-sm btn-info font-weight-bold mr-2" @click="printContract">
                    In hợp đồng
                </button>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <table class="table table-bordered" v-if="order">
                    <tbody v-for="(item, index) in displayOrderItems" :key="index">
                        <tr class="text-primary">
                            <td>Thuê xe</td>
                            <td>
                                <p class="font-weight-bold mb-1">
                                    {{ item.vehicle.name }} ({{
                                        item.vehicle.license
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
                            <td>
                                {{ displayStoreName }}
                            </td>
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
                            <td>
                                {{
                                    order.note ? order.note : "Chưa có ghi chú"
                                }}
                            </td>
                        </tr>
                        <tr>
                            <td>Nguồn lead</td>

                            <td> <span v-if="order.leads.length" v-for=" lead in order.leads">{{ lead.user ?
                                lead.user.name : 'Landing page Himoto' }} </span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="col-md-6">
                <h4 class="my-5 ml-2">Thông tin khách hàng</h4>
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
                                <div v-for="(rel, rk) in displayCustomer.relatives" :key="rk" v-if="rel.name || rel.phone">
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
                <h4 class="my-5 ml-2">Chi phí</h4>
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

                <div v-if="order && order.order_items[0].order_item_fees.length > 0">
                    <h4 class="my-5 ml-2">Chi phí khác</h4>
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
            </div>
        </div>
        <el-collapse accordion>
            <el-collapse-item name="1">
                <template slot="title">
                    Xem lịch sử
                </template>
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
            </el-collapse-item>
        </el-collapse>
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
import { SHOW_ORDER_CAR_RENTAL, GET_ORDER_DOCUMENT } from "../../../../core/services/store/order.module";
import moment from "moment";

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
    },
    methods: {
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
            showPrintModal: false,
            printDocumentDto: null,
        };
    },
    async created() {
        if (this.order?.id) {
            await this.getOtherFees(this.order.id);
        }
    },
};
</script>

<style scoped></style>
