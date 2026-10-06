<template>
    <ValidationObserver v-slot="{ handleSubmit }" ref="form">
        <div class="card-toolbar mb-4">
            <router-link
                class="font-weight-bold font-size-3  btn btn-secondary"
                :to="{ name: 'customers' }"
            >Quay lại
            </router-link>
        </div>
        <div class="card card-custom gutter-b">
            <div class="card-header">
                <div class="card-title">
                    <h3 class="card-label">Cập nhật khách hàng</h3>
                </div>
            </div>
            <div class="card-body">
                <form class="form" @submit.prevent="handleSubmit(onSubmit)">
                    <div class="row">
                        <div class="col-md-12 mb-4">
                            <h5 class="text-primary">Cập nhật khách hàng</h5>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Họ và tên</label>
                                <ValidationProvider vid="name" name="Tên khách hàng"
                                                    rules="required"
                                                    v-slot="{ errors,classes }">
                                    <el-input filterable class="w-100" placeholder="Tên khách hàng"
                                              v-model="customer.name"
                                              clearable
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
                                        clearable
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
                                        clearable
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
                                        clearable
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
                                        clearable
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
                                <label>Cơ sở</label>
                                <el-select
                                    v-model="customer.store_id"
                                    class="w-100"
                                    filterable
                                    clearable
                                    placeholder="Chọn cơ sở"
                                    @change="onStoreChange"
                                >
                                    <el-option
                                        v-for="store in stores"
                                        :key="store.id"
                                        :label="store.store_name"
                                        :value="store.id"
                                    ></el-option>
                                </el-select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Sale phụ trách</label>
                                <el-select
                                    v-model="customer.sale_user_id"
                                    class="w-100"
                                    filterable
                                    clearable
                                    :disabled="!customer.store_id"
                                    :placeholder="customer.store_id ? 'Chọn sale phụ trách' : 'Chọn cơ sở trước'"
                                    :loading="salesLoading"
                                >
                                    <el-option
                                        v-for="sale in sales"
                                        :key="sale.id"
                                        :label="sale.name"
                                        :value="sale.id"
                                    ></el-option>
                                </el-select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Cảnh báo</label>
                                <el-select v-model="customer.warning" class="w-100" filterable clearable placeholder="Chọn cảnh báo">
                                    <el-option v-for="option in warningChoices" :key="option" :label="option" :value="option"></el-option>
                                </el-select>
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
import {CUSTOMER_SHOW, CUSTOMER_UPDATE} from "@/core/services/store/customers.module";
import {STORE_GET_ALL} from "@/core/services/store/store.module";
import {USER_GET_ALL} from "@/core/services/store/user.module";
import { getApiMessage, getApiValidationErrors } from "@/utils/apiErrorHandler";
import { normalizePaginator } from "@/utils/paginatorAdapter";

export default {
    name: "CustomerUpdate",
    data() {
        return {
            customer: {
                name: "",
                email: "",
                phone: "",
                id_card: "",
                address: ""
            },
            stores: [],
            sales: [],
            salesLoading: false,
            loading: false
        }
    },
    computed: {
        warningChoices() {
            const options = ["Khách mới", "Khách cũ", "Blacklist"];
            const current = String(this.customer.warning || "").trim();
            if (current && !options.includes(current)) options.push(current);
            return options;
        },
    },
    created() {
        this.loadStores();
        this.showCustomer();
    },
    watch: {
        '$route.params.id'(id) {
            if (id) this.showCustomer();
        },
    },
    mounted() {
        this.$store.dispatch(SET_BREADCRUMB, [{
            title: "Quản lý khách hàng",
            route: 'customers'
        }, {title: "Cập nhật khách hàng"}]);
    },
    methods: {
        loadStores() {
            this.$store.dispatch(STORE_GET_ALL, {}).then((data) => {
                this.stores = data?.data || [];
            }).catch(() => {
                this.stores = [];
            });
        },
        onStoreChange() {
            this.customer.sale_user_id = null;
            this.loadSales();
        },
        loadSales() {
            if (!this.customer.store_id) {
                this.sales = [];
                return;
            }
            this.salesLoading = true;
            this.$store.dispatch(USER_GET_ALL, { store_id: this.customer.store_id, limit: 200 }).then((data) => {
                const users = normalizePaginator(data).items || [];
                const sales = users.filter(user => this.isSaleUser(user));
                this.sales = sales.length ? sales : users;
                if (this.customer.sale_user_id && !this.sales.some(user => user.id === this.customer.sale_user_id)) {
                    this.sales.unshift({ id: this.customer.sale_user_id, name: "Sale đã gán" });
                }
            }).catch(() => {
                this.sales = [];
            }).finally(() => {
                this.salesLoading = false;
            });
        },
        isSaleUser(user) {
            const role = user.role_rel || {};
            const text = `${role.slug || ""} ${role.name || ""} ${user.role || ""}`.toLowerCase();
            return /sale|telesale|nhan-vien|nhân viên|nhan vien/.test(text);
        },
        onSubmit: function () {
            let payload = {
                id: this.$route.params.id,
                params: { ...this.customer, id: this.$route.params.id }
            }
            this.loading = true;
            this.$store.dispatch(CUSTOMER_UPDATE, payload).then((res) => {
                this.$router.push({name: "customers"}).then(() => {
                    this.noticeMessage('success', 'Thành công', res.message);
                })
            }).catch((e) => {
                const errors = getApiValidationErrors(e);
                if (errors) {
                    this.$refs.form.setErrors(errors);
                } else {
                    this.noticeMessage('error', 'Thất bại', getApiMessage(e));
                }
            }).finally(() => this.loading = false);
        },
        showCustomer() {
            let id = this.$route.params.id;
            this.$store.dispatch(CUSTOMER_SHOW, id).then((res) => {
                this.customer = {
                    ...res.data,
                    store_id: res.data.store_id || null,
                    sale_user_id: res.data.sale_user_id || null,
                };
                this.loadSales();
            }).catch((e) => {
                this.noticeMessage('error', 'Thất bại', e.data?.message);
            });
        }
    }

}
</script>

<style scoped>

</style>
