<template>
    <div>
        <div class="card card-custom gutter-b">
            <div class="card-header align-items-center">
                <div class="card-title">
                    <h3 class="card-label">Danh sách tài khoản tiền mặt</h3>
                </div>

                <!-- Start Search box -->
                <div class="d-flex pr-6 justify-content-between w-100" style="flex:1;">
                    <div class="w-100 mr-3">
                        <el-input clearable placeholder="Nhập địa chỉ cửa hàng" v-model="query.keyword"
                            @change="handleKeywordChange($event)"></el-input>
                    </div>
                    <el-button :loading="loading" icon="fa fa-search" class="btn btn-primary font-weight-bold"
                        @click="search"></el-button>
                </div>
                <!-- Ends Search box -->

                <div v-if="currentUser.role_id === 1" class="card-title">
                    <router-link :to="{ name: 'cash-create' }" class="btn btn-success">Thêm mới</router-link>
                    <button style="    margin-left: 5px;" @click="exportFile" class="btn btn-success">Export</button>
                </div>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">#</th>

                            <th scope="col">Cửa hàng</th>
                            <th scope="col">Số dư ban đầu</th>
                            <th scope="col">Số dư hiện tại</th>

                            <th scope="col">Hành động</th>
                        </tr>
                    </thead>
                    <tbody v-if="cash.length">
                        <tr v-for="(item, index) in cash" :key="index">
                            <th scope="row">{{ index + 1 }}</th>

                            <td>
                                <span class="label label-info label-inline mr-2">{{ item.store_name }}</span>
                            </td>


                            <td>{{ item.opening_balance | formatPrice }}</td>
                            <td>{{ item.current_balance | formatPrice }}</td>

                            <td>
                                <router-link v-if="currentUser.role_id === 1" :to="{ name: 'cash-update', params: { id: item.id } }" title="Sửa"
                                    class="btn btn-xs btn-icon mr-2 btn-outline-info"><i class="fas fa-pen-nib"></i>
                                </router-link>


                                <button v-b-modal.modal-show-car-rental class="btn btn-xs btn-icon btn-outline-info"
                                    title="Xem chi tiết" data-target="#rentalPopup" @click="showBankPopup(item)">
                                    <i class="far fa-eye"></i>
                                </button>
                                <a v-if="currentUser.role_id === 1" title="Xóa" @click="deleteBank(item.id)" href="javascript:"
                                    class="btn btn-xs btn-icon btn-outline-danger"><i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    </tbody>
                    <tbody v-else>
                        <tr>
                            <td colspan="6" class="text-center">Chưa có tài khoản tiền mặt</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <ModalShowCash :transactions="transactions" :cash="cash_show"></ModalShowCash>
            <div class="edu-paginate mx-auto text-center" v-if="cash.length">
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
import {EXPORT_CASH } from "@/core/services/store/exports.module";
import { SET_BREADCRUMB } from "@/core/services/store/breadcrumbs.module";
import Swal from "sweetalert2";
import { CASH_DELETE, CASH_INDEX } from "@/core/services/store/cash.module";
import ModalShowCash from "./ModalShowCash"
import queryMixin from '@/utils/queryMixin.js';

export default {
    mixins: [queryMixin],
    name: "CashIndex",
    data() {
        return {
            cash_show: null,
            cash: [],
            transactions: [],
            page: +this.$route?.query?.page || 1,
            last_page: 1,
            loading: false,
            query: {
                keyword: '',
                ...(this.$route?.query || {})
            }
        }
    },

    components: { ModalShowCash },
    computed: {
        ...mapGetters(["currentUser"])
    },
    mounted() {
        this.$store.dispatch(SET_BREADCRUMB, [{ title: "Tài khoản Tiền mặt" }]);
        this.getList();
    },
    methods: {
        exportFile(){
          
          this.$store.dispatch(EXPORT_CASH, this.query).then().catch((error) => {
              this.noticeMessage('error', 'Thất bại', error.message);
          }).finally(() => {
               
          })
      },
        showBankPopup(item) {
            this.cash_show = item;
            this.transactions = item.transactions;
        },
        getList() {
            this.loading = true;
            this.$store.dispatch(CASH_INDEX, { page: this.page, ...this.query }).then((data) => {
                this.cash = data.data.data;
                this.last_page = data.data.last_page
            }).finally(() => {
                this.loading = false;
            })
        },

        deleteBank(id) {
            Swal.fire({
                title: "Bạn chắc chắn muốn xóa?",
                showDenyButton: true,
                showCancelButton: true,
                confirmButtonText: "Đồng ý",
                cancelButtonText: "Không",
            }).then((result) => {
                if (result.isConfirmed) {
                    this.$store.dispatch(CASH_DELETE, id).then(() => {
                        Swal.fire("Xóa tài khoản thành công", "", "success");
                        this.getList();
                    }).catch(() => {
                        this.noticeMessage('error', 'Thất bại', 'Xóa tài khoản tiền mặt thất bại');
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
        }
    }
}
</script>

<style scoped></style>
