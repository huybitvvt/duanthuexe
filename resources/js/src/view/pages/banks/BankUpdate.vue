<template>
    <ValidationObserver v-slot="{ handleSubmit }" ref="form">
        <div class="card-toolbar mb-4">
            <router-link
                class="font-weight-bold font-size-3  btn btn-secondary"
                :to="{ name: 'banks' }"
            ><i class="fas fa-angle-double-left"></i> Quay lại
            </router-link>
        </div>
        <div class="card card-custom gutter-b">
            <div class="card-header">
                <div class="card-title">
                    <h3 class="card-label">Cập nhật tài khoản ngân hàng</h3>
                </div>
            </div>
            <div class="card-body">
                <form class="form" @submit.prevent="handleSubmit(onSubmit)">
                    <div class="row">
                        <div class="col-md-12 mb-4">
                            <h5 class="text-primary">Cập nhật tài khoản ngân hàng</h5>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Tên ngân hàng</label>
                                <ValidationProvider vid="bank_name" name="Tên ngân hàng"
                                                    rules="required"
                                                    v-slot="{ errors,classes }">
                                    <el-input filterable class="w-100" placeholder="Tên ngân hàng"
                                              v-model="bank.bank_name"
                                              clearable
                                              :class="classes"
                                    />
                                    <div class="fv-plugins-message-container">
                                        <div data-field="bank_name" data-validator="notEmpty" class="fv-help-block">{{
                                                errors[0]
                                            }}
                                        </div>
                                    </div>
                                </ValidationProvider>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>STK</label>
                                <ValidationProvider vid="account_number" name="STK"
                                                    rules="required"
                                                    v-slot="{ errors,classes }">
                                    <el-input
                                        clearable
                                        placeholder="STK"
                                        v-model="bank.account_number"
                                        :class="classes"
                                    ></el-input>
                                    <div class="fv-plugins-message-container">
                                        <div data-field="account_number" data-validator="notEmpty" class="fv-help-block">{{
                                                errors[0]
                                            }}
                                        </div>
                                    </div>
                                </ValidationProvider>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Người thụ hưởng</label>
                                <ValidationProvider vid="owner_name" name="Người thụ hưởng"
                                                    rules="required"
                                                    v-slot="{ errors,classes }">
                                    <el-input
                                        clearable
                                        placeholder="Người thụ hưởng"
                                        v-model="bank.owner_name"
                                        :class="classes"
                                    ></el-input>
                                    <div class="fv-plugins-message-container">
                                        <div data-field="owner_name" data-validator="notEmpty" class="fv-help-block">{{
                                                errors[0]
                                            }}
                                        </div>
                                    </div>
                                </ValidationProvider>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Cửa hàng</label>
                                <ValidationProvider vid="store_id" name="Cừa hàng"
                                                    rules="required"
                                                    v-slot="{ errors,classes }">
                                    <el-select filterable class="w-100" placeholder="Cửa hàng"
                                            v-model="bank.store_id"
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
								<label>Loại tài khoản</label>
								<ValidationProvider vid="account_type" name="Loại tài khoản" rules="required" v-slot="{ errors,classes }">
									<el-select filterable class="w-100" placeholder="Chọn loại tài khoản" v-model="bank.account_type" clearable :class="classes">
										<el-option v-for="item in bank_account_types" :key="item.type" :label="item.label" :value="item.type">
											<span style="float: left">{{ item.label }}</span>
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
                                <money   v-model="bank.opening_balance" v-bind="money"
                                           class="form-control"></money>
                               
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Số dư hiện tại</label>
                                <money   v-model="bank.current_balance" v-bind="money"
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
import {BANK_SHOW, BANK_UPDATE} from "@/core/services/store/banks.module";
import {STORE_GET_ALL} from "../../../core/services/store/store.module";
export default {
    name: "BankUpdate",
    data() {
        return {
            bank: {
                account_number: "",
                owner_name: "",
                bank_name: "",
                store_id: 0,
                opening_balance: "",
				account_type: 1,
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
			bank_account_types: [
				{
					label: 'Tài khoản thu & chi',
					type: 0,
				},
				{
					label: 'Tài khoản thu',
					type: 1,
				},
				{
					label: 'Tài khoản chi',
					type: 2,
				},
			]
        }
    },
    created() {
        this.getStore();
        this.showBank();
    },
    mounted() {
        this.$store.dispatch(SET_BREADCRUMB, [{
            title: "Quản lý tài khoản ngân hàng",
            route: 'banks'
        }, {title: "Cập nhật tài khoản ngân hàng"}]);
    },
    components: {Money},
    methods: {
        onSubmit: function () {
            let payload = {
                id: this.$route.params.id,
                params: this.bank
            }
            this.loading = true;
            this.$store.dispatch(BANK_UPDATE, payload).then((res) => {
                this.$router.push({name: "banks"}).then(() => {
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
                let getStores = data.data;
				/*
				getStores.unshift({
					id: 0,
					created_at: '',
					store_address: '',
					store_name: 'Cửa hàng tổng',
					store_phone: '',
					updated_at: '',
					user_id: null,
				}) */

				this.stores = getStores;
            });
        },
        showBank() {
            let id = this.$route.params.id;
            this.$store.dispatch(BANK_SHOW, id).then((res) => {
                this.bank = res.data;
            }).catch((e) => {
                this.noticeMessage('error', 'Thất bại', e.data?.message);
            });
        }
    }

}
</script>

<style scoped>

</style>
