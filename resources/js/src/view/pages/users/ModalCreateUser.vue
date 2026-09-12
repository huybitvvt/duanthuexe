<template>
    <div>
        <button v-b-modal.modal-user-create class="btn btn-success btn-sm">
            Thêm mới
        </button>
        <b-modal id="modal-user-create" title="Thêm mới nhân viên" size="xl" centered @show="resetModal"
            @hidden="resetModal" hide-footer>
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
                                            <div data-field="name" data-validator="notEmpty" class="fv-help-block">
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
                                        <el-input placeholder="Email" v-model="user.email" :class="classes"></el-input>
                                        <div class="fv-plugins-message-container">
                                            <div data-field="email" data-validator="notEmpty" class="fv-help-block">
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
                                            <div data-field="phone" data-validator="notEmpty" class="fv-help-block">
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
                                        <el-input type="textarea" placeholder="Địa chỉ nhân sự" v-model="user.address"
                                            :class="classes"></el-input>
                                        <div class="fv-plugins-message-container">
                                            <div data-field="address" data-validator="notEmpty" class="fv-help-block">
                                                {{ errors[0] }}
                                            </div>
                                        </div>
                                    </ValidationProvider>
                                </div>
                            </div>
                            <div :class="`${isQuanTriVien ? 'col-md-12' : 'col-md-6'}`">
                                <div class="form-group">
                                    <label>Phân quyền</label>
                                    <ValidationProvider vid="role" name="Quyền" rules="required"
                                        v-slot="{ errors, classes }">
                                        <el-select filterable class="w-100" placeholder="Chọn quyền"
                                            v-model="user.role_id" clearable :class="classes"
                                            @change="changeRole($event)">
                                            <el-option v-for="item in roles" :key="item.id" :label="item.name"
                                                :value="item.id"><span style="float: left">{{
                                                    item.name
                                                    }}</span>
                                            </el-option>
                                        </el-select>
                                        <div class="fv-plugins-message-container">
                                            <div data-field="role" data-validator="notEmpty" class="fv-help-block">
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
                                            <el-option v-for="item in stores" :key="item.id" :label="item.store_name"
                                                :value="item.id">
                                                <span style="float: left">{{
                                                    item.store_name
                                                    }}</span>
                                            </el-option>
                                        </el-select>




                                        <div class="fv-plugins-message-container">
                                            <div data-field="store_id" data-validator="notEmpty" class="fv-help-block">
                                                {{ errors[0] }}
                                            </div>
                                        </div>
                                    </ValidationProvider>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Mật khẩu</label>
                                    <ValidationProvider vid="password" name="Password" rules="required"
                                        v-slot="{ errors }">
                                        <el-input placeholder="Nhập password" v-model="user.password"
                                            show-password></el-input>
                                        <div class="fv-plugins-message-container">
                                            <div data-field="address" data-validator="notEmpty" class="fv-help-block">
                                                {{ errors[0] }}
                                            </div>
                                        </div>
                                    </ValidationProvider>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Nhập lại mật khẩu</label>
                                    <ValidationProvider vid="confirm_password" name="Confirm password"
                                        rules="required|confirmed:password" v-slot="{ errors }">
                                        <el-input placeholder="Xác nhận password" v-model="user.confirm_password"
                                            show-password></el-input>
                                        <div class="fv-plugins-message-container">
                                            <div data-field="address" data-validator="notEmpty" class="fv-help-block">
                                                {{ errors[0] }}
                                            </div>
                                        </div>
                                    </ValidationProvider>
                                </div>
                            </div>
                        </div>
                        <div class="card-toolbar">
                            <el-button native-type="submit" type="btn btn-success mr-2" :loading="loading">Thêm mới
                            </el-button>
                        </div>
                    </form>
                </ValidationObserver>
            </div>
        </b-modal>
    </div>
</template>

<script>
import { STORE_GET_ALL } from "../../../core/services/store/store.module";
import { ROLE_GET_ALL } from "../../../core/services/store/role.module";
import { USER_CREATE } from "../../../core/services/store/user.module";
import { isRole } from '../../../utils';
import ErrorMessage from "../common/ErrorMessage";

export default {
    name: "ModalUserCreate",
    data() {
        return {
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
        };
    },
    mounted() {
        this.getStore();
        this.getRoles();
    },
    methods: {
        resetModal() {
            this.user = {
                name: "",
                email: "",
                password: "",
                confirm_password: "",
                role_id: "",
                phone: "",
                address: "",
                store_id: "",
            };
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
            this.storeUser();
        },
        storeUser() {
            this.$store
                .dispatch(USER_CREATE, this.user)
                .then(() => {
                    this.$emit("storeSuccess");
                    this.$message.success("Tạo mới thành công");
                    this.$bvModal.hide("modal-user-create");
                })
                .catch((e) => {
                    this.$message.error(e.data.message);
                    if (e.response.data.data.message_validate_form) {
                        this.$refs.form.setErrors(
                            e.response.data.data.message_validate_form,
                        );
                    }
                });
        },
        changeRole(value) {
            this.isQuanTriVien = this.isQuanTriVienFn(value);
        },
        isQuanTriVienFn(id) {
            const { roles } = this;
            return isRole(roles, id, "quan-tri-vien|sale");

        },
    },
};
</script>

<style scoped></style>
