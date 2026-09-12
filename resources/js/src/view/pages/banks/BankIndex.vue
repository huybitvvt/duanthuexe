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
                        <el-input clearable placeholder="Nhập người thụ hưởng hoặc STK" v-model="query.keyword" @change="handleKeywordChange($event)"></el-input>
                    </div>
                    <el-button :loading="loading" icon="fa fa-search" class="btn btn-primary font-weight-bold" @click="search"></el-button>
                </div>
                <!-- Ends Search box -->

                <div v-if="currentUser.role_id === 1" class="card-title">
                    <router-link :to="{name: 'banks-create'}" class="btn btn-success">Thêm mới</router-link>
                    <button style="    margin-left: 5px;" @click="exportFile" class="btn btn-success">Export</button>
                </div>
            </div>
            <div class="card-body">
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
                    <tbody v-if="banks.length">
                        <tr v-for="(item, index) in banks" :key="index">
                            <th scope="row">{{ index + 1 }}</th>
                            <td>{{ item.bank_name }}</td>
                            <td>{{ item.account_number }}</td>
                            <td>
								<el-tag v-if="item.account_type === 0" type="" size="small">Thu & Chi</el-tag>
								<el-tag v-if="item.account_type === 1" type="success" size="small">Thu</el-tag>
								<el-tag v-if="item.account_type === 2" type="info" size="small">Chi</el-tag>
							</td>
                            <td>{{ item.owner_name }}</td>
                            <td>      
                                <span
                                    
                                    class="label label-info label-inline mr-2"
                                >{{ item.store_name }}</span
                                >
                            </td>

                            
                            <td>{{ item.opening_balance | formatPrice }}</td>
                            <td>{{ item.current_balance | formatPrice }}</td>
                        
                            <td>
                                <router-link v-if="currentUser.role_id === 1" :to="{name: 'banks-update', params: {id: item.id}}" title="Sửa"
                                             class="btn btn-xs btn-icon mr-2 btn-outline-info"><i
                                    class="fas fa-pen-nib"></i>
                                </router-link>


                                <button
                                        v-b-modal.modal-show-car-rental
                                        class="btn btn-xs btn-icon btn-outline-info"
                                        title="Xem chi tiết"
                                        data-target="#rentalPopup"
                                        @click="showBankPopup(item)"
                                    >
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
                            <td colspan="6" class="text-center">Chưa có ngân hàng</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <ModalShowBank :transactions="transactions" :bank="bank_show"></ModalShowBank>
            <div class="edu-paginate mx-auto text-center" v-if="banks.length">
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
import {BANK_DELETE,BANK_INDEX} from "@/core/services/store/banks.module";
import ModalShowBank from "./ModalShowBank"
import queryMixin from '@/utils/queryMixin.js';


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
            query: {
                keyword: '',
                ...(this.$route?.query || {})
            }
        }
    },
    components:{ModalShowBank},
    computed: {
        ...mapGetters(["currentUser"])
    },
   
    mounted() {
        this.$store.dispatch(SET_BREADCRUMB, [{title: "Tài khoản ngân hàng"}]);
        this.getList();
    },
    methods: {
        showBankPopup(item){
            this.bank_show = item;
            this.transactions = item.transactions;
        },
        getList() {
            this.loading = true;
            this.$store.dispatch(BANK_INDEX, {page: this.page, ...this.query}).then((data) => {
                this.banks = data.data.data;
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
