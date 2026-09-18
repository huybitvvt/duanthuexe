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
                    <el-button :loading="loading" class="btn btn-primary font-weight-bold"
                        @click="search">Tìm kiếm</el-button>
                </div>
                <!-- Ends Search box -->

                <div v-if="currentUser.role_id === 1" class="card-title">
                    <router-link :to="{ name: 'cash-create' }" class="btn btn-success">Thêm mới</router-link>
                    <button style="    margin-left: 5px;" @click="exportFile" class="btn btn-success">Export</button>
                </div>
            </div>
            <div class="card-body">
                <HimotoErrorState v-if="errorMessage" title="Không thể tải danh sách tài khoản tiền mặt" :message="errorMessage" @retry="getList" />
                <HimotoTableSkeleton v-else-if="loading" :rows="5" :columns="5" />
                <div v-else-if="cash.length" class="table-responsive">
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
                        <tbody>
                            <tr v-for="(item, index) in cash" :key="item.id || index">
                                <th scope="row">{{ (page - 1) * 10 + index + 1 }}</th>

                                <td>
                                    <span class="label label-info label-inline mr-2">{{ item.store_name }}</span>
                                </td>


                                <td>{{ item.opening_balance | formatPrice }}</td>
                                <td>{{ item.current_balance | formatPrice }}</td>

                                <td>
                                    <router-link v-if="currentUser.role_id === 1" :to="{ name: 'cash-update', params: { id: item.id } }"
                                        class="btn btn-xs btn-outline-info font-weight-bold mr-1">Sửa
                                    </router-link>

                                    <button v-b-modal.modal-show-car-rental class="btn btn-xs btn-outline-primary font-weight-bold mr-1"
                                        data-target="#rentalPopup" @click="showBankPopup(item)">
                                        Xem
                                    </button>
                                    <a v-if="currentUser.role_id === 1" @click="deleteBank(item.id)" href="javascript:"
                                        class="btn btn-xs btn-outline-danger font-weight-bold">Xóa
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <HimotoEmptyState v-else title="Chưa có tài khoản tiền mặt" description="Thử thay đổi bộ lọc hoặc thêm mới tài khoản tiền mặt." :actionText="currentUser.role_id === 1 ? 'Thêm mới tài khoản' : ''" @action="$router.push({ name: 'cash-create' })" />
            </div>
            <ModalShowCash :transactions="transactions" :cash="cash_show"></ModalShowCash>
            <div class="edu-paginate mx-auto text-center" v-if="!loading && cash.length">
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
import { CASH_DELETE, CASH_INDEX, CASH_SHOW } from "@/core/services/store/cash.module";
import ModalShowCash from "./ModalShowCash";
import queryMixin from '@/utils/queryMixin.js';
import HimotoTableSkeleton from "@/view/components/himoto/HimotoTableSkeleton.vue";
import HimotoEmptyState from "@/view/components/himoto/HimotoEmptyState.vue";
import HimotoErrorState from "@/view/components/himoto/HimotoErrorState.vue";
import { normalizePaginator } from "@/utils/paginatorAdapter";
import { getApiMessage } from "@/utils/apiErrorHandler";

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
            errorMessage: null,
            lastFetchedAt: 0,
            query: {
                keyword: '',
                ...(this.$route?.query || {})
            }
        }
    },

    components: {
        ModalShowCash,
        HimotoTableSkeleton,
        HimotoEmptyState,
        HimotoErrorState
    },
    computed: {
        ...mapGetters(["currentUser"])
    },
    mounted() {
        this.$store.dispatch(SET_BREADCRUMB, [{ title: "Tài khoản Tiền mặt" }]);
        this.getList();
    },
    activated() {
        const queryPage = +this.$route?.query?.page || 1;
        const queryKeyword = this.$route?.query?.keyword || '';
        const paramsChanged = queryPage !== this.page || queryKeyword !== (this.query.keyword || '');
        const isTtlExpired = !this.lastFetchedAt || (Date.now() - this.lastFetchedAt > 60000);

        if (paramsChanged) {
            this.page = queryPage;
            this.query.keyword = queryKeyword;
            this.getList();
        } else if (isTtlExpired) {
            this.getList();
        }
    },
    methods: {
        exportFile(){
          this.$store.dispatch(EXPORT_CASH, this.query).then().catch((error) => {
              this.noticeMessage('error', 'Thất bại', error.message);
          }).finally(() => {
               
          });
        },
        showBankPopup(item) {
            this.cash_show = item;
            this.transactions = [];
            this.$store.dispatch(CASH_SHOW, item.id).then((response) => {
                this.cash_show = response.data;
                this.transactions = response.data.transactions || [];
            }).catch((error) => {
                this.noticeMessage('error', 'Thất bại', getApiMessage(error));
            });
        },
        getList() {
            this.loading = true;
            this.errorMessage = null;
            this.$store.dispatch(CASH_INDEX, { page: this.page, ...this.query }).then((data) => {
                const paginated = normalizePaginator(data);
                this.cash = paginated.items || [];
                this.last_page = paginated.lastPage || 1;
                this.lastFetchedAt = Date.now();
            }).catch((err) => {
                this.errorMessage = getApiMessage(err);
            }).finally(() => {
                this.loading = false;
            });
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
            this.pushParamsUrl();
            this.getList();
        },

        search() {
            this.page = 1;
            this.pushParamsUrl();
            this.getList();
        },
        pushParamsUrl() {
            this.$router.push({
                path: '',
                query: {
                    page: this.page,
                    ...this.query
                }
            }).catch(() => {});
        },
        handleKeywordChange(value) {
            if (!value) {
                this.search();
            }
        }
    }
}
</script>

<style scoped></style>
