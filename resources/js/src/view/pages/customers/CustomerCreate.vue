<template>
    <ValidationObserver v-slot="{ handleSubmit }" ref="form">
        <div class="card-toolbar mb-4">
            <router-link
                class="font-weight-bold font-size-3  btn btn-secondary"
                :to="{ name: 'customers' }"
            ><i class="fas fa-angle-double-left"></i> Quay lại
            </router-link>
        </div>
        <div class="card card-custom gutter-b">
            <div class="card-header">
                <div class="card-title">
                    <h3 class="card-label">Thêm mới khách hàng</h3>
                </div>
            </div>
            <div class="card-body">
                <form class="form" @submit.prevent="handleSubmit(onSubmit)">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Họ và tên</label>
                                <ValidationProvider vid="name" name="Tên khách hàng"
                                                    rules="required"
                                                    v-slot="{ errors,classes }">
                                    <el-input class="w-100" placeholder="Tên khách hàng"
                                              v-model="customer.name"
                                              :class="classes"
                                    />
                                    <div class="fv-plugins-message-container">
                                        <div data-field="name" data-validator="notEmpty" class="fv-help-block">{{
                                                errors[0]
                                            }}
                                        </div>
                                    </div>
                                </ValidationProvider>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Email</label>
                                <ValidationProvider vid="email" name="Email khách hàng"
                                                    rules="required|email"
                                                    v-slot="{ errors,classes }">
                                    <el-input
                                        placeholder="Email khách hàng"
                                        v-model="customer.email"
                                        :class="classes"
                                    ></el-input>
                                    <div class="fv-plugins-message-container">
                                        <div data-field="email" data-validator="notEmpty" class="fv-help-block">{{
                                                errors[0]
                                            }}
                                        </div>
                                    </div>
                                </ValidationProvider>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>SĐT</label>
                                <ValidationProvider vid="phone" name="Số điện thoại khách hàng"
                                                    rules="required|numeric"
                                                    v-slot="{ errors,classes }">
                                    <el-input
                                        placeholder="SĐT khách hàng"
                                        v-model="customer.phone"
                                        :class="classes"
                                    ></el-input>
                                    <div class="fv-plugins-message-container">
                                        <div data-field="phone" data-validator="notEmpty" class="fv-help-block">{{
                                                errors[0]
                                            }}
                                        </div>
                                    </div>
                                </ValidationProvider>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Địa chỉ</label>
                                <ValidationProvider vid="address" name="Địa chỉ"
                                                    rules=""
                                                    v-slot="{ errors,classes }">
                                    <el-input
                                        placeholder="Địa chỉ khách hàng"
                                        v-model="customer.address"
                                        :class="classes"
                                    ></el-input>
                                    <div class="fv-plugins-message-container">
                                        <div data-field="address" data-validator="notEmpty" class="fv-help-block">{{
                                                errors[0]
                                            }}
                                        </div>
                                    </div>
                                </ValidationProvider>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Số CMTND/CCCD</label>
                                <ValidationProvider vid="cccd" name="Số CMTND/CCCD"
                                                    rules="required|numeric"
                                                    v-slot="{ errors,classes }">
                                    <el-input
                                        placeholder="Số CMTND/CCCD"
                                        v-model="customer.id_card"
                                        :class="classes"
                                    ></el-input>
                                    <div class="fv-plugins-message-container">
                                        <div data-field="cccd" data-validator="notEmpty" class="fv-help-block">{{
                                                errors[0]
                                            }}
                                        </div>
                                    </div>
                                </ValidationProvider>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Cảnh báo</label>
                                <ValidationProvider vid="" name=""
                                                    rules=""
                                                    v-slot="{ errors,classes }">
                                    <el-input
                                        placeholder="Cảnh báo"
                                        v-model="customer.warning"
                                        :class="classes"
                                    ></el-input>
                                    <div class="fv-plugins-message-container">
                                        <div data-field="cccd" data-validator="notEmpty" class="fv-help-block">{{
                                                errors[0]
                                            }}
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
            </div>
        </div>
    </ValidationObserver>
</template>

<script>

import {SET_BREADCRUMB} from "@/core/services/store/breadcrumbs.module";
import {CUSTOMER_CREATE} from "@/core/services/store/customers.module";

export default {
    name: "CustomerCreate",
    data() {
        return {
            customer: {
                name: "",
                email: "",
                phone: "",
                id_card: "",
                address: "",
                warning: "",
            },
            loading: false
        }
    },
    mounted() {
        this.$store.dispatch(SET_BREADCRUMB, [{
            title: "Quản lý khách hàng",
            route: 'customers'
        }, {title: "Thêm mới khách hàng"}]);
    },
    methods: {
        onSubmit: function () {
            this.loading = true;
            this.$store.dispatch(CUSTOMER_CREATE, this.customer).then((res) => {
                this.$router.push({name: "customers"}).then(() => {
                    this.noticeMessage('success', 'Thành công', res.message);
                })
            }).catch((e) => {
                if (e.data.data.message_validate_form) {
                    if (e.data.data?.message_validate_form['avatar'] || e.data.data?.message_validate_form['background_avatar']) {
                        let message = e.data.data?.message_validate_form['avatar'] ? 'Ảnh đại diện' : 'Ảnh bìa';
                        this.$message.error(`${message} quá dung lượng vui lòng thử lại.`);
                    }
                    this.$refs.form.setErrors(e.data.data.message_validate_form);
                } else {
                    this.noticeMessage('error', 'Thất bại', e.data?.message);
                }
            }).finally(() => this.loading = false);
        },
    }

}
</script>

<style scoped>

</style>
