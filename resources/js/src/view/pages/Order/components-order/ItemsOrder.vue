<template>
    <div class="list-vehicles">
        <div class="d-flex justify-content-between">
            <div class="mb-7">
                <span style="font-size: 14px" class="font-weight-bold">Thông tin xe thuê số {{ index + 1 }}</span>                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 ml-2" @click="deleteVehicle(index)" title="Xóa xe">
                    [Xóa xe]
                </button>
            </div>
            <el-switch v-model="local_order_item.is_all_in_one" @change="changeIsAllInOne" active-text="Thuê tháng"
                inactive-text="Thuê theo ngày">
            </el-switch>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label><strong>Chọn xe</strong> <span class="text-danger">(*)</span></label>
                    <ValidationProvider vid="vehicle_id" name="Xe thuê" rules="required" v-slot="{ errors }">
                        <el-select v-model="order_item.vehicle_id" clearable filterable class="w-100"
                            placeholder="Chọn xe thuê" @change="changeVehicleId">
                            <el-option v-for="item in vehicles" :key="item.id"
                                :label="`${item.name}(${item.license})`" :value="item.id">
                                <span style="float: left">{{ item.name }}</span>
                                <span style="
                                        float: right;
                                        color: #8492a6;
                                        font-size: 13px;
                                    ">{{ item.license }}</span>
                            </el-option>
                        </el-select>
                        <error-message :errors="errors" field="vehicle_id"></error-message>
                    </ValidationProvider>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="form-group">
					<label v-if="is_deposit_contract_mode"><strong>Ngày đặt cọc</strong><span class="text-danger">(*)</span></label>
                    <label v-else><strong>Thuê lúc</strong><span class="text-danger">(*)</span></label>
                    <ValidationProvider ref="rentAtProvider" vid="rent_at" name="Thời gian thuê" rules="required" v-slot="{ errors }">
                        <el-date-picker class="w-100" @change="changeRentAt" v-model="local_order_item.rent_at"
                            type="datetime" format="dd-MM-yyyy HH:mm:ss" placeholder="Chọn thời gian">
                        </el-date-picker>
                        <error-message :errors="errors" field="rent_at"></error-message>
                    </ValidationProvider>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label v-if="is_deposit_contract_mode"><strong>Ngày hẹn lấy xe</strong> <span class="text-danger">(*)</span></label>
                    <label v-else><strong>Hẹn trả</strong> <span class="text-danger">(*)</span></label>
                    <ValidationProvider ref="returnAtProvider" vid="return_at" name="Thời gian hẹn trả" rules="required" v-slot="{ errors }">
                        <el-date-picker class="w-100" @change="changeReturnAt" v-model="local_order_item.return_at"
                            format="dd-MM-yyyy HH:mm:ss" type="datetime" placeholder="Chọn thời gian">
                        </el-date-picker>
                        <error-message :errors="errors" field="return_at"></error-message>
                    </ValidationProvider>
                </div>
            </div>

            <!-- Thông số xe tự động lấy từ danh mục để đối chiếu mẫu hợp đồng -->
            <div v-if="selectedVehicleDetails" class="col-12 mb-3">
                <div class="p-2 px-3 rounded bg-light d-flex flex-wrap align-items-center text-muted" style="font-size: 12px; border: 1px dashed #c0c4cc;">
                    <span class="mr-4"><strong>[Nhãn hiệu]</strong> {{ selectedVehicleDetails.brand || '—' }}</span>
                    <span class="mr-4"><strong>[Loại xe]</strong> {{ selectedVehicleDetails.type || '—' }}</span>
                    <span class="mr-4"><strong>[Màu sắc]</strong> {{ selectedVehicleDetails.color || '—' }}</span>
                    <span><strong>[Năm SX]</strong> {{ selectedVehicleDetails.year || '—' }}</span>
                </div>
            </div>

            <!-- Thông tin người lái xe theo mẫu hợp đồng -->
            <div class="col-md-4" v-if="!is_deposit_contract_mode">
                <div class="form-group">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="mb-0"><strong>Tên người lái</strong></label>
                        <a href="javascript:void(0)" class="text-primary font-size-xs" @click="copyCustomerAsDriver">Khách là người lái</a>
                    </div>
                    <el-input placeholder="Họ và tên người lái xe" v-model="local_order_item.driver_name" @change="changeDriverName"></el-input>
                </div>
            </div>
            <div class="col-md-4" v-if="!is_deposit_contract_mode">
                <div class="form-group">
                    <label><strong>Số GP lái xe</strong></label>
                    <el-input placeholder="Số GPLX" v-model="local_order_item.driver_license_number" @input="changeDriverLicenseNumber"></el-input>
                </div>
            </div>
            <div class="col-md-4" v-if="!is_deposit_contract_mode">
                <div class="form-group">
                    <label><strong>Ngày cấp GPLX</strong></label>
                    <el-date-picker class="w-100" v-model="local_order_item.driver_license_issued_on" format="dd-MM-yyyy" value-format="yyyy-MM-dd" type="date" placeholder="Ngày cấp GPLX" @input="changeDriverLicenseIssuedOn" @change="changeDriverLicenseIssuedOn"></el-date-picker>
                </div>
            </div>

			<div class="col-md-2" v-if="!is_deposit_contract_mode">
                <div class="form-group">
                    <label><strong>Số mũ mượn</strong></label>
                    <ValidationProvider vid="borrow_hats" name="Số mũ mượn" rules="numeric" v-slot="{ errors }">
                        <el-input placeholder="Số mũ" @change="changeBorrowHats"
                            v-model="local_order_item.borrow_hats"></el-input>
                        <error-message :errors="errors" field="borrow_hats"></error-message>
                    </ValidationProvider>
                </div>
            </div>
			<div class="col-md-2" v-if="!is_deposit_contract_mode">
                <div class="form-group">
                    <label><strong>Số áo mưa</strong></label>
                    <ValidationProvider vid="borrow_raincoats" name="Số áo mưa" rules="numeric" v-slot="{ errors }">
                        <el-input placeholder="Số áo mưa" @change="changeBorrowRaincoats"
                            v-model="local_order_item.borrow_raincoats"></el-input>
                        <error-message :errors="errors" field="borrow_raincoats"></error-message>
                    </ValidationProvider>
                </div>
            </div>
			<div class="col-md-4" v-if="!is_deposit_contract_mode">
                <div class="form-group">
					<label for="account">
						<strong>Phí thuê xe</strong>
						<span>(mặc định<span v-if="local_order_item.default_unit_price > 0">: <strong>{{ local_order_item.default_unit_price | formatPrice }}</strong></span> <span v-if="local_order_item.rental_days > 0">x <strong>{{ local_order_item.rental_days }}</strong> ngày</span>)</span>
						<button type="button" class="btn btn-sm btn-link py-0 px-1 font-weight-bold" @click="editingCustomHiringFee">[Sửa giá]</button>
					</label>
					<money v-if="editing_custom_hiring_fee" id="account" v-model="custom_hiring_fee" v-bind="money" class="form-control"></money>
					<!-- <money v-else id="account" :value="(order_id && local_order_item.hiring_fee) ? local_order_item.hiring_fee : local_order_item.hiringFee" v-bind="money" class="form-control" disabled></money> -->
					<money v-else id="account" :value="local_order_item.hiringFee" v-bind="money" class="form-control" disabled></money>
                </div>
            </div>
            

            <div v-if="local_order_item.money_out_date !== 0" class="col-md-4">
                <div class="form-group">
                    <label v-if="local_order_item.money_out_date > 0" for="money_out_date"><strong>Phí quá hạn</strong></label>
                    <label v-if="local_order_item.money_out_date < 0" for="money_out_date"><strong>Số tiền hoàn cho khách do trả sớm</strong>
						<el-tooltip content="Giá này do app tính toán theo thời gian hoàn thành hợp đồng.">
							<span>[?]</span>
						</el-tooltip>
					</label>
					
					<money id="money_out_date" v-model="calcItemMoneyOutdate" v-bind="money" class="form-control"></money>
                </div>
            </div>

            <div class="col-md-4" v-if="!is_deposit_contract_mode">
                <div class="form-group">
                    <label for="handler_price">
                        <el-tooltip content="Là giá chốt cuối cùng(tổng tiền cuối cùng mà khách phải trả theo thỏa thuận cho hợp đồng này, không tính thêm bất kì khoản phí nào kể cả phí quá giờ), quá hạn cũng không tính thêm tiền.">
							<span><strong>Giá Tổng Khác</strong> [?]</span></el-tooltip>
                    </label>

                    <money id="handler_price" v-model="handlerPriceInput" v-bind="money" class="form-control"></money>
                </div>
            </div>

			<div class="col-md-4" v-if="!is_deposit_contract_mode">
                <div class="form-group">
                    <label><strong>Số km khi khách nhận xe</strong></label>
                    <ValidationProvider vid="odometer_before" name="Số km khi khách nhận xe" rules="numeric" v-slot="{ errors }">
                        <el-input id="odometer_before" placeholder="Số km khi khách nhận xe" @change="odometerBeforeChanged" v-model="odometer_before_input"></el-input>
                        <error-message :errors="errors" field="odometer_before"></error-message>
                    </ValidationProvider>
                </div>
            </div>

			<div class="col-md-4" v-if="!is_deposit_contract_mode && order_id != 0 && order_status === 'completed'">
                <div class="form-group">
                    <label><strong>Số km khi khách trả xe</strong></label>
                    <ValidationProvider vid="odometer_after" name="Số km khi khách trả xe" rules="numeric" v-slot="{ errors }">
                        <el-input id="odometer_after" placeholder="Số km khi khách trả xe" @change="odometerAfterChanged" v-model="local_order_item.odometer_after"></el-input>
                        <error-message :errors="errors" field="odometer_after"></error-message>
                    </ValidationProvider>
                </div>
            </div>

			<div class="col-md-4" v-if="local_order_item.total_renewal_amount > 0">
                <div class="form-group">
                    <label for="handler_price">
						<strong>Tổng tiền gia hạn</strong>
                    </label>
                    <money id="handler_price" :value="local_order_item.total_renewal_amount" v-bind="money" class="form-control" disabled></money>
                </div>
            </div>

        </div>

        <div v-if="order_id != 0">
            <el-collapse accordion style="display: none" data-note="Temporary disabled by TuyenDev">
                <el-collapse-item name="1">
                    <template slot="title">
                        <b>Xem chi phí khác </b>
                    </template>
                    <el-tabs type="card">
                        <div>
                            <div class="form-group" v-if="this.local_order_item.order_item_fees.length > 0">
                                <div class="mt-2">
                                    <div class="mb-2">
                                        <el-switch v-model="isOtherFeeByCash" active-text="Tiền mặt"
                                            inactive-text="Chuyển khoản">
                                        </el-switch>
                                    </div>
                                    <div class="form-group" :class="{
                                        'd-none': isRequiredBank !== 'required',
                                    }">
                                        <ValidationProvider vid="bank_id" name="Tài khoản" :rules="isRequiredBank"
                                            v-slot="{ errors, classes }">
                                            <el-select filterable class="w-100" placeholder="Tài khoản"
                                                v-model="otherFeeBankIdLocalInput" clearable :class="classes">
                                                <el-option v-for="item in banks" :key="item.id" :label="item.bank_name +
                                                    ' - ' +
                                                    item.owner_name +
                                                    ' - ' +
                                                    item.account_number
                                                    " :value="item.id ? item.id : ''">
                                                    <span style="float: left">{{ item.bank_name }} -
                                                        {{ item.owner_name }} -
                                                        {{ item.account_number }}</span>
                                                </el-option>
                                            </el-select>
                                            <error-message :errors="errors" field="bank_id"></error-message>
                                        </ValidationProvider>
                                    </div>
                                </div>
                            </div>

                            <div v-for="(fee, key) in order_item.order_item_fees" :key="key">
                                <fee :ref="'itemOrderFee-' + key" :index="key" :fee="fee" :order_item_id="order_item.id"
                                    :order_status="order_status" :order_id="order_item.order_id" @feeChanged="feeChanged" @deleteFee="deleteFee">
                                </fee>
                            </div>

                            <div :style="{
                                'pointer-events': order_status === 'completed' ? 'none' : 'auto'
                            }" @click="addFee()">
                                <button type="button" class="btn btn-sm btn-outline-success">
                                    Thêm chi phí khác cho xe {{ index + 1 }}
                                </button>
                    </el-tabs>
                </el-collapse-item>
            </el-collapse>
        </div>
        <el-divider></el-divider>
    </div>
</template>

<script>
// import { ElTooltip } from 'element-plus';
import ErrorMessage from "../../common/ErrorMessage";
import { Money } from "v-money";
import Fee from "./Fee";
import Swal from "sweetalert2";
import moment from "moment";

export default {
    name: "ItemsOrder",
    props: {
        banks: {
            type: Array,
            default: () => {
                return [];
            },
        },
        vehicles: {
            type: Array,
            default: () => {
                return [];
            },
        },
        priceVehicles: {
            type: Array,
            default: () => {
                return [];
            },
        },

        index: {
            type: Number,
            default: () => {
                return 0;
            },
        },
        order_status: {
            type: String,
            default: () => {
                return '';
            },
        },
        order_id: {
            type: Number,
            default: () => {
                return 0;
            },
        },

        order_item: {
            type: [Object],
            default: () => {
                return {};
            },
        },
        other_fee_bank_id: {
            type: Number,
            default: () => {
                return 0;
            },
        },
        other_fee_payment_method: {
            type: Number,
            default: () => {
                return 1;
            },
        },
		is_deposit_contract_mode: {
			type: Boolean,
			default: () => {
				return false;
			}
		},
		customer_name: {
			type: String,
			default: '',
		}
    },
    components: {
        ErrorMessage,
        Money,
        Fee,
    },
    data() {
        return {

            money: {
                decimal: ",",
                thousands: ".",
                prefix: "",
                suffix: " VNĐ",
                precision: 0,
                masked: false,
            },
			custom_hiring_fee: 0,
			editing_custom_hiring_fee: false,
        };
    },
    computed: {
        selectedVehicleDetails() {
            if (!this.order_item || !this.order_item.vehicle_id || !this.vehicles) return null;
            return this.vehicles.find(v => v.id == this.order_item.vehicle_id) || null;
        },
		isNomalOrderCompleted() {
			if (!this.is_deposit_contract_mode && this.order_id != 0 && this.order_status === 'completed') {
				return true;
			}
			return false;
		},
        isOtherFeeByCash: {
            get() {
                return this.other_fee_payment_method == 1;
            },
            set(val) {

                this.$emit(
                    "other_fee_payment_method",
                    val == true ? 1 : 2,
                );
            },
        },
        isRequiredBank() {
            return this.isOtherFeeByCash ? "" : "required";
        },
        otherFeeBankIdLocalInput: {
            get() {
                return this.other_fee_bank_id;
            },
            set(value) {

                this.$emit(
                    "other_fee_bank_id",
                    value,
                );
            },
        },

		calcItemMoneyOutdate: {
			get() {
				return this.local_order_item.money_out_date;
			},
			set(data) {
				if (data !== this.local_order_item.money_out_date) {
                    this.$emit("change_money_out_date", {
                        index: this.index,
                        data,
                    });
                }
			}
		},

        substitute_unit_price_input: {
            get() {
                return this.local_order_item.substitute_unit_price;
            },
            set(data) {
                if (data !== this.local_order_item.substitute_unit_price) {
					this.editing_custom_hiring_fee = false;
					this.custom_hiring_fee = null;

                    this.$emit("change_substitute_unit_price", {
                        index: this.index,
                        data,
                    });
                }
            },
        },


        handlerPriceInput: {
            get() {
                return this.local_order_item.handler_price;
            },
            set(data) {
                if (data !== this.local_order_item.handler_price) {
					this.local_order_item.handler_price = data;
                    this.$emit("changeHandlerPrice", {
                        index: this.index,
                        data,
                    });
                }
            },
        },

        local_order_item() {
            return { ...this.order_item };
        },

		odometer_before_input: {
            get() {
				if (this.local_order_item.odometer_before) {
					return this.local_order_item.odometer_before;
				}
				if ( !this.order_id && !this.is_deposit_contract_mode ) { // Case create new constract.
					let currentVehicleID = this.order_item.vehicle_id;
					let choiceVehicle = this.vehicles.find(item => item.id == currentVehicleID);

					if (choiceVehicle && choiceVehicle.odometer) {
						this.$emit("odometerBeforeChanged", { index: this.index, data: choiceVehicle.odometer });
						return choiceVehicle.odometer;
					}
				}
				
				return null;
            },
            set(data) {
                if (data !== this.local_order_item.odometer_before) {
                    this.local_order_item.odometer_before = data;
					this.$emit("odometerBeforeChanged", { index: this.index, data });
                }
            },
        },
		// item_hiring_fee: {
		// 	get() {
		// 		console.log('item_hiring_fee case get');
		// 		this.custom_hiring_fee = this.local_order_item.hiringFee;
		// 		return this.custom_hiring_fee;
		// 	},
		// 	set(data) {
		// 		console.log('item_hiring_fee case set: ', data);
		// 		if (data !== this.local_order_item.hiringFee) {
		// 			this.local_order_item.hiringFee = data;
		// 			this.$emit("item_hiring_fee_changed", { index: this.index, data });
		// 		}
		// 	}
		// }
    },

	watch: {
		custom_hiring_fee(data) {
			this.$emit("item_hiring_fee_changed", { index: this.index, data });
		}
	},

    methods: {
		resetCustomFee() {
			if ( this.custom_hiring_fee && this.custom_hiring_fee > 0) {
				this.custom_hiring_fee = '';
				this.$emit("custom_hiring_fee_changed", { index: this.index, custom_hiring_fee: '' });
			}
		},
        deleteVehicle(index) {
            if (index === 0) {
                this.$message.warning("Đơn hàng không được trống xe thuê");
                return;
            }
            this.$swal
                .fire({
                    title: "Bạn có chắc chắn muốn xóa?",
                    showDenyButton: true,
                    showCancelButton: false,
                    cancelButtonText: "Không xóa",
                    confirmButtonText: "Xóa",
                })
                .then((result) => {
                    if (result.isConfirmed) {
                        this.$emit("deleteVehicle", index);
                    }
                });
			this.resetCustomFee();
        },

        addFee() {
            this.order_item.order_item_fees.push({ id: "" });
			this.$emit("addFee");
        },
		feeChanged() {
			this.$emit("feeChanged");
		},

        deleteFee(index) {
            Swal.fire({
                title: "Bạn chắc chắn muốn xóa?",
                showDenyButton: true,
                showCancelButton: true,
                confirmButtonText: "Đồng ý",
                cancelButtonText: "Không",
            }).then((result) => {
                if (result.isConfirmed) {
                    this.$emit("deleteFee", this.order_item.order_item_fees[index].id);
                    this.order_item.order_item_fees.splice(index, 1);
                }
            });

        },
        changeRentAt(data) {
			console.log('changeRentAt data: ', data);
            this.$emit("changeRentAt", { index: this.index, rent_at: data });

			this.resetCustomFee();
        },
        changeIsAllInOne(data) {
            this.$emit("changeIsAllInOne", { index: this.index, data });
			this.resetCustomFee();
        },
        changeBorrowHats(data) {
            this.$emit("changeBorrowHats", { index: this.index, data });
        },
		odometerBeforeChanged(data) {
			this.$emit("odometerBeforeChanged", { index: this.index, data });
		},
		odometerAfterChanged(data) {
			this.$emit("odometerAfterChanged", { index: this.index, data });
		},
        changeReturnAt(data) {
            this.$emit("changeReturnAt", { index: this.index, data });

			if (new Date(this.local_order_item.rent_at) >= new Date(this.local_order_item.return_at)) {
				console.log("Case set error");
				this.$refs.rentAtProvider.setErrors(["Rent Date must be earlier than Return Date"]);
				this.$refs.returnAtProvider.setErrors(["Return Date must be later than Rent Date"]);
			} else {
				this.$refs.rentAtProvider.setErrors([]);
				this.$refs.returnAtProvider.setErrors([]);
			}
			this.resetCustomFee();
        },
        changeVehicleId(data) {
            this.$emit("changeVehicleId", { index: this.index, data });
			this.resetCustomFee();
        },
		editingCustomHiringFee() {
			this.editing_custom_hiring_fee = !this.editing_custom_hiring_fee;
			if (this.editing_custom_hiring_fee) {
				this.custom_hiring_fee = this.local_order_item.hiringFee;
			}
			
		},
        copyCustomerAsDriver() {
            if (this.customer_name) {
                this.$set(this.local_order_item, 'driver_name', this.customer_name);
                this.$emit("changeDriverName", { index: this.index, data: this.customer_name });
            }
        },
        changeDriverName(data) {
            this.$emit("changeDriverName", { index: this.index, data });
        },
        changeDriverLicenseNumber(data) {
            this.$emit("changeDriverLicenseNumber", { index: this.index, data });
        },
        changeDriverLicenseIssuedOn(data) {
            this.$emit("changeDriverLicenseIssuedOn", { index: this.index, data });
        },
        changeBorrowRaincoats(data) {
            this.$emit("changeBorrowRaincoats", { index: this.index, data: parseInt(data) || 0 });
        }
    },

};
</script>

<style scoped>
.form-group > label, .form-group label strong { white-space: nowrap; }
</style>
