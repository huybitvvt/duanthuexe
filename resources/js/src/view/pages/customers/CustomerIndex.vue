<template>
    <div>
        <div class="card card-custom gutter-b">
            <div class="card-header align-items-center">
                <div class="card-title">
                    <h3 class="card-label">Danh sách khách hàng</h3>
                </div>

                <!-- Start Search box -->
                <div class="d-flex pr-6 justify-content-between w-100" style="flex:1;">
                    <div class="w-100 mr-3">
                        <el-input clearable placeholder="Nhập Tên hoặc SĐT" v-model="query.keyword"
                            @change="handleKeywordChange($event)"></el-input>
                    </div>
                    <el-button :loading="loading" icon="fa fa-search" class="btn btn-primary font-weight-bold"
                        @click="search"></el-button>
                </div>
                <!-- Ends Search box -->

                <div class="card-title">
                    <router-link :to="{ name: 'customers-create' }" class="btn btn-success">Thêm mới</router-link>
                </div>

                <div class="card-title">
                    <button @click="exportCustomers" class="btn btn-success">Export</button>
                </div>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Tên</th>
                            <th scope="col">Số CMTND/CCCD</th>
                            <th scope="col">SĐT</th>
                            <th scope="col">Địa chỉ</th>
                            <th scope="col">Cảnh báo</th>
                            <th scope="col">Trạng thái</th>
                            <th scope="col">Hành động</th>
                        </tr>
                    </thead>
                    <tbody v-if="customers.length">
                        <tr v-for="(item, index) in customers" :key="index">
                            <th scope="row">{{ index + 1 }}</th>
                            <td>{{ item.name }}</td>
                            <td>{{ item.id_card }}</td>
                            <td>{{ item.phone }}</td>
                            <td>{{ item.address }}</td>
                            <td class="text-danger">
                                <el-tooltip :content="item.warning">
                                    <span>{{ getWarning(item.warning) }}</span>
                                </el-tooltip>
                            </td>
                            <td>
                                <span class="label label-inline label-light-primary font-weight-bold">
                                    {{ item.status == 1 ? 'Hoàn thành' : 'Chưa hoàn thành' }}
                                </span>
                            </td>
                            <td>
                                <button v-b-modal.modal-show-car-rental class="btn btn-xs btn-icon btn-outline-info"
                                    title="Xem chi tiết" @click="showPopup(item)">
                                    <i class="far fa-eye"></i>
                                </button>
                                <router-link :to="{ name: 'customers-update', params: { id: item.id } }" title="Sửa"
                                    class="btn btn-xs btn-icon mr-2 btn-outline-info"><i class="fas fa-pen-nib"></i>
                                </router-link>
                                <a v-if="currentUser.role_id === 1" title="Xóa" @click="deleteCustomer(item.id)" href="javascript:"
                                    class="btn btn-xs btn-icon btn-outline-danger"><i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    </tbody>
                    <tbody v-else>
                        <tr>
                            <td colspan="6" class="text-center">Chưa có khách hàng</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <ModalShowCustomer :customer="customer_show"></ModalShowCustomer>
            <div class="edu-paginate mx-auto text-center" v-if="customers.length">
                <paginate v-model="page" :page-count="last_page" :page-range="3" :margin-pages="1"
                    :click-handler="clickCallback" :prev-text="'Trước'" :next-text="'Sau'"
                    :container-class="'pagination b-pagination'" :pageLinkClass="'page-link'"
                    :next-link-class="'next-link-item'" :prev-link-class="'prev-link-item'" :prev-class="'page-link'"
                    :next-class="'page-link'" :page-class="'page-item'">
                </paginate>
            </div>
        </div>
    </div>
</template>

<script>
import { mapGetters } from "vuex";
import { SET_BREADCRUMB } from "@/core/services/store/breadcrumbs.module";
import Swal from "sweetalert2";
import { CUSTOMER_DELETE, CUSTOMER_INDEX } from "@/core/services/store/customers.module";
import { getTextShort } from '../../../utils';
import ModalShowCustomer from "./ModalShowCustomer";
import { ORDER_STATUS_DEFINE } from '../../../option/orderOption';
import { EXPORT_CUSTOMERS } from "@/core/services/store/exports.module";
import queryMixin from '@/utils/queryMixin.js';


export default {
    mixins: [queryMixin],
    name: "CustomerIndex",
    data() {
        return {
            customer_show: null,
            customers: [],
            page: +this.$route?.query?.page || 1,
            last_page: 1,
            loading: true,
            query: {
                keyword: '',
                ...(this.$route?.query || {})
            }
        }
    },
  
    components: {
        ModalShowCustomer
    },
    computed: {
        ...mapGetters(["currentUser"])
    },
    mounted() {
        this.$store.dispatch(SET_BREADCRUMB, [{ title: "Quản lý khách hàng" }]);
        this.getList();
    },
    methods: {
        showPopup(item) {
            this.customer_show = item;

        },
        getWarning(str) {
            return getTextShort(str);
        },
        getList() {
            this.loading = true;
            this.$store.dispatch(CUSTOMER_INDEX, { page: this.page, ...this.query }).then((data) => {
                this.customers = data.data.data;
                this.last_page = data.data.last_page
            }).finally(() => {
                this.loading = false;
            })
        },

        deleteCustomer(id) {
            Swal.fire({
                title: "Bạn chắc chắn muốn huỷ?",
                showDenyButton: true,
                showCancelButton: true,
                confirmButtonText: "Đồng ý",
                cancelButtonText: "Không",
            }).then((result) => {
                if (result.isConfirmed) {
                    this.$store.dispatch(CUSTOMER_DELETE, id).then(() => {
                        Swal.fire("Hủy", "", "success");
                        this.getList();
                    }).catch(() => {
                        this.noticeMessage('error', 'Thất bại', 'Xóa khách hàng thất bại');
                    });
                }
            });

        },
        clickCallback(obj) {
            this.page = obj;
            this.getList();
        },

        // search data
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
        exportCustomers() {
            this.loading = true;
            this.$store.dispatch(EXPORT_CUSTOMERS, this.query).then().catch((error) => {
                this.noticeMessage('error', 'Thất bại', error.message);
            }).finally(() => {
                this.loading = false;
            })
        }
    }
}
</script>

<style scoped></style>
