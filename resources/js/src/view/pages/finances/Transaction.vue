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
                    <div class="alert alert-custom alert-white alert-shadow fade show gutter-b" role="alert"
                        v-if="!!stats">
                        <div class="d-flex flex-column justify-content-start w-100">
                            <div class="text-start">
                                <div class="alert-text">
                                    <p class="font-weight-bold">
                                        Tổng Thu:
                                        <span class="font-weight-bold">
                                            {{
                                                (stats.all_in + stats.all_addon)
                                                | formatPrice
                                            }}
                                        </span>
                                    </p>
                                    <p class="font-weight-bold">
                                        Tổng Chi:
                                        <span class="font-weight-bold">
                                            {{ stats.all_out | formatPrice }}
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <div class="justify-content-start w-100">
                                <el-collapse accordion v-model="showDetail">
                                    <el-collapse-item title="Chi tiết" name="detail">
                                        <div class="table-content">
                                            <table class="table table-vertical-center table-hover table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th scope="col">
                                                            Tài Khoản
                                                        </th>
                                                        <th scope="col">
                                                            Tổng Thu
                                                        </th>
                                                        <th scope="col">
                                                            Tổng Chi
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="(
                                                            item, index
                                                        ) in stats.banks" :key="`banks-${index}`">
                                                        <td>
                                                            <span class="font-weight-bold">{{
                                                                `${item.owner_name} (${item.bank_name} -
                                                                ${item.account_number})`
                                                            }}</span>
                                                        </td>
                                                        <td>
                                                            <span>{{
                                                                (item.type_in +
                                                                    item.type_addon)
                                                                | formatPrice
                                                            }}</span>
                                                        </td>
                                                        <td>
                                                            <span>{{
                                                                item.type_out
                                                                | formatPrice
                                                            }}</span>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>
                                                            <span class="font-weight-bold">Tiền Mặt</span>
                                                        </td>
                                                        <td>
                                                            <span>{{
                                                                (stats.cash_in +
                                                                    stats.cash_addon)
                                                                | formatPrice
                                                            }}</span>
                                                        </td>
                                                        <td>
                                                            <span>{{
                                                                stats.cash_out
                                                                | formatPrice
                                                            }}</span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </el-collapse-item>
                                </el-collapse>
                            </div>
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
        }
    }

};
</script>

<style>
.font-weight-bold {
    font-weight: 700 !important;
}

.el-collapse-item__header .el-collapse-item__arrow::before {
    content: "";
}

.el-collapse-item__header,
.el-collapse-item__wrap {
    border-bottom: none;
}

.el-collapse-item__header {
    font-weight: 700;
    cursor: pointer;
}

.table-content {
    max-height: 500px;
    overflow-y: scroll;
}
</style>
