<template>
    <div>
        <b-modal
            id="modal-order-edit"
            title="Sửa đơn hàng"
            size="xl"
            hide-footer
        >
            <ValidationObserver v-slot="{ handleSubmit }" ref="form">
                <form class="form" @submit.prevent="handleSubmit(handleOk)">
                    <div class="row">
                        <div class="col-md-12">
                            <h5 class="text-primary">Thông tin đơn hàng</h5>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Họ và tên</label>
                                <ValidationProvider
                                    vid="name"
                                    name="Tên khách hàng"
                                    rules="required"
                                    v-slot="{ errors, classes }"
                                >
                                    <el-input
                                        filterable
                                        class="w-100"
                                        placeholder="Tên khách hàng"
                                        v-model="order.customer.name"
                                        clearable
                                        :class="classes"
                                    />
                                    <div class="fv-plugins-message-container">
                                        <div
                                            data-field="name"
                                            data-validator="notEmpty"
                                            class="fv-help-block"
                                        >
                                            {{ errors[0] }}
                                        </div>
                                    </div>
                                </ValidationProvider>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Email</label>
                                <ValidationProvider
                                    vid="email"
                                    name="Email khách hàng"
                                    rules="required|email"
                                    v-slot="{ errors, classes }"
                                >
                                    <el-input
                                        clearable
                                        placeholder="Email khách hàng"
                                        v-model="order.customer.email"
                                        :class="classes"
                                    ></el-input>
                                    <div class="fv-plugins-message-container">
                                        <div
                                            data-field="email"
                                            data-validator="notEmpty"
                                            class="fv-help-block"
                                        >
                                            {{ errors[0] }}
                                        </div>
                                    </div>
                                </ValidationProvider>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>SĐT</label>
                                <ValidationProvider
                                    vid="phone"
                                    name="Số điện thoại khách hàng"
                                    rules="required|numeric"
                                    v-slot="{ errors, classes }"
                                >
                                    <el-input
                                        clearable
                                        placeholder="SĐT khách hàng"
                                        v-model="order.customer.phone"
                                        :class="classes"
                                    ></el-input>
                                    <div class="fv-plugins-message-container">
                                        <div
                                            data-field="phone"
                                            data-validator="notEmpty"
                                            class="fv-help-block"
                                        >
                                            {{ errors[0] }}
                                        </div>
                                    </div>
                                </ValidationProvider>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Địa chỉ</label>
                                <ValidationProvider
                                    vid="address"
                                    name="Địa chỉ"
                                    rules=""
                                    v-slot="{ errors, classes }"
                                >
                                    <el-input
                                        type="textarea"
                                        :autosize="{ minRows: 2, maxRows: 4 }"
                                        clearable
                                        placeholder="Địa chỉ khách hàng"
                                        v-model="order.customer.address"
                                        :class="classes"
                                    ></el-input>
                                    <div class="fv-plugins-message-container">
                                        <div
                                            data-field="address"
                                            data-validator="notEmpty"
                                            class="fv-help-block"
                                        >
                                            {{ errors[0] }}
                                        </div>
                                    </div>
                                </ValidationProvider>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Số CMTND/CCCD</label>
                                <ValidationProvider
                                    vid="cccd"
                                    name="Số CMTND/CCCD"
                                    rules="required|numeric"
                                    v-slot="{ errors, classes }"
                                >
                                    <el-input
                                        clearable
                                        placeholder="Số CMTND/CCCD"
                                        v-model="order.customer.id_card"
                                        :class="classes"
                                    ></el-input>
                                    <div class="fv-plugins-message-container">
                                        <div
                                            data-field="cccd"
                                            data-validator="notEmpty"
                                            class="fv-help-block"
                                        >
                                            {{ errors[0] }}
                                        </div>
                                    </div>
                                </ValidationProvider>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Cửa hàng</label>
                                <ValidationProvider
                                    vid="store_id"
                                    name="Cừa hàng"
                                    rules="required"
                                    v-slot="{ errors, classes }"
                                >
                                    <el-select
                                        @input="handleStore"
                                        filterable
                                        class="w-100"
                                        placeholder="Cửa hàng"
                                        v-model="order.store_id"
                                        clearable
                                        :class="classes"
                                        :disabled="disable.is_store_disable"
                                    >
                                        <el-option
                                            v-for="item in stores"
                                            :key="item.id"
                                            :label="item.store_name"
                                            :value="item.id"
                                        >
                                            <span style="float: left">{{
                                                item.store_name
                                            }}</span>
                                        </el-option>
                                    </el-select>
                                    <div class="fv-plugins-message-container">
                                        <div
                                            data-field="name"
                                            data-validator="notEmpty"
                                            class="fv-help-block"
                                        >
                                            {{ errors[0] }}
                                        </div>
                                    </div>
                                </ValidationProvider>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Nhân viên phụ trách</label>
                                <ValidationProvider
                                    vid="store_id"
                                    name="Nhân viên"
                                    rules="required"
                                    v-slot="{ errors, classes }"
                                >
                                    <el-select
                                        filterable
                                        class="w-100"
                                        placeholder="Nhân viên"
                                        v-model="order.sale_id"
                                        clearable
                                        :class="classes"
                                    >
                                        <el-option
                                            v-for="item in staffs"
                                            :key="item.id"
                                            :label="item.name"
                                            :value="item.id"
                                        >
                                            <span style="float: left">{{
                                                item.name
                                            }}</span>
                                        </el-option>
                                    </el-select>
                                    <div class="fv-plugins-message-container">
                                        <div
                                            data-field="name"
                                            data-validator="notEmpty"
                                            class="fv-help-block"
                                        >
                                            {{ errors[0] }}
                                        </div>
                                    </div>
                                </ValidationProvider>
                            </div>
                        </div>
                    </div>
                    <div class="row" v-if="order.store_id">
                        <div class="col-md-12 d-flex justify-content-start">
                            <h5 class="text-primary">Danh sách sản phẩm</h5>
                            <button type="button" class="btn btn-link ml-3 p-0 text-primary" @click="addOrderItem">Thêm xe</button>
                        </div>
                        <OrderItems
                            v-for="(item, index) in order.order_items"
                            :key="index"
                            :index="index"
                            :vehicles="vehicles"
                            :order_item="item"
                            @minusItem="minusItem(index)"
                            @changePrice="calcPrice"
                        ></OrderItems>
                    </div>
                    <div class="row">
                        <div class="col-md-12 d-flex justify-content-start">
                            <h5 class="text-primary">Thanh Toán</h5>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Tổng tiền</label>
                                <money
                                    disabled=""
                                    v-model="order.price"
                                    class="form-control"
                                    v-bind="money"
                                ></money>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Lợi nhuận</label>
                                <money
                                    disabled=""
                                    v-model="order.price_profit"
                                    class="form-control"
                                    v-bind="money"
                                ></money>
                            </div>
                        </div>
                    </div>
                    <div class="card-toolbar">
                        <el-button
                            native-type="submit"
                            type="btn btn-success mr-2"
                            >Cập nhật
                        </el-button>
                    </div>
                </form>
            </ValidationObserver>
        </b-modal>
    </div>
</template>

<script>
import { STORE_GET_ALL } from "../../../core/services/store/store.module";
import {
    brands,
    status,
    types,
    typeOfServices,
    XE_BAN,
} from "../../../option/vehicle";
import { VEHICLE_GET_ALL } from "../../../core/services/store/vehicle.module";
import OrderItems from "./OrderItems";
import { ORDER_SELL_UPDATE } from "../../../core/services/store/orderSell.module";
import { mapGetters } from "vuex";
import { USER_GET_STAFF_BY_STORE } from "../../../core/services/store/user.module";

export default {
    name: "ModalOrderSellEdit",
    components: { OrderItems },
    props: {
        order_prop: {
            type: Object,
            default: () => {
                return null;
            },
        },
    },
    watch: {
        order_prop: {
            handler() {
                this.order = this.order_prop;
                this.getStaff();
                this.getVehicle();
                this.calcPrice();
            },
            deep: false,
        },
    },
    data() {
        return {
            disable: {
                is_store_disable: false,
            },
            vehicles: [],
            order: {
                store_id: "",
                price: 0,
                sale_id: "",
                price_profit: 0,
                customer: {
                    name: "",
                    phone: "",
                    address: "",
                    id_card: "",
                },
                items: [
                    {
                        vehicle_id: "",
                        desc: "",
                        price: 0,
                    },
                ],
            },
            staffs: [],
            stores: [],
            typeOfServices: typeOfServices,
            years: [],
            types: types,
            brands: brands,
            status: status,
            is_show_price_sell: false,
            money: {
                decimal: ",",
                thousands: ",",
                prefix: "",
                suffix: " VNĐ",
                precision: 0,
                masked: false,
            },
        };
    },
    computed: {
        ...mapGetters(["currentUser"]),
    },
    async mounted() {
        this.$root.$on("bv::modal::show", async (_, modalId) => {
            if (modalId === "modal-order-edit") {
                await this.getStore();
                this.handYear();
            }
        });
    },
    methods: {
        settingDefault() {
            let role_id = this.currentUser.role_id;
            if (role_id === 1) {
                return false;
            }
            this.order.store_id = this.currentUser.store_id;
            this.disable.is_store_disable = true;
            this.getStaff();
        },
        resetModal() {
            this.order = {
                store_id: "",
                price: 0,
                price_profit: 0,
                customer: {
                    name: "",
                    phone: "",
                    address: "",
                    id_card: "",
                },
                items: [
                    {
                        vehicle_id: "",
                        desc: "",
                        price: 0,
                    },
                ],
                staffs: [],
            };
        },
        addOrderItem() {
            this.order.items.push({
                vehicle_id: "",
                desc: "",
                price: 0,
            });
        },
        calcPrice() {
            this.order.price = 0;
            this.order.price_profit = 0;
            let items = this.order.order_items;
            for (let i = 0; i < items.length; i++) {
                let item = items[i];
                this.order.price += item.price;
                this.order.price_profit += item.price - item.vehicle_cost_price;
            }
        },
        minusItem(index) {
            this.order.items.splice(index, 1);
        },
        async getStore() {
            await this.$store.dispatch(STORE_GET_ALL, {}).then((data) => {
                this.stores = data.data;
            });
        },
        handYear() {
            for (let i = 2000; i <= 2023; i++) {
                this.years.push({
                    id: i,
                    name: i,
                });
            }
        },
        handleOk() {
            this.updateOrderSell();
        },
        handleStore() {
            this.vehicles = [];
            this.resetOrderItems();
            this.getVehicle();
            this.order.sale_id = "";
            this.getStaff();
        },
        getStaff() {
            this.staffs = [];
            this.$store
                .dispatch(USER_GET_STAFF_BY_STORE, {
                    store_id: this.order.store_id,
                })
                .then((data) => {
                    this.staffs = data.data;
                });
        },
        resetOrderItems() {
            this.order.items = [
                {
                    vehicle_id: "",
                    desc: "",
                    price: 0,
                },
            ];
        },
        getVehicle() {
            this.$store
                .dispatch(VEHICLE_GET_ALL, {
                    limit: 1000,
                    type_of_service_id: XE_BAN,
                    store_id: this.order.store_id,
                    is_all: true,
                })
                .then((data) => {
                    this.vehicles = data.data;
                });
        },
        updateOrderSell() {
            this.$store
                .dispatch(ORDER_SELL_UPDATE, this.order)
                .then((data) => {
                    this.$emit("storeSuccess");
                    this.$message.success(data.message);
                    this.$nextTick(() => {
                        this.$bvModal.hide("modal-order-edit");
                    });
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
    },
};
</script>

<style scoped></style>
