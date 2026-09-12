<template>
    <div>
        <div class="card card-custom gutter-b">
            <div class="card-header">
                <div class="card-title">
                    <h3 class="card-label">Danh sách cửa hàng</h3>
                </div>
                <div class="card-title">
                    <router-link :to="{name: 'stores-create'}" class="btn btn-success">Thêm mới</router-link>
                </div>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Tên</th>
                        <th scope="col">SĐT</th>
                        <th scope="col">Địa chỉ</th>
                        <th scope="col">Trạng thái</th>
                        <th scope="col">Hành động</th>
                    </tr>
                    </thead>
                    <tbody v-if="customers.length">
                    <tr v-for="(item, index) in customers" :key="index">
                        <th scope="row">{{ index + 1 }}</th>
                        <td>{{ item.store_name }}</td>
                        <td>{{ item.store_phone }}</td>
                        <td>{{ item.store_address }}</td>
                        <td class="text-danger">{{ item.status }}</td>
                        <td>
                            <router-link :to="{name: 'stores-update', params: {id: item.id}}" title="Sửa"
                                         class="btn btn-xs btn-icon mr-2 btn-outline-info"><i
                                class="fas fa-pen-nib"></i>
                            </router-link>
                            <a v-if="currentUser.role_id === 1" title="Xóa" @click="deleteStore(item.id)" href="javascript:"
                               class="btn btn-xs btn-icon btn-outline-danger"><i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    </tbody>
                    <tbody v-else>
                    <tr>
                        <td colspan="6" class="text-center">Không tìm thấy cửa hàng</td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <div class="edu-paginate mx-auto text-center" v-if="customers.length">
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
import {SET_BREADCRUMB} from "@/core/services/store/breadcrumbs.module";
import Swal from "sweetalert2";
import {CUSTOMER_DELETE} from "@/core/services/store/customers.module";
import {STORE_INDEX, STORE_DELETE} from "../../../core/services/store/store.module";

export default {
    name: "StoreIndex",
    data() {
        return {
            customers: [],
            page: +this.$route?.query?.page || 1,
            last_page: 1,
        }
    },
    computed: {
        ...mapGetters(["currentUser"])
    },
    mounted() {
        this.$store.dispatch(SET_BREADCRUMB, [{title: "Quản lý cửa hàng"}]);
        this.getList();
    },
    methods: {
        getList() {
            this.$store.dispatch(STORE_INDEX, {page: this.page}).then((data) => {
                this.customers = data.data.data;
                this.last_page = data.data.last_page
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
		deleteStore(id) {
            Swal.fire({
                title: "Bạn chắc chắn muốn xóa cửa hàng này?",
                showDenyButton: true,
                showCancelButton: true,
                confirmButtonText: "Đồng ý",
                cancelButtonText: "Không",
            }).then((result) => {
                if (result.isConfirmed) {
                    this.$store.dispatch(STORE_DELETE, id).then(() => {
                        Swal.fire("Xóa thành công", "", "success");
                        this.getList();
                    }).catch(() => {
                        this.noticeMessage('error', 'Thất bại', 'Xóa cửa hàng thất bại');
                    });
                }
            });

        },
        clickCallback(obj) {
            this.page = obj;
            this.getList();
        },
    }
}
</script>

<style scoped>

</style>
