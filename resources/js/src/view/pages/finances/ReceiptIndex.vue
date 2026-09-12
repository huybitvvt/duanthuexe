<template>
    <div class="card card-custom gutter-b">
        <div class="card-header">
            <div class="card-title col-md-12 d-flex justify-content-between">
                <h3 class="card-label">Phiếu thu chi</h3>
                <div>
                    <router-link :to="{ name: 'receipt-create' }" class="btn btn-success">Thêm mới</router-link>
                </div>
            </div>
        </div>
        <div>
            <div class="card card-custom gutter-b">
                <div class="card-body">


                    <div class="example mb-10">
                        <div class="row mb-10">


                            <div class="col-md-2">
                                <el-input clearable placeholder="Nhập ghi chú" v-model="query.keyword"
                                    @change="handleKeywordChange($event)"></el-input>
                            </div>
                            <div v-if="currentUser.role_id === 1" class="col-md-2">
                                <el-select v-model="query.store_id" filterable clearable placeholder="Chọn cửa hàng"
                                    class="w-100">
                                    <el-option v-for="item in stores" :store_id="item.id" :key="item.id"
                                        :label="item.store_name" :value="item.id">
                                    </el-option>
                                </el-select>
                            </div>
                            <div class="col-md-2">
                                <el-select v-model="query.payment_method" filterable clearable
                                    placeholder="Chọn loại thanh toán" class="w-100">
                                    <el-option v-for="(item, key) in payment_methods" :key="`method-${key}`"
                                        :payment_method="key" :label="item" :value="key">
                                    </el-option>
                                </el-select>
                            </div>
                            <div class="col-md-2">
                                <el-date-picker class="w-100" v-model="query.start_date" type="date" format="yyyy-MM-dd"
                                    value-format="yyyy-MM-dd" :picker-options="pickerStartOptions"
                                    placeholder="Từ ngày">
                                </el-date-picker>
                            </div>
                            <div class="col-md-2">
                                <el-date-picker class="w-100" v-model="query.end_date" type="date" ref="picker"
                                    format="yyyy-MM-dd" value-format="yyyy-MM-dd" :picker-options="pickerEndOptions"
                                    placeholder="Đến ngày">
                                </el-date-picker>
                            </div>
                            <div class="col-md-2 text-right">
                                <el-button :loading="loading" icon="fa fa-search" style="width: 100%;"
                                    class="btn btn-primary font-weight-bold" @click="search">
                                    Tìm kiếm
                                </el-button>
                            </div>

                        </div>
                        <div class="example-preview table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>

                                        <th scope="col">Mã</th>
                                        <th scope="col" class="min-w-130px">Ngày</th>
                                        <th scope="col">Cửa hàng</th>
                                        <th scope="col">Người thực hiện</th>
                                        <th scope="col">Loại phiếu</th>
                                        <th scope="col">Phương thức</th>
                                        <th scope="col">Số tiền</th>
                                        <th scope="col">Ghi chú</th>

                                        <th scope="col">Hành động</th>
                                    </tr>
                                </thead>
                                <tbody v-if="receipts.length">
                                    <tr v-for="(item, index) in receipts" :key="index">
                                        <td>{{ item.id }}</td>
                                        <td>
                                            {{
                        item.created_at | formatDateTime
                    }}
                                        </td>
                                        <td>
                                            <span class="label label-info label-inline mr-2">{{ item.store.store_name
                                                }}</span>
                                        </td>
                                        <td>{{ item.user.name }}</td>
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


                                        <td>
                                            <router-link :to="{ name: 'receipt-update', params: { id: item.id } }"
                                                title="Sửa" class="btn btn-xs btn-icon mr-2 btn-outline-info"><i
                                                    class="fas fa-pen-nib"></i>
                                            </router-link>



                                            <button v-b-modal.modal-show-car-rental
                                                class="btn btn-xs btn-icon btn-outline-info" title="Xem chi tiết"
                                                @click="showBankPopup(item)">
                                                <i class="far fa-eye"></i>
                                            </button>
                                            <a v-if="currentUser.role_id === 1" title="Xóa"
                                                @click="deleteReceipt(item.id)" href="javascript:"
                                                class="btn btn-xs btn-icon btn-outline-danger"><i
                                                    class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                </tbody>
                                <tbody v-else>
                                    <tr>
                                        <td colspan="6" class="text-center">Không có phiếu thu chi</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <ModalShowReceipt :receipt="receipt_show"></ModalShowReceipt>
                <div class="edu-paginate mx-auto text-center" v-if="receipts.length">
                    <paginate v-model="page" :page-count="last_page" :page-range="3" :margin-pages="1"
                        :click-handler="clickCallback" :prev-text="'Trước'" :next-text="'Sau'"
                        :container-class="'pagination b-pagination'" :pageLinkClass="'page-link'"
                        :next-link-class="'next-link-item'" :prev-link-class="'prev-link-item'"
                        :prev-class="'page-link'" :next-class="'page-link'" :page-class="'page-item'">
                    </paginate>
                </div>

            </div>
        </div>
    </div>
</template>

<script>
import { types, payment_methods } from "../../../option/transactionOption";
import { mapGetters } from "vuex";
import { SET_BREADCRUMB } from "@/core/services/store/breadcrumbs.module";
import Swal from "sweetalert2";
import { RECEIPT_DELETE, RECEIPT_INDEX } from "@/core/services/store/receipt.module";
import  ModalShowReceipt  from './ModalShowReceipt';
import moment from "moment-timezone";
import { getTextShort } from "../../../utils";
import { STORE_GET_ALL } from "@/core/services/store/store.module";
import queryMixin from '@/utils/queryMixin.js';

export default {
    mixins: [queryMixin],
    name: "ReceiptIndex",
    data() {
        return {

            moment: moment,
            types: types,
            payment_methods: payment_methods,
            receipt_show: null,
            receipts: [],
            stores: [],
            page: +this.$route?.query?.page || 1,
            last_page: 1,
            loading: false,
            query: {
                keyword: '',
                ...(this.$route?.query || {})
            },
            pickerStartOptions: {},
            pickerEndOptions: {},
        }
    },
    components: {
        ModalShowReceipt
    },
    computed: {
        ...mapGetters(["currentUser"])
    },
    created() {

        this.getStore();

    },
  
    mounted() {
        this.$store.dispatch(SET_BREADCRUMB, [{ title: "Phiếu thu chi" }]);
        this.getList();
    },
    methods: {
        showBankPopup(item) {
            this.receipt_show = item;

        },
        getStore() {
            this.$store.dispatch(STORE_GET_ALL, {}).then((data) => {
                this.stores = data.data;
            });
        },
        showReceiptPopup(item) {
            this.receipt_show = item;

        },
        getList() {
            this.loading = true;
            this.$store.dispatch(RECEIPT_INDEX, { page: this.page, ...this.query }).then((data) => {
                this.receipts = data.data.data;
                this.last_page = data.data.last_page
            }).finally(() => {
                this.loading = false;
            })
        },

        deleteReceipt(id) {
            Swal.fire({
                title: "Bạn chắc chắn muốn xóa phiếu này?",
                showDenyButton: true,
                showCancelButton: true,
                confirmButtonText: "Đồng ý",
                cancelButtonText: "Không",
            }).then((result) => {
                if (result.isConfirmed) {
                    this.$store.dispatch(RECEIPT_DELETE, id).then(() => {
                        Swal.fire("Xóa phiếu thành công", "", "success");
                        this.getList();
                    }).catch((e) => {
                        this.noticeMessage('error', 'Xóa phiếu thất bại', e.message);
                    });
                }
            });

        },
        clickCallback(obj) {
            this.page = obj;
            this.getList();
        },

        search() {
            // this.pushParamsUrl(this.query);
            this.getList();
        },
        pushParamsUrl() {
            this.$router.push({
                path: '', query: {
                    page: this.page,
                    ...this.query
                }
            })
        },
        handleKeywordChange(value) {
            // case click remove
            if (!value) {
                this.search();
            }
        },
        getNote(str) {
            return getTextShort(str);
        },
        getBankInfo(item) {
            if (item.payment_method === 2 || (item.payment_method === 3 && item.bank_id)) {
                return `${item.bank.bank_name}</br>${item.bank.owner_name}</br>${item.bank.account_number}`;
            } else if (item.payment_method === 1) {
                return payment_methods[1];
            } else {
                // null => Tiền Mặt
                return payment_methods[1];
            }
        }
    }
}
</script>

<style scoped></style>













