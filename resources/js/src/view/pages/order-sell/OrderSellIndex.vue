<template>
    <div>
        <div class="card card-custom gutter-b">
            <div class="card-header">
                <div class="card-title">
                    <h3 class="card-label">Danh sách đơn hàng (Bán xe)</h3>
                </div>
                <div class="card-title">
                    <ModalOrderSellCreate @storeSuccess="getList"></ModalOrderSellCreate>
                </div>
            </div>
            <div class="alert alert-custom alert-white alert-shadow fade show gutter-b" role="alert">
                <div class="alert-text">
                    <div class="row">
                        <div class="col-md-3">
                            <p>Số lượt bán: <span class="font-weight-bold">{{ reports.total_sell }}</span></p>
                            <p>Tổng thu: <span class="font-weight-bold">{{ reports.total_price | formatPrice }}</span>
                            </p>
                        </div>
                        <div class="col-md-3">
                            <p>Phí đầu tư: <span class="font-weight-bold">{{ reports.total_cost | formatPrice }}</span>
                            </p>
                            <p>Lợi nhuận: <span class="font-weight-bold">{{ reports.total_profit | formatPrice }}</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Thời gian tạo</label>
                            <date-picker v-model="query.created_at" type="date" range placeholder="Chọn thời gian tạo"
                                format="DD-MM-YYYY" valueType="YYYY-MM-DD"></date-picker>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Tên xe, biển số</label>
                            <search-suggest endpoint="/api/auth/order-sell" :params="query" query-key="vehicle" fields="order_items.vehicle.name,order_items.vehicle.license,orderItems.vehicle.name,orderItems.vehicle.license" @select="search" @submit="search" clearable placeholder="Tên xe, biển số" v-model="query.vehicle"></search-suggest>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Tên khách, phone, cccd</label>
                            <search-suggest endpoint="/api/auth/order-sell" :params="query" query-key="customer" fields="customer.name,customer.phone,customer.email,customer.id_card" @select="search" @submit="search" clearable placeholder="Tên khách, phone, cccd"
                                v-model="query.customer"></search-suggest>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Trạng thái</label>
                            <el-select filterable class="w-100" placeholder="Trạng thái" v-model="query.status"
                                clearable>
                                <el-option v-for="order in status" :key="order.id" :label="order.name"
                                    :value="order.id">
                                    <span style="float: left">{{
                                        order.name
                                        }}</span>
                                </el-option>
                            </el-select>

                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Cửa hàng</label>
                            <el-select filterable class="w-100" placeholder="Cửa hàng" v-model="query.store_id"
                                clearable>
                                <el-option v-for="order in stores" :key="order.id" :label="order.store_name"
                                    :value="order.id">
                                    <span style="float: left">{{
                                        order.store_name
                                        }}</span>
                                </el-option>
                            </el-select>
                        </div>
                    </div>
                    <div class="col-md-2 mt-8">
                        <button class="btn btn-primary"
                            :class="{ 'spinner spinner-white spinner-right': is_loading_search }" @click="search">Tìm
                            kiếm
                        </button>
                    </div>
                </div>
                <div class="example mb-10">
                    <div class="example-preview table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col" class="min-w-130px">Ngày tạo</th>
                                    <th scope="col">Tên xe</th>
                                    <th scope="col">Biển số</th>
                                    <th scope="col">Cửa hàng</th>
                                    <th scope="col">Khách hàng</th>
                                    <th scope="col">Số điện thoại</th>
                                    <th scope="col">CMT,cccd</th>
                                    <th scope="col">Địa chỉ</th>
                                    <th scope="col">Giá bán</th>
                                    <th scope="col">Lợi nhuận</th>
                                    <th scope="col">Trạng thái</th>
                                    <th scope="col">Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(order, index) in orders.data" :key="index">
                                    <th scope="row">{{ index + 1 }}</th>
                                    <td>{{ order.created_at | formatDateTime }}</td>
                                    <td>
                                        <span v-for="(item, i) in order.order_items" :key="i">
                                            {{ item.vehicle ? item.vehicle.name : '' }} <br>
                                        </span>
                                    </td>
                                    <td>
                                        <span v-for="(item, i) in order.order_items" :key="i">
                                            {{ item.vehicle ? item.vehicle.license : '' }} <br>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-primary">
                                            {{ order.store ? order.store.store_name : '' }}
                                        </span>
                                    </td>
                                    <td>
                                        {{ order.customer ? order.customer.name : '' }}
                                    </td>
                                    <td>
                                        {{ order.customer ? order.customer.phone : '' }}
                                    </td>
                                    <td>
                                        {{ order.customer ? order.customer.phone : '' }}
                                    </td>
                                    <td>
                                        {{ order.customer ? order.customer.address : '' }}
                                    </td>
                                    <td>
                                        {{ order.price | formatPrice }}
                                    </td>
                                    <td>
                                        {{ order.price_profit | formatPrice }}
                                    </td>
                                    <td>
                                        <span :class="status_define_css[order.status]">{{
                                            status_define[order.status]
                                        }}</span>
                                    </td>
                                    <td>
                                        <button v-b-modal.modal-order-edit @click="order_current = order"
                                            class="btn btn-xs btn btn-xs btn-outline-info">Sửa</button>
                                        <button @click="deleteOrder(order.id)"
                                            class="btn btn-danger btn-xs btn btn-xs btn-outline-info">Xóa</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <ModalOrderSellEdit :order_prop="order_current" @storeSuccess="getList"></ModalOrderSellEdit>
            <div class="edu-paginate mx-auto text-center">
                <paginate v-model="page" :page-count="last_page" :page-range="3" :margin-pages="1"
                    :click-handler="clickCallback" :prev-text="'Trước'" :next-text="'Sau'"
                    :container-class="'pagination b-pagination'" :pageLinkClass="'page-link'"
                    :next-link-class="'next-link-order'" :prev-link-class="'prev-link-order'" :prev-class="'page-link'"
                    :next-class="'page-link'" :page-class="'page-order'">
                </paginate>
            </div>
        </div>
    </div>
</template>

<script>
import { SET_BREADCRUMB } from "@/core/services/store/breadcrumbs.module";
import { mapGetters } from "vuex";
import { status, status_define_css, status_define } from "../../../option/sell-order";
import { STORE_GET_ALL } from "../../../core/services/store/store.module";
import ModalOrderSellCreate from "./ModalOrderSellCreate";
import {
    ORDER_SELL_DELETE,
    ORDER_SELL_GET_ALL,
    ORDER_SELL_GET_ALL_REPORT
} from "../../../core/services/store/orderSell.module";
import ModalOrderSellEdit from "./ModalOrderSellEdit";
import Swal from "sweetalert2";
import queryMixin from '@/utils/queryMixin.js';
import { normalizePaginator } from "@/utils/paginatorAdapter";
import { getApiMessage } from "@/utils/apiErrorHandler";

export default {
    mixins: [queryMixin],
    name: "OrderSellIndex",
    components: { ModalOrderSellEdit, ModalOrderSellCreate },
    data() {
        const { page, store_id, status: statusQ, ...restQuery } = this.$route?.query || {};
        const newStatus = statusQ ? statusQ === "using" ? "using" : +statusQ : "";

        return {
            query: {
                vehicle: "",
                customer: "",
                created_at: "",
                ...(restQuery || {}),
                status: newStatus,
                store_id: store_id ? +store_id : "",
            },
            status: status,
            stores: [],
            orders: [],
            reports: [],
            page: +page || 1,
            last_page: 1,
            status_define_css: status_define_css,
            status_define: status_define,
            is_loading_search: false,
            order_current: null
        };
    },
    computed: {
        ...mapGetters(["currentUser"])
    },
    mounted() {
        this.getStore();
        this.$store.dispatch(SET_BREADCRUMB, [{ title: "Quản lý đơn hàng" }]);
        this.getList();
        this.getReport();
    },
    methods: {
        search() {
            this.page = 1;
            this.pushParamsUrl();
            this.getList();
            this.getReport();
        },
        pushParamsUrl() {
            this.$router.push({
                path: '', query: {
                    page: this.page,
                    ...this.query
                }
            }).catch(() => {})
        },
        getList() {
            this.is_loading_search = true;
            this.$store.dispatch(ORDER_SELL_GET_ALL, { page: this.page, ...this.query }).then(data => {
                const paginated = normalizePaginator(data);
                this.orders = paginated.items;
                this.last_page = paginated.lastPage;
            }).finally(() => {
                this.is_loading_search = false;
            });
        },
        getReport() {
            this.$store.dispatch(ORDER_SELL_GET_ALL_REPORT, this.query).then(data => {
                this.reports = data.data;
            });
        },
        clickCallback(obj) {
            this.page = obj;
            this.pushParamsUrl();
            this.getList();
        },
        getStore() {
            this.$store.dispatch(STORE_GET_ALL, {}).then((data) => {
                this.stores = data.data;
            });
        },
        deleteOrder(id) {
            Swal.fire({
                title: "Bạn chắc chắn muốn huỷ?",
                showDenyButton: true,
                showCancelButton: true,
                confirmButtonText: "Đồng ý",
                cancelButtonText: "Không",
            }).then((result) => {
                if (result.isConfirmed) {
                    this.$store
                        .dispatch(ORDER_SELL_DELETE, id)
                        .then((data) => {
                            this.getList();
                            this.$message.success(data.message);
                        }).catch((e) => {
                            this.$message.error(getApiMessage(e));
                        });
                }
            });
        }
    }
};
</script>

<style scoped>
.mx-datepicker {
    width: 100% !important;
}
</style>
