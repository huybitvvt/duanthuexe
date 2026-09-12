<template>
    <ValidationObserver v-slot="{ handleSubmit }" ref="form">
        <div class="card-toolbar mb-4">
            <router-link
                class="font-weight-bold font-size-3  btn btn-secondary"
                :to="{ name: 'stores' }"
            ><i class="fas fa-angle-double-left"></i> Quay lại
            </router-link>
        </div>
        <div class="card card-custom gutter-b">
            <div class="card-header">
                <div class="card-title">
                    <h3 class="card-label">Cập nhật cửa hàng</h3>
                </div>
            </div>
            <div class="card-body">
                <form class="form" @submit.prevent="handleSubmit(onSubmit)">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Tên cửa hàng</label>
                                <ValidationProvider vid="store_name" name="Tên cửa hàng"
                                                    rules="required"
                                                    v-slot="{ errors,classes }">
                                    <el-input class="w-100" placeholder="Tên khách hàng"
                                              v-model="store.store_name"
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

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>SĐT</label>
                                <ValidationProvider vid="store_phone" name="Số điện thoại khách hàng"
                                                    rules="required|numeric"
                                                    v-slot="{ errors,classes }">
                                    <el-input
                                        placeholder="SĐT cửa hàng"
                                        v-model="store.store_phone"
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
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Địa chỉ</label>
                                <ValidationProvider vid="store_address" name="Địa chỉ"
                                                    rules=""
                                                    v-slot="{ errors,classes }">
                                    <el-input
                                        placeholder="Địa chỉ khách hàng"
                                        v-model="store.store_address"
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
                    </div>
                    <div class="card-toolbar">
                        <el-button native-type="submit" type="btn btn-success mr-2" :loading="loading">Cập nhật
                        </el-button>
                    </div>
                </form>
            </div>
        </div>
    </ValidationObserver>
</template>

<script>

import {SET_BREADCRUMB} from "@/core/services/store/breadcrumbs.module";
import {STORE_CREATE, STORE_SHOW, STORE_UPDATE} from "../../../core/services/store/store.module";

export default {
    name: "StoreUpdate",
    data() {
        return {
            store: {
                store_name: "",
                store_phone: "",
                store_address: "",
            },
            loading: false
        }
    },
    mounted() {
        console.log('hello')
        this.getById();
        this.$store.dispatch(SET_BREADCRUMB, [{
            title: "Quản lý cửa hàng",
            route: 'stores'
        }, {title: "Cập nhật cửa hàng"}]);
    },
    methods: {
        getById() {
            let id = this.$route.params.id;
            this.$store.dispatch(STORE_SHOW, id).then((res) => {
                this.store = res.data;
            }).catch((e) => {
                if (e.data.errors) {
                    this.$refs.form.setErrors(e.data.errors);
                }
            }).finally(() => this.loading = false);
        },
        onSubmit: function () {
            this.loading = true;
            console.log(this.store)
            this.$store.dispatch(STORE_UPDATE, this.store).then((res) => {
                this.$router.push({name: "stores"}).then(() => {
                    this.noticeMessage('success', 'Thành công', res.message);
                })
            }).catch((e) => {
                if (e.data.errors) {
                    this.$refs.form.setErrors(e.data.errors);
                }
            }).finally(() => this.loading = false);
        },
    }

}
</script>

<style scoped>

</style>
