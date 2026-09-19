<template>
    <div class="card card-custom gutter-b">
        <div class="card-header">
            <div class="card-title">
                <h3 class="card-label">Lịch sử thu chi</h3>
            </div>
            <div class="card-title">
                <button class="btn btn-success" @click="exportFile">Export</button>
            </div>

        </div>
        <div>
            <div class="card card-custom gutter-b">
                <div class="card-body">
                    <!-- KHUNG THỐNG KÊ TỔNG THU - TỔNG CHI -->
                    <div class="card card-custom gutter-b border shadow-xs bg-white rounded" v-if="!!stats">
                        <div class="card-body p-4">
                            <!-- 2 Khung hiển thị Tổng Thu và Tổng Chi -->
                            <div class="row">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <div class="stats-box stats-box-in p-4 rounded border h-100 d-flex align-items-center justify-content-between shadow-xs">
                                        <div>
                                            <span class="text-dark font-weight-bolder font-size-sm text-uppercase d-block mb-1">
                                                <i class="fa fa-arrow-circle-down text-success mr-1 font-size-base"></i> Tổng Thu
                                            </span>
                                            <span class="font-size-h3 font-weight-bolder text-success">
                                                {{ (stats.all_in + stats.all_addon) | formatPrice }}
                                            </span>
                                        </div>
                                        <div class="symbol symbol-50 symbol-light-success">
                                            <span class="symbol-label">
                                                <i class="fa fa-money-bill-wave text-success font-size-h4"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="stats-box stats-box-out p-4 rounded border h-100 d-flex align-items-center justify-content-between shadow-xs">
                                        <div>
                                            <span class="text-dark font-weight-bolder font-size-sm text-uppercase d-block mb-1">
                                                <i class="fa fa-arrow-circle-up text-danger mr-1 font-size-base"></i> Tổng Chi
                                            </span>
                                            <span class="font-size-h3 font-weight-bolder text-danger">
                                                {{ stats.all_out | formatPrice }}
                                            </span>
                                        </div>
                                        <div class="symbol symbol-50 symbol-light-danger">
                                            <span class="symbol-label">
                                                <i class="fa fa-hand-holding-usd text-danger font-size-h4"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Thanh toggle Chi tiết có nút mũi tên rõ ràng -->
                            <div class="mt-4 pt-3 border-top d-flex align-items-center justify-content-between flex-wrap">
                                <button 
                                    type="button" 
                                    class="btn btn-sm btn-outline-primary font-weight-bolder shadow-xs d-inline-flex align-items-center"
                                    @click="toggleDetail"
                                >
                                    <i :class="isDetailOpen ? 'fa fa-chevron-up mr-2 text-primary' : 'fa fa-chevron-down mr-2 text-primary'"></i>
                                    <span>{{ isDetailOpen ? 'Thu gọn chi tiết' : 'Chi tiết' }}</span>
                                    <span class="ml-2 badge badge-primary text-white">
                                        {{ (stats.banks ? stats.banks.length : 0) + 1 }}
                                    </span>
                                </button>
                                <span class="text-dark-50 font-size-sm font-weight-bold mt-1 mt-md-0">
                                    {{ isDetailOpen ? 'Bấm nút để thu gọn bảng chi tiết' : 'Bấm nút mũi tên để xem chi tiết từng tài khoản & tiền mặt' }}
                                </span>
                            </div>

                            <!-- Bảng chi tiết mở rộng -->
                            <transition name="fade">
                                <div v-show="isDetailOpen" class="mt-3 table-responsive rounded border bg-white shadow-xs">
                                    <table class="table table-vertical-center table-hover table-bordered mb-0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th scope="col" class="font-weight-bolder text-dark">Tài Khoản / Phương thức</th>
                                                <th scope="col" class="font-weight-bolder text-success text-right" style="min-width: 140px;">Tổng Thu</th>
                                                <th scope="col" class="font-weight-bolder text-danger text-right" style="min-width: 140px;">Tổng Chi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(item, index) in stats.banks" :key="`banks-${index}`">
                                                <td>
                                                    <span class="font-weight-bold text-dark d-block">
                                                        <i class="fa fa-university text-primary mr-1"></i>
                                                        {{ item.owner_name }}
                                                    </span>
                                                    <span class="text-dark-50 font-size-xs">
                                                        {{ item.bank_name }} - {{ item.account_number }}
                                                    </span>
                                                </td>
                                                <td class="text-right font-weight-bold text-success font-size-sm">
                                                    {{ (item.type_in + item.type_addon) | formatPrice }}
                                                </td>
                                                <td class="text-right font-weight-bold text-danger font-size-sm">
                                                    {{ item.type_out | formatPrice }}
                                                </td>
                                            </tr>
                                            <tr class="bg-light-success-soft">
                                                <td>
                                                    <span class="font-weight-bold text-dark">
                                                        <i class="fa fa-money-bill-wave text-success mr-1"></i> Tiền Mặt
                                                    </span>
                                                </td>
                                                <td class="text-right font-weight-bold text-success font-size-sm">
                                                    {{ (stats.cash_in + stats.cash_addon) | formatPrice }}
                                                </td>
                                                <td class="text-right font-weight-bold text-danger font-size-sm">
                                                    {{ stats.cash_out | formatPrice }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </transition>
                        </div>
                    </div>
                    <div class="example mb-10">
                        <div class="d-flex justify-content-between">
                            <div class="row mb-10">
                                <div class="col-md-3">
                                    <el-select v-model="query.store_id" filterable clearable placeholder="Chọn cửa hàng"
                                        class="w-100">
                                        <el-option v-for="item in stores" :store_id="item.id" :key="item.id"
                                            :label="item.store_name" :value="item.id">
                                        </el-option>
                                    </el-select>
                                </div>
                                <div class="col-md-3">
                                    <el-select v-model="query.payment_method" filterable clearable
                                        placeholder="Chọn loại thanh toán" class="w-100">
                                        <el-option v-for="(item, key) in payment_methods" :key="`method-${key}`"
                                            :payment_method="key" :label="item" :value="key">
                                        </el-option>
                                    </el-select>
                                </div>
                                <div class="col-md-3">
                                    <el-date-picker class="w-100" v-model="query.start_date" type="date"
                                        format="yyyy-MM-dd" value-format="yyyy-MM-dd"
                                        :picker-options="pickerStartOptions" placeholder="Từ ngày">
                                    </el-date-picker>
                                </div>
                                <div class="col-md-3">
                                    <el-date-picker class="w-100" v-model="query.end_date" type="date" ref="picker"
                                        format="yyyy-MM-dd" value-format="yyyy-MM-dd" :picker-options="pickerEndOptions"
                                        placeholder="Đến ngày">
                                    </el-date-picker>
                                </div>

                            </div>
                            <div class="row mb-2 justify-self-end">
                                <div class="col-md-4 text-right">
                                    <el-button :loading="loading"
                                        class="btn btn-primary font-weight-bold" @click="search">
                                        Tìm kiếm
                                    </el-button>

                                </div>
                            </div>
                        </div>
                        <div class="example-preview table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th scope="col">Mã giao dịch</th>
                                        <th scope="col" class="min-w-130px">
                                            Ngày giao dịch
                                        </th>
                                        <th scope="col">Người thực hiện</th>
                                        <th scope="col">Loại giao dịch</th>
                                        <th scope="col">Phương thức</th>
                                        <th scope="col">Số tiền</th>
                                        <th scope="col">Ghi chú</th>
                                        <th scope="col">Trạng thái</th>
                                        <!-- <th scope="col">Hành động</th> -->
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(item, index) in transactions" :key="`transaction-${index}`">
                                        <td>{{ item.id }}</td>
                                        <td>
                                            {{
                                                item.created_at | formatDateTime
                                            }}
                                        </td>
                                        <td>{{ item.user_name }}</td>
                                        <td>
                                            <span :class="{
                                                badge: true,
                                                'px-4': true,
                                                'badge-primary': [
                                                    'in',
                                                    'addon',
                                                ].includes(item.type),
                                                'badge-danger':
                                                    item.type === 'out',
                                            }">{{ types[item.type] }}</span>
                                        </td>
                                        <td v-html="item.payment_method === 'cash'
                                            ? payment_methods['cash']
                                            : getBankInfo(item)
                                            "></td>

                                        <td>
                                            <span :class="{
                                                'text-danger':
                                                    item.type == 'out',
                                            }">{{
                                                item.value | formatPrice
                                                }}</span>
                                        </td>
                                        <td>
                                            <el-tooltip :content="item.note">
                                                <span>{{
                                                    getNote(item.note)
                                                }}</span>
                                            </el-tooltip>
                                        </td>
                                        <td>{{ item.status }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="edu-paginate mx-auto text-center">
                    <paginate v-model="page" :page-count="last_page" :page-range="3" :margin-pages="1"
                        :click-handler="clickCallback" :prev-text="'Trước'" :next-text="'Sau'"
                        :container-class="'pagination b-pagination'" :pageLinkClass="'page-link'"
                        :next-link-class="'next-link-order'" :prev-link-class="'prev-link-order'"
                        :prev-class="'page-link'" :next-class="'page-link'" :page-class="'page-order'">
                    </paginate>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import {
    TRANSACTION_GET_LIST,
    TRANSACTION_STATS,
} from "../../../core/services/store/transaction.module";
import { EXPORT_TRANSACTIONS } from "../../../core/services/store/exports.module";
import { types, payment_methods } from "../../../option/transactionOption";
import { SET_BREADCRUMB } from "@/core/services/store/breadcrumbs.module";
import { getTextShort } from "../../../utils";
import { STORE_GET_ALL } from "@/core/services/store/store.module";
import moment from "moment-timezone";
import queryMixin from '@/utils/queryMixin.js';
import { normalizePaginator } from "@/utils/paginatorAdapter";
import { getApiMessage } from "@/utils/apiErrorHandler";

export default {
    mixins: [queryMixin],
    name: "Transaction",
    data() {
        const { page, store_id, ...restQuery } = this.$route?.query || {};
        return {
            moment: moment,
            types: types,
            payment_methods: payment_methods,
            page: +page || 1,
            last_page: 1,
            transactions: [],
            stats: null,
            loading: false,
            showDetail: "",
            isDetailOpen: false,
            stores: [],
            loading: false,
            query: {
                payment_method: "",
                start_date: "",
                end_date: "",
                store_id: store_id ? +store_id : '',
                ...(restQuery || {}),
            },
            pickerStartOptions: {},
            pickerEndOptions: {},
        };
    },
    created() {
        this.getList();
        this.getStore();
        this.getStats();
    },
    mounted() {
        this.$store.dispatch(SET_BREADCRUMB, [{ title: "Lịch sử thu chi" }]);
    },
  
    methods: {
        getStore() {
            this.$store.dispatch(STORE_GET_ALL, {}).then((data) => {
                this.stores = data.data;
            });
        },
        search() {
            this.page = 1;
            this.pushParamsUrl();
            this.getList();
            this.getStats();
        },
        pushParamsUrl() {
            this.$router.push({
                path: "",
                query: {
                    page: this.page,
                    ...this.query,
                },
            }).catch(() => {});
        },
        formatValue(...values) {
            let res = values.reduce((acc, item) => {
                acc += item || 0;
                return acc;
            }, 0);

            return res;
        },
        getStats() {
            this.$store
                .dispatch(TRANSACTION_STATS, { ...this.query })
                .then((data) => {
                    this.stats = data?.data;
                })
                .catch((error) => {
                    this.noticeMessage('error', 'Thất bại', getApiMessage(error));
                });
        },
        getNote(str) {
            return getTextShort(str);
        },
        getList() {
            this.loading = true;
            this.$store
                .dispatch(TRANSACTION_GET_LIST, {
                    page: this.page,
                    ...this.query,
                })
                .then((data) => {
                    const paginated = normalizePaginator(data);
                    this.transactions = paginated.items;
                    this.last_page = paginated.lastPage;
                })
                .catch((error) => {
                    this.noticeMessage('error', 'Thất bại', getApiMessage(error));
                })
                .finally(() => {
                    this.loading = false;
                });
        },
        clickCallback(obj) {
            this.page = obj;
            this.pushParamsUrl();
            this.getList();
        },
        getBankInfo(item) {
            if (item.payment_method === 2) {
                return `${item.bank_name}</br>${item.owner_name}</br>${item.account_number}`;
            } else if (item.payment_method === 1) {
                return payment_methods[1];
            } else {
                // null => Tiền Mặt
                return payment_methods[1];
            }
        },
        exportFile() {
            this.loading = true;
            this.$store.dispatch(EXPORT_TRANSACTIONS, this.query).then().catch((error) => {
                this.noticeMessage('error', 'Thất bại', error.message);
            }).finally(() => {
                this.loading = false;
            })
        },
        toggleDetail() {
            this.isDetailOpen = !this.isDetailOpen;
        }
    }

};
</script>

<style>
.font-weight-bold {
    font-weight: 700 !important;
}

.stats-box-in {
    background: #f3fbf7;
    border-color: #d1f3e0 !important;
}

.stats-box-out {
    background: #fdf5f5;
    border-color: #fbd6d9 !important;
}

.bg-light-success-soft {
    background-color: #f8fdfa !important;
}

.table-content {
    max-height: 500px;
    overflow-y: scroll;
}
</style>
