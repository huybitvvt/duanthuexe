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
            <div class="col-md-6">
                <h4 class="my-5 ml-2" v-if="order">Thông tin hợp đồng {{ order.id }}</h4>
                <table class="table table-bordered" v-if="order">
                    <tbody v-for="(item, index) in order.order_items" :key="index">
                        <tr class="text-primary">
                            <td>Thuê xe</td>
                            <td>
                                <p>
                                    {{ item.vehicle.name }}({{
                                        item.vehicle.license
                                    }})
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td>Tại cửa hàng</td>
                            <td>
                                {{ order.store ? order.store.store_name : "" }}
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
                            <td>Mượn mũ</td>
                            <td>{{ item.borrow_hats }}</td>
                        </tr>

                    </tbody>
                    <tbody>
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
                            <td>{{ order ? order.customer.name : "" }}</td>
                        </tr>
                        <tr>
                            <td>Số điện thoại</td>
                            <td>{{ order ? order.customer.phone : "" }}</td>
                        </tr>
                        <tr>
                            <td>Số CMTND/CCCD</td>
                            <td>{{ order ? order.customer.id_card : "" }}</td>
                        </tr>
                        <tr>
                            <td>Địa chỉ</td>
                            <td>{{ order ? order.customer.address : "" }}</td>
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
                    Xem lịch sử<i class="header-icon el-icon-info"></i>
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
    </div>
</template>

<script>
import {
    ORDER_STATUS_DEFINE,
    ORDER_STATUS_DEFINE_CSS,
} from "../../../../option/orderOption";
import ActivityHistory from "./ActivityHistory";
import TransactionHistory from "./TransactionHistory";
import { SHOW_ORDER_CAR_RENTAL } from "../../../../core/services/store/order.module";
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
    },
    components: {
        ActivityHistory,
        TransactionHistory,
    },
    data() {
        return {
            ORDER_STATUS_DEFINE: ORDER_STATUS_DEFINE,
            ORDER_STATUS_DEFINE_CSS: ORDER_STATUS_DEFINE_CSS,
            loading: false,
            otherFees: [],
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
