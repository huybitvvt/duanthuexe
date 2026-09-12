<template>
    <ValidationObserver v-slot="{ handleSubmit }" ref="form">
        <form class="form" @submit.prevent="handleSubmit(changePassword)">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Mật khẩu mới</label>
                        <ValidationProvider vid="password" name="Password"
                                            rules="required|min:6"
                                            v-slot="{ errors,classes }">
                            <el-input placeholder="Nhập password" v-model="password"
                                      show-password></el-input>
                            <div class="fv-plugins-message-container">
                                <div data-field="address" data-validator="notEmpty"
                                     class="fv-help-block">{{
                                        errors[0]
                                    }}
                                </div>
                            </div>
                        </ValidationProvider>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Nhập lại mật khẩu</label>
                        <ValidationProvider vid="confirm_password" name="Confirm password"
                                            rules="required|min:6|confirmed:password"
                                            v-slot="{ errors,classes }">
                            <el-input placeholder="Xác nhận password"
                                      v-model="confirm_password"
                                      show-password></el-input>
                            <div class="fv-plugins-message-container">
                                <div data-field="address" data-validator="notEmpty"
                                     class="fv-help-block">
                                    {{ errors[0] }}
                                </div>
                            </div>
                        </ValidationProvider>
                    </div>
                </div>
            </div>
            <div class="card-toolbar">
                <el-button native-type="submit" type="btn btn-success mr-2 text-right">
                    Đổi mật khẩu
                </el-button>
            </div>
        </form>
    </ValidationObserver>
</template>

<script>
import {USER_CHANGE_MY_PASSWORD} from "../../../../core/services/store/user.module";

export default {
    name: "UserChangeMyPassword",
    props: {
        user: {
            type: Object,
            default: () => {
                return null;
            }
        }
    },
    data() {
        return {
            password: '',
            confirm_password: '',
        }
    },
    methods: {
        changePassword() {
            this.$store.dispatch(USER_CHANGE_MY_PASSWORD, {
                user_id: this.user.id,
                password: this.password,
            }).then((data) => {
                this.$message.success(data.message);
                this.$bvModal.hide('modal-user-edit')
				this.$emit("updatePasswordSuccess");
            }).catch((err) => {
                this.$message.error(err.data.message);
				this.$emit("updatePasswordFail");
            })
        }
    }
}
</script>

<style scoped>

</style>
