<template>
    <ValidationObserver v-slot="{ handleSubmit }" ref="form">
        <div class="card-toolbar mb-4">
            <router-link
                class="font-weight-bold font-size-3  btn btn-secondary"
                :to="{ name: 'cash' }"
            >Quay lại
            </router-link>
        </div>
        <div class="card card-custom gutter-b">
            <div class="card-header">
                <div class="card-title">
                    <h3 class="card-label">Cập nhật tài khoản tiền mặt</h3>
                </div>
            </div>
            <div class="card-body">
                <form class="form" @submit.prevent="handleSubmit(onSubmit)">
                    <div class="row">
                        <div class="col-md-12 mb-4">
                            <h5 class="text-primary">Cập nhật tài khoản tiền mặt</h5>
                        </div>
                     
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Cửa hàng</label>
                                <ValidationProvider vid="store_id" name="Cừa hàng"
                                                    rules="required"
                                                    v-slot="{ errors,classes }">
                                    <el-select filterable class="w-100" placeholder="Cửa hàng"
                                            v-model="cash.store_id"
                                            clearable
                                            :class="classes"
                                    >
                                        <el-option
                                            v-for="item in stores"
                                            :key="item.id"
                                            :label="item.store_name"
                                            :value="item.id"

                                        >
                                            <span style="float: left">{{ item.store_name }}</span>
                                        </el-option>
                                    </el-select>
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
                                <label>Số dư ban đầu</label>
                                <money   v-model="cash.opening_balance" v-bind="money"
                                           class="form-control"></money>
                               
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Số dư hiện tại</label>
                                <money   v-model="cash.current_balance" v-bind="money"
                                           class="form-control"></money>
                               
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
import {Money} from 'v-money';
import {SET_BREADCRUMB} from "@/core/services/store/breadcrumbs.module";
import {CASH_SHOW, CASH_UPDATE} from "@/core/services/store/cash.module";
import {STORE_GET_ALL} from "../../../core/services/store/store.module";
export default {
    name: "CashUpdate",
    data() {
        return {
            cash: {
              
                store_id: "",
                opening_balance: ""
               
            },
            loading: false,
            money: {
                decimal: ',',
                thousands: ',',
                prefix: '',
                suffix: ' VNĐ',
                precision: 0,
                masked: false,
            },
            stores: [],
        }
    },
    created() {
        this.getStore();
        this.showCash();
    },
    mounted() {
        this.$store.dispatch(SET_BREADCRUMB, [{
            title: "Quản lý tài khoản tiền mặt",
            route: 'cash'
        }, {title: "Cập nhật tài khoản tiền mặt"}]);
    },
    components: {Money},
    methods: {
        onSubmit: function () {
            let payload = {
                id: this.$route.params.id,
                params: this.cash
            }
            this.loading = true;
            this.$store.dispatch(CASH_UPDATE, payload).then((res) => {
                this.$router.push({name: "cash"}).then(() => {
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
        getStore() {
            this.$store.dispatch(STORE_GET_ALL, {}).then((data) => {
                this.stores = data.data;
            });
        },
        showCash() {
            let id = this.$route.params.id;
            this.$store.dispatch(CASH_SHOW, id).then((res) => {
                this.cash = res.data;
            }).catch((e) => {
                this.noticeMessage('error', 'Thất bại', e.data?.message);
            });
        }
    }

}
</script>

<style scoped>

</style>
