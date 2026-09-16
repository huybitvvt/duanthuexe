<template>
    <div class="order-items col-md-12">
        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    <label>Chọn xe <button type="button" class="btn btn-xs btn-outline-danger font-weight-bold ml-1 py-0 px-1"
                                      title="Xóa sản phẩm" @click="minusItem"
                    >Xóa</button></label>
                    <ValidationProvider
                        vid="store_id"
                        name="Xe"
                        rules="required"
                        v-slot="{ errors, classes }"
                    >
                        <el-select
                            filterable
                            class="w-100"
                            placeholder="Chọn xe"
                            v-model="order_item.vehicle_id"
                            clearable
                            :class="classes"
                            @change="changeVehicle"

                        >
                            <el-option
                                v-for="item in vehicles"
                                :key="item.id"
                                :label="`${item.name}(${item.license})`"
                                :value="item.id"
                                :disabled="
                                    item.status === 'sold' &&
                                    item.id !== order_item.vehicle_id
                                "
                            >
                                <span style="float: left">{{ item.name }}<span class="text-danger ml-4"
                                                                               style="float: right;  font-size: 10px"
                                                                               v-if="item.status === 'sold'">(Đã bán)</span></span>
                                <span class="text-primary" style="float: right;font-size: 10px">{{
                                        item.license
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
            <div class="col-md-3">
                <div class="form-group">
                    <label>Giá mua vào</label>
                    <money
                        disabled=""
                        v-model="order_item.vehicle_cost_price"
                        class="form-control"
                        v-bind="money"
                    ></money>
                </div>
            </div>

            <div class="col-md-3" v-if="order_item.vehicle_id">
                <div class="form-group">
                    <label>Giá bán</label>
                    <ValidationProvider
                        vid="price"
                        name="Giá bán"
                        :rules="{ min_value : 1}"
                        v-slot="{ errors, classes }"
                    >
                        <money
                            @input="changePrice"
                            v-model="order_item.price"
                            class="form-control"
                            :class="classes"
                            v-bind="money"
                        ></money>

                        <div class="fv-plugins-message-container">
                            <div
                                data-field="name"
                                data-validator="notEmpty"
                                class="fv-help-block"
                            >
                                {{ errors[0] ? 'Tiền không được để trống' : '' }}
                            </div>
                        </div>
                    </ValidationProvider>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Ghi chú</label>
                    <ValidationProvider
                        vid="price_min"
                        name="Ghi chú"
                        :rules="{ required: false }"
                        v-slot="{ errors, classes }"
                    >
                        <el-input
                            type="textarea"
                            :autosize="{ minRows: 2, maxRows: 4}"
                            placeholder="Nhập ghi chú"
                            v-model="order_item.desc">
                        </el-input>
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
    </div>
</template>

<script>
export default {
    name: "OrderItems",
    props: {
        vehicles: {
            type: Array,
            default: () => {
                return [];
            }
        },
        index: {
            type: Number,
            default: () => {
                return 0;
            }
        },
        order_item: {
            type: Object,
            default: () => {
                return {
                    vehicle_id: "",
                    desc: "",
                    price: ""
                };
            }
        }
    },
    data() {
        return {
            money: {
                decimal: ",",
                thousands: ",",
                prefix: "",
                suffix: " VNĐ",
                precision: 0,
                masked: false
            }
        }
    },
    methods: {
        minusItem() {
            this.$swal.fire({
                title: 'Bạn có chắc chắn muốn xóa?',
                showDenyButton: true,
                showCancelButton: false,
                cancelButtonText: "Không xóa",
                confirmButtonText: 'Xóa',
            }).then((result) => {
                if (result.isConfirmed) {
                    this.$emit('minusItem', this.index);
                    this.emitChangePrice();
                }
            })
        },
        changePrice() {
            this.emitChangePrice();
        },
        changeVehicle(vehicle_id) {
            this.setVehicleCostPrice(vehicle_id);
            this.order_item.price = 0;
            this.emitChangePrice();
        },
        setVehicleCostPrice(vehicle_id) {
            this.vehicles.map((value) => {
                if (value.id == vehicle_id) {
                    this.order_item.vehicle_cost_price = value.cost_price;
                }
            })
        },
        emitChangePrice() {
            setTimeout(() => {
                this.$emit('changePrice')
            }, 2);
        }
    }
}
</script>

<style scoped>

</style>
