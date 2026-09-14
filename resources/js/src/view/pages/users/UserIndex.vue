<template>
    <div>
        <div class="card card-custom gutter-b">
            <div class="card-header">
                <div class="card-title">
                    <h3 class="card-label">Danh sách nhân sự</h3>
                </div>
                <div class="card-title">
                    <ModalUserCreate @storeSuccess="getList"></ModalUserCreate>
                </div>
            </div>
            <div class="card-body">
                <!--                <div class="row">-->
                <!--                    <div class="col-md-3">-->
                <!--                        <div class="form-group">-->
                <!--                            <label>Thời gian tạo</label>-->
                <!--                            <date-picker v-model="query.created_at"-->
                <!--                                         type="date"-->
                <!--                                         range-->
                <!--                                         placeholder="Chọn thời gian tạo"-->
                <!--                                         format="DD-MM-YYYY" valueType="YYYY-MM-DD"-->
                <!--                            ></date-picker>-->
                <!--                        </div>-->
                <!--                    </div>-->
                <!--                    <div class="col-md-3">-->
                <!--                        <div class="form-group">-->
                <!--                            <label>Tên xe, biển số</label>-->
                <!--                            <el-input-->
                <!--                                clearable-->
                <!--                                placeholder="Tên xe, biển số"-->
                <!--                                v-model="query.name"-->
                <!--                            ></el-input>-->
                <!--                        </div>-->
                <!--                    </div>-->
                <!--                    <div class="col-md-3">-->
                <!--                        <div class="form-group">-->
                <!--                            <label>Trạng thái</label>-->
                <!--                            <el-select-->
                <!--                                filterable-->
                <!--                                class="w-100"-->
                <!--                                placeholder="Trạng thái"-->
                <!--                                v-model="query.status"-->
                <!--                                clearable-->
                <!--                            >-->
                <!--                                <el-option-->
                <!--                                    v-for="item in status"-->
                <!--                                    :key="item.id"-->
                <!--                                    :label="item.name"-->
                <!--                                    :value="item.id"-->
                <!--                                >-->
                <!--                                    <span style="float: left">{{-->
                <!--                                            item.name-->
                <!--                                        }}</span>-->
                <!--                                </el-option>-->
                <!--                            </el-select>-->

                <!--                        </div>-->
                <!--                    </div>-->
                <!--                    <div class="col-md-3">-->
                <!--                        <div class="form-group">-->
                <!--                            <label>Cửa hàng</label>-->
                <!--                            <el-select-->
                <!--                                filterable-->
                <!--                                class="w-100"-->
                <!--                                placeholder="Cửa hàng"-->
                <!--                                v-model="query.store_id"-->
                <!--                                clearable-->
                <!--                            >-->
                <!--                                <el-option-->
                <!--                                    v-for="item in stores"-->
                <!--                                    :key="item.id"-->
                <!--                                    :label="item.store_name"-->
                <!--                                    :value="item.id"-->
                <!--                                >-->
                <!--                                    <span style="float: left">{{-->
                <!--                                            item.store_name-->
                <!--                                        }}</span>-->
                <!--                                </el-option>-->
                <!--                            </el-select>-->

                <!--                        </div>-->
                <!--                    </div>-->

                <!--                    <div class="col-md-3">-->
                <!--                        <div class="form-group">-->
                <!--                            <label>Loại xe</label>-->
                <!--                            <el-select-->
                <!--                                filterable-->
                <!--                                class="w-100"-->
                <!--                                placeholder="Loại xe"-->
                <!--                                v-model="query.type"-->
                <!--                                clearable-->
                <!--                            >-->
                <!--                                <el-option-->
                <!--                                    v-for="item in types"-->
                <!--                                    :key="item.id"-->
                <!--                                    :label="item.name"-->
                <!--                                    :value="item.id"-->
                <!--                                >-->
                <!--                                    <span style="float: left">{{-->
                <!--                                            item.name-->
                <!--                                        }}</span>-->
                <!--                                </el-option>-->
                <!--                            </el-select>-->
                <!--                        </div>-->
                <!--                    </div>-->
                <!--                    <div class="col-md-3 mt-8">-->
                <!--                        <button class="btn btn-primary"-->
                <!--                                :class="{'spinner spinner-white spinner-right' : is_loading_search}"-->
                <!--                                @click="search"-->
                <!--                        >Tìm kiếm-->
                <!--                        </button>-->
                <!--                    </div>-->
                <!--                </div>-->
                <div class="example mb-10">
                    <div class="row mb-10">
                        <div class="col-md-2">
                            <el-input class="w-100" clearable placeholder="Tên, Email hoặc SĐT" v-model="query.keyword">
                            </el-input>
                        </div>
                        <div class="col-md-2">
                            <el-select v-model="query.store_id" filterable clearable placeholder="Chọn cửa hàng"
                                class="w-100">
                                <el-option v-for="item in stores" :store_id="item.id" :key="item.id"
                                    :label="item.store_name" :value="item.id">
                                </el-option>
                            </el-select>
                        </div>
                        <div class="col-md-2">
                            <el-select v-model="query.role_id" filterable clearable placeholder="Chọn quyền"
                                class="w-100">
                                <el-option v-for="(item, key) in roles" :key="`role-${key}`" :role_id="item.id"
                                    :label="item.name" :value="item.id">
                                </el-option>
                            </el-select>
                        </div>
                        <div class="col-md-2">
                            <el-date-picker class="w-100" v-model="query.start_date" type="date" format="yyyy-MM-dd"
                                value-format="yyyy-MM-dd" :picker-options="pickerStartOptions" placeholder="Từ ngày">
                            </el-date-picker>
                        </div>
                        <div class="col-md-2">
                            <el-date-picker class="w-100" v-model="query.end_date" type="date" ref="picker"
                                format="yyyy-MM-dd" value-format="yyyy-MM-dd" :picker-options="pickerEndOptions"
                                placeholder="Đến ngày">
                            </el-date-picker>
                        </div>
                        <div class="col-md-2 text-right">
                            <el-button :loading="is_loading_search" icon="fa fa-search"
                                class="btn btn-primary font-weight-bold" @click="search">
                                Tìm kiếm
                            </el-button>
                        </div>
                    </div>
                    <div class="example-preview table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col" class="min-w-130px">Tên</th>
                                    <th scope="col">Email</th>
                                    <th scope="col">Quyền</th>
                                    <th scope="col">Cửa hàng</th>
                                    <th scope="col" class="min-w-120px">
                                        Trạng thái
                                    </th>
                                    <th scope="col">Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in users.data" :key="index">
                                    <th scope="row">{{ index + 1 }}</th>
                                    <td>
                                        <span>{{ item.name }}<br /></span>
                                    </td>
                                    <td>{{ item.email }}</td>
                                    <td>
                                        {{
                                            item.role_rel
                                                ? item.role_rel.name
                                                : ""
                                        }}
                                    </td>
                                    <td>
                                        {{
                                            item.store
                                                ? item.store.store_name
                                                : ""
                                        }}
                                    </td>
                                    <td>
                                        <span :class="item.status === 'active'
                                            ? 'badge badge-primary'
                                            : 'badge badge-danger'
                                            ">{{ item.status }}</span>
                                    </td>
                                    <td>
                                        <button v-b-modal.modal-user-edit @click="item_current = item"
                                            class="btn btn-xs btn-icon btn-outline-info">
                                            <i class="far fa-edit"> </i>
                                        </button>
                                        <a title="Xóa" @click="deleteUser(item.id)" href="javascript:"
                                            class="btn btn-xs btn-icon btn-outline-danger"><i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <ModalUserEdit :item="item_current" @storeSuccess="getList"></ModalUserEdit>
            <div class="edu-paginate mx-auto text-center">
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
import { SET_BREADCRUMB } from "@/core/services/store/breadcrumbs.module";
import { mapGetters } from "vuex";
import {
    types,
    typeOfService,
    typeOfServiceDefineCss,
    status,
    brands,
    type_define,
    status_define,
    status_define_css,
} from "../../../option/vehicle";
import { STORE_GET_ALL } from "../../../core/services/store/store.module";
import ModalUserCreate from "./ModalCreateUser";
import {
    USER_DELETE,
    USER_GET_ALL,
} from "../../../core/services/store/user.module";
import ModalUserEdit from "./ModalEditUser";
import Swal from "sweetalert2";
import { ROLE_GET_ALL } from "@/core/services/store/role.module";
import queryMixin from '@/utils/queryMixin.js';

export default {
    mixins: [queryMixin],
    name: "UserIndex",
    components: { ModalUserEdit, ModalUserCreate },
    data() {
        const { page, store_id, role_id, ...restQuery } =
            this.$route?.query || {};
        return {
            query: {
                keyword: "",
                status: "",
                keyword: "",
                role_id: role_id ? +role_id : "",
                store_id: store_id ? +store_id : "",
                ...(restQuery || {}),
            },
            status: status,
            stores: [],
            roles: [],
            brands: brands,
            users: [],
            page: +page || 1,
            last_page: 1,
            types: types,
            type_define: type_define,
            typeOfServiceDefineCss: typeOfServiceDefineCss,
            type_of_service: typeOfService,
            status_define_css: status_define_css,
            status_define: status_define,
            is_loading_search: false,
            item_current: null,
            pickerStartOptions: {},
            pickerEndOptions: {},
        };
    },
    computed: {
        ...mapGetters(["currentUser"]),
    },
 
    created() {
        this.getRole();
        this.getStore();
        this.getList();
    },
    mounted() {
        this.$store.dispatch(SET_BREADCRUMB, [{ title: "Quản lý nhân sự" }]);
    },
    methods: {
        getRole() {
            this.$store.dispatch(ROLE_GET_ALL, {}).then((data) => {
                this.roles = data?.data || [];
            }).catch(() => {});
        },
        search() {
            // this.pushParamsUrl();
            this.getList();
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
        getList() {
            this.is_loading_search = true;
            this.$store
                .dispatch(USER_GET_ALL, { page: this.page, ...this.query })
                .then((data) => {
                    this.users = data?.data || [];
                    this.last_page = data?.data?.last_page || 1;
                })
                .catch(() => {})
                .finally(() => {
                    this.is_loading_search = false;
                });
        },
        clickCallback(obj) {
            this.page = obj;
            this.$router.push({ path: "", query: { page: this.page } }).catch(() => {});
            this.getList();
        },
        getStore() {
            this.$store.dispatch(STORE_GET_ALL, {}).then((data) => {
                this.stores = data?.data || [];
            }).catch(() => {});
        },
        deleteUser(id) {
            Swal.fire({
                title: "Bạn chắc chắn muốn xóa?",
                showDenyButton: true,
                showCancelButton: true,
                confirmButtonText: "Đồng ý",
                cancelButtonText: "Không",
            }).then((result) => {
                if (result.isConfirmed) {
                    this.$store
                        .dispatch(USER_DELETE, id)
                        .then((data) => {
                            Swal.fire("Hủy", "", "success");
                            this.$message.success(data.message);
                            this.getList();
                        })
                        .catch((err) => {
                            this.$message.success(err.message);
                        });
                }
            });
        },
    },
};
</script>

<style scoped>
.mx-datepicker {
    width: 100% !important;
}
</style>
