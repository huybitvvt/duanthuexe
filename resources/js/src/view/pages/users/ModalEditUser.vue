<template>
    <div>
        <b-modal id="modal-user-edit" title="Sửa nhân viên" size="xl" centered hide-footer>
            <div class="card card-custom gutter-b">
                <div class="card-header card-header-tabs-line">
                    <div class="card-toolbar">
                        <ul class="nav nav-tabs nav-bold nav-tabs-line">
                            <li class="nav-item" @click="activeTab(1)">
                                <a class="nav-link" v-bind:class="{ active: tab_active1 }" data-toggle="tab">
                                    <span class="nav-icon"><i class="flaticon2-user"></i></span>
                                    <span class="nav-text">Sửa nhân viên</span>
                                </a>
                            </li>
                            <li class="nav-item" @click="activeTab(2)">
                                <a class="nav-link" v-bind:class="{ active: tab_active2 }" data-toggle="tab">
                                    <span class="nav-icon"><i class="flaticon2-lock"></i></span>
                                    <span class="nav-text">Đổi mật khẩu</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="tab-content">
                    <div class="tab-pane fade" v-bind:class="{
                        show: show_active_1,
                        active: show_active_1,
                    }" role="tabpanel" aria-labelledby="kt_tab_pane_1_4">
                        <div class="card-body">
                            <ValidationObserver v-slot="{ handleSubmit }" ref="form">
                                <form class="form" @submit.prevent="handleSubmit(handleOk)">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Họ và tên</label>
                                                <ValidationProvider vid="name" name="Tên nhân sự" rules="required"
                                                    v-slot="{ errors, classes }">
                                                    <el-input class="w-100" placeholder="Tên nhân sự" v-model="user.name"
                                                        :class="classes" />
                                                    <div class="fv-plugins-message-container">
                                                        <div data-field="name" data-validator="notEmpty"
                                                            class="fv-help-block">
                                                            {{ errors[0] }}
                                                        </div>
                                                    </div>
                                                </ValidationProvider>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Email</label>
                                                <ValidationProvider vid="email" name="Email nhân sự" rules="required|email"
                                                    v-slot="{ errors, classes }">
                                                    <el-input placeholder="Email" v-model="user.email"
                                                        :class="classes"></el-input>
                                                    <div class="fv-plugins-message-container">
                                                        <div data-field="email" data-validator="notEmpty"
                                                            class="fv-help-block">
                                                            {{ errors[0] }}
                                                        </div>
                                                    </div>
                                                </ValidationProvider>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>SĐT</label>
                                                <ValidationProvider vid="phone" name="Số điện thoại nhân sự"
                                                    rules="required|numeric" v-slot="{ errors, classes }">
                                                    <el-input placeholder="SĐT nhân sự" v-model="user.phone"
                                                        :class="classes"></el-input>
                                                    <div class="fv-plugins-message-container">
                                                        <div data-field="phone" data-validator="notEmpty"
                                                            class="fv-help-block">
                                                            {{ errors[0] }}
                                                        </div>
                                                    </div>
                                                </ValidationProvider>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Địa chỉ</label>
                                                <ValidationProvider vid="address" name="Địa chỉ" rules=""
                                                    v-slot="{ errors, classes }">
                                                    <el-input type="textarea" placeholder="Địa chỉ nhân sự"
                                                        v-model="user.address" :class="classes"></el-input>
                                                    <div class="fv-plugins-message-container">
                                                        <div data-field="address" data-validator="notEmpty"
                                                            class="fv-help-block">
                                                            {{ errors[0] }}
                                                        </div>
                                                    </div>
                                                </ValidationProvider>
                                            </div>
                                        </div>
                                        <div :class="`${isQuanTriVien ? 'col-md-12' : 'col-md-6'}`">
                                            <div class="form-group">
                                                <label>Phân quyền</label>
                                                <ValidationProvider vid="store_id" name="Quyền" rules="required"
                                                    v-slot="{ errors, classes }">
                                                    <el-select filterable class="w-100" placeholder="Chọn quyền"
                                                        v-model="user.role_id" clearable :class="classes" @change="
                                                            changeRole($event)
                                                            ">
                                                        <el-option v-for="item in roles" :key="item.id" :label="item.name"
                                                            :value="item.id"><span style="
                                                                    float: left;
                                                                ">{{
                                                                    item.name
                                                                }}</span>
                                                        </el-option>
                                                    </el-select>
                                                    <div class="fv-plugins-message-container">
                                                        <div data-field="name" data-validator="notEmpty"
                                                            class="fv-help-block">
                                                            {{ errors[0] }}
                                                        </div>
                                                    </div>
                                                </ValidationProvider>
                                            </div>
                                        </div>
                                        <div v-if="!isQuanTriVien" class="col-md-6">
                                            <div class="form-group">
                                                <label>Cửa hàng</label>
                                                <ValidationProvider vid="store_id" name="Cừa hàng" rules="required"
                                                    v-slot="{ errors, classes }">
                                                    <el-select filterable class="w-100" placeholder="Cửa hàng"
                                                        v-model="user.store_id" clearable :class="classes">
                                                        <el-option v-for="item in stores" :key="item.id" :label="item.store_name
                                                            " :value="item.id">
                                                            <span style="
                                                                    float: left;
                                                                ">{{
                                                                    item.store_name
                                                                }}</span>
                                                        </el-option>
                                                    </el-select>
                                                    <div class="fv-plugins-message-container">
                                                        <div data-field="name" data-validator="notEmpty"
                                                            class="fv-help-block">
                                                            {{ errors[0] }}
                                                        </div>
                                                    </div>
                                                </ValidationProvider>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label>Trạng thái</label>
                                                <div>
                                                    <el-switch v-model="compStatus" title="Status" active-text="Đang hoạt động"
                                                        inactive-text="Đã nghỉ việc">
                                                    </el-switch>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-toolbar">
                                        <el-button native-type="submit" type="btn btn-success mr-2" :loading="loading">
                                            Cập nhật
                                        </el-button>
                                    </div>
                                </form>
                            </ValidationObserver>
                        </div>
                    </div>
                </div>
                <div class="tab-content">
                    <div class="tab-pane fade" v-bind:class="{
                        show: show_active_2,
                        active: show_active_2,
                    }" role="tabpanel" aria-labelledby="kt_tab_pane_1_4">
                        <div class="card-body">
                            <UserChangePassword :user="user"></UserChangePassword>
                        </div>
                    </div>
                </div>
            </div>
        </b-modal>
    </div>
</template>

<script>
import { STORE_GET_ALL } from "../../../core/services/store/store.module";
import { ROLE_GET_ALL } from "../../../core/services/store/role.module";
import { USER_UPDATE } from "../../../core/services/store/user.module";
import UserChangePassword from "./components/UserChangePassword";
import { isRole } from "../../../utils";
import { getApiMessage, getApiValidationErrors } from "@/utils/apiErrorHandler";

export default {
    name: "ModalUserEdit",
    components: { UserChangePassword },
    props: {
        item: {
            type: Object,
            default: () => {
                return null;
            },
        },
    },
    data() {
        return {
            tab_active1: true,
            tab_active2: false,
            show_active_1: true,
            show_active_2: false,
            loading: false,
            user: {
                name: "",
                email: "",
                password: "",
                confirm_password: "",
                role_id: "",
                phone: "",
                address: "",
                store_id: "",
            },
            stores: [],
            roles: [],
            isQuanTriVien: false,
            userStatusTmp: false,
        };
    },
    computed: {
        compStatus: {
            get() {
                return this.userStatusTmp;
            },
            set(val) {
                this.userStatusTmp = val;
            },
        },
    },
    watch: {
        item() {
            this.user = this.item;
            console.log("this.item", this.item.status);
            if (this.item?.status) {
                console.log(
                    '!!this.user.status === "active";',
                    this.item.status === "active",
                );
                console.log({ active: "active" });
                this.userStatusTmp = this.item.status === "active";
            }
            if (this.user?.role_id != null) {
                this.isQuanTriVien = this.isQuanTriVienFn(this.user.role_id);
            }
            if (this.user.store_id === 0) {
                this.user.store_id = "";
            }
            if (this.user.role_id === 0) {
                this.user.role_id = "";
            }
        },
    },
    mounted() {
        this.getStore();
        this.getRoles();
    },
    methods: {
        activeTab(n) {
            if (n == 1) {
                this.tab_active1 = true;
                this.tab_active2 = false;
                this.show_active_1 = true;
                this.show_active_2 = false;
            } else {
                this.tab_active1 = false;
                this.tab_active2 = true;
                this.show_active_1 = false;
                this.show_active_2 = true;
            }
        },
        getStore() {
            this.$store.dispatch(STORE_GET_ALL, {}).then((data) => {
                this.stores = data.data;
            });
        },
        getRoles() {
            this.$store.dispatch(ROLE_GET_ALL, {}).then((data) => {
                this.roles = data.data;
            });
        },
        handleOk() {
            this.updateUser();
        },
        updateUser() {
            const newStatus = this.userStatusTmp ? 'active' : 'deactive';
            console.log('newState', newStatus);
            this.$store
                .dispatch(USER_UPDATE, { ...this.user, status: newStatus })
                .then((data) => {
                    this.$emit("storeSuccess");
                    this.$message.success(data.message);
                    this.$bvModal.hide("modal-user-edit");
                })
                .catch((e) => {
                    this.$message.error(getApiMessage(e));
                    const errors = getApiValidationErrors(e);
                    if (errors) {
                        this.$refs.form.setErrors(errors);
                    }
                });
        },
        isQuanTriVienFn(id) {
            const { roles } = this;
            return isRole(roles, id);
        },
        changeRole(event) {
            this.isQuanTriVien = this.isQuanTriVienFn(event);
        },
    },
};
</script>

<style scoped></style>
