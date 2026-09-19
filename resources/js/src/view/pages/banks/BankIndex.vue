<template>
    <div>
        <div class="card card-custom gutter-b">
            <div class="card-header align-items-center">
                <div class="card-title">
                    <h3 class="card-label">Danh sách tài khoản ngân hàng</h3>
                </div>

                 <!-- Start Search box -->
                <div class="d-flex pr-6 justify-content-between w-100" style="flex:1;">
                    <div class="w-100 mr-3">
                        <search-suggest endpoint="/api/auth/banks" :params="query" query-key="keyword" fields="owner_name,account_number" @select="search" @submit="search" clearable placeholder="Nhập người thụ hưởng hoặc STK" v-model="query.keyword" @change="handleKeywordChange($event)"></search-suggest>
                    </div>
                    <el-button :loading="loading" class="btn btn-primary font-weight-bold" @click="search">Tìm kiếm</el-button>
                </div>
                <!-- Ends Search box -->

                <div v-if="currentUser.role_id === 1" class="card-title">
                    <router-link :to="{name: 'banks-create'}" class="btn btn-success">Thêm mới</router-link>
                    <button style="    margin-left: 5px;" @click="exportFile" class="btn btn-success">Export</button>
                </div>
            </div>
            <div class="card-body">
                <HimotoErrorState v-if="errorMessage" title="Không thể tải danh sách tài khoản ngân hàng" :message="errorMessage" @retry="getList" />
                <HimotoTableSkeleton v-else-if="loading" :rows="5" :columns="9" />
                <div v-else-if="banks.length" class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Ngân hàng</th>
                                <th scope="col">STK</th>
                                <th scope="col">Loại tài khoản</th>
                                <th scope="col">Người thụ hưởng</th>
                                <th scope="col">Cửa hàng</th>
                                <th scope="col">Số dư ban đầu</th>
                                <th scope="col">Số dư hiện tại</th>
                                <th scope="col">Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, index) in banks" :key="item.id || index">
                                <th scope="row">{{ (page - 1) * 10 + index + 1 }}</th>
                                <td>{{ item.bank_name }}</td>
                                <td>{{ item.account_number }}</td>
                                <td>
                                    <el-tag v-if="item.account_type === 0" type="" size="small">Thu & Chi</el-tag>
                                    <el-tag v-if="item.account_type === 1" type="success" size="small">Thu</el-tag>
                                    <el-tag v-if="item.account_type === 2" type="info" size="small">Chi</el-tag>
                                </td>
                                <td>{{ item.owner_name }}</td>
                                <td>
                                    <span class="label label-info label-inline mr-2">{{ item.store_name }}</span>
                                </td>
                                <td>{{ item.opening_balance | formatPrice }}</td>
                                <td>{{ item.current_balance | formatPrice }}</td>
                                <td>
                                    <router-link v-if="currentUser.role_id === 1" :to="{name: 'banks-update', params: {id: item.id}}"
                                                 class="btn btn-xs btn-outline-info font-weight-bold mr-1">
                                        Sửa
                                    </router-link>
                                    <button
                                            v-b-modal.modal-show-car-rental
                                            class="btn btn-xs btn-outline-primary font-weight-bold mr-1"
                                            data-target="#rentalPopup"
                                            @click="showBankPopup(item)"
                                        >
                                            Xem
                                    </button>
                                    <a v-if="currentUser.role_id === 1" @click="deleteBank(item.id)" href="javascript:"
                                       class="btn btn-xs btn-outline-danger font-weight-bold">
                                        Xóa
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <HimotoEmptyState v-else title="Chưa có tài khoản ngân hàng" description="Thử thay đổi bộ lọc hoặc thêm mới tài khoản ngân hàng." :actionText="currentUser.role_id === 1 ? 'Thêm mới tài khoản' : ''" @action="$router.push({ name: 'banks-create' })" />
            </div>
            <ModalShowBank :transactions="transactions" :bank="bank_show"></ModalShowBank>
            <div class="edu-paginate mx-auto text-center" v-if="!loading && banks.length">
                <paginate
                    v-model="page"
                    :page-count="last_page"
                    :page-range="3"
                    :margin-pages="1"
                    :click-handler="clickCallback"
                    :prev-text="'Trước'"
                    :next-text="'Sau'"
                    :container-class="'pagination b-pagination'"
                    :pageLinkClass="'page-link'"
                    :next-link-class="'next-link-item'"
                    :prev-link-class="'prev-link-item'"
                    :prev-class="'page-link'"
                    :next-class="'page-link'"
                    :page-class="'page-item'"
                >
                </paginate>
            </div>
        </div>
    </div>
</template>

<script>
import {mapGetters} from "vuex";
import {EXPORT_BANK} from "@/core/services/store/exports.module";
import {SET_BREADCRUMB} from "@/core/services/store/breadcrumbs.module";
import Swal from "sweetalert2";
import {BANK_DELETE,BANK_INDEX,BANK_SHOW} from "@/core/services/store/banks.module";
import ModalShowBank from "./ModalShowBank";
import queryMixin from '@/utils/queryMixin.js';
import HimotoTableSkeleton from "@/view/components/himoto/HimotoTableSkeleton.vue";
import HimotoEmptyState from "@/view/components/himoto/HimotoEmptyState.vue";
import HimotoErrorState from "@/view/components/himoto/HimotoErrorState.vue";
import { normalizePaginator } from "@/utils/paginatorAdapter";
import { getApiMessage } from "@/utils/apiErrorHandler";

export default {
    mixins: [queryMixin],
    name: "BankIndex",
    data() {
        return {
            bank_show: null,
            banks: [],
            transactions:[],
            page: +this.$route?.query?.page || 1,
            last_page: 1,
            loading: false,
            errorMessage: null,
            lastFetchedAt: 0,
            isFirstActivated: true,
            query: {
                keyword: '',
                ...(this.$route?.query || {})
            }
        }
    },
    components:{
        ModalShowBank,
        HimotoTableSkeleton,
        HimotoEmptyState,
        HimotoErrorState
    },
    computed: {
        ...mapGetters(["currentUser"])
    },
   
    mounted() {
        this.$store.dispatch(SET_BREADCRUMB, [{title: "Tài khoản ngân hàng"}]);
        this.getList();
    },
    activated() {
        if (this.isFirstActivated) {
            this.isFirstActivated = false;
            return;
        }
        const queryPage = +this.$route?.query?.page || 1;
        const queryKeyword = this.$route?.query?.keyword || '';
        const queryChanged = queryPage !== this.page || queryKeyword !== (this.query.keyword || '');
        if (queryChanged) {
            this.page = queryPage;
            this.query.keyword = queryKeyword;
        }
        if (!queryChanged && this.lastFetchedAt && Date.now() - this.lastFetchedAt < 60000) {
            return;
        }
        this.getList();
    },
    methods: {
        showBankPopup(item){
            this.bank_show = item;
            this.transactions = [];
            this.$store.dispatch(BANK_SHOW, item.id).then((response) => {
                this.bank_show = response.data;
                this.transactions = response.data.transactions || [];
            }).catch((error) => {
                this.noticeMessage('error', 'Thất bại', getApiMessage(error));
            });
        },
        getList() {
            this.loading = true;
            this.errorMessage = null;
            this.$store.dispatch(BANK_INDEX, {page: this.page, ...this.query}).then((data) => {
                const paginated = normalizePaginator(data);
                this.banks = paginated.items || [];
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
                    this.$store.dispatch(BANK_DELETE, id).then(() => {
                        Swal.fire("Xóa tài khoản thành công", "", "success");
                        this.getList();
                    }).catch(() => {
                        this.noticeMessage('error', 'Thất bại', 'Xóa tài khoản ngân hàng thất bại');
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
        },
        exportFile(){
          
            this.$store.dispatch(EXPORT_BANK, this.query).then().catch((error) => {
                this.noticeMessage('error', 'Thất bại', error.message);
            }).finally(() => {
                 
            })
        },

    }
}
</script>

<style scoped>

</style>
