<template>
    <div>
        <b-button
            class="btn btn-primary"
            @click="dialogVisible = !dialogVisible"
            >Thu thêm / Gia hạn
        </b-button>
        <b-modal
            centered
            v-model="dialogVisible"
            title="Thu thêm tiền hoặc gia hạn hợp đồng"
            hide-footer
        >
            <ValidationObserver v-slot="{ handleSubmit }" ref="form">
                <form class="form" @submit.prevent="handleSubmit(handleOk)">
                    <div class="row">
						<div class="col-md-12 form-group">
							<label><strong>Chọn xe cần gia hạn<span class="text-danger">(*)</span></strong></label>
							<ValidationProvider vid="store_id" name="Xe cần gia hạn" rules="required" v-slot="{ errors }">
                                <el-select name="store_id" v-model="order2.line_item_id" clearable filterable class="w-100" placeholder="Chọn xe cần gia hạn">
                                    <el-option v-for="item in order.order_items" :key="item.id" :label="`${item.vehicle.name}(${item.vehicle.license})`" :value="item.id">
										<span style="float: left">{{ item.vehicle.name }}</span>
										<span style=" float: right; color: #8492a6; font-size: 13px;">{{ item.vehicle.license }}</span>
									</el-option>
                                </el-select>
                                <error-message :errors="errors" field="store_id"></error-message>
                            </ValidationProvider>
						</div>
                        <div class="col-md-12 form-group">
                            <label><strong>Hẹn trả xe<span class="text-danger">(*)</span></strong></label>
                            <ValidationProvider
                                vid="return_at"
                                name="Thời gian hẹn trả"
                                rules="required"
                                v-slot="{ errors, classes }"
                            >
                                <el-date-picker
                                    class="w-100"
                                    :class="classes"
                                    v-model="return_at"
                                    format="dd-MM-yyyy HH:mm:ss"
                                    type="datetime"
                                    placeholder="Chọn thời gian"
                                >
                                </el-date-picker>
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

						<div class="col-md-12">
							<div class="form-group">
								<label for="payment-method"><strong>Hình thức thu tiền gia hạn</strong></label>
								<div>
									<el-radio-group id="payment-method" v-model="order2.addon_payment_method" size="medium">
										<el-radio-button label="1">Tiền mặt</el-radio-button>
										<el-radio-button label="2">Chuyển khoản</el-radio-button>
										<el-radio-button label="3">Tiền mặt & Chuyển khoản</el-radio-button>
									</el-radio-group>
								</div>
							</div>
							<div v-if="order2.addon_payment_method == 2">
								<div class="form-group required">
									<label><strong>Chọn tài khoản: </strong></label>
									<ValidationProvider vid="bank_id" name="Tài khoản" rules="required" v-slot="{ errors }">
										<el-select filterable class="w-100" placeholder="Tài khoản" v-model="order2.addon_bank_id" clearable>
											<el-option v-for="item in banks" :key="item.id" :label="item.bank_name +
												' - ' +
												item.owner_name +
												' - ' +
												item.account_number
												" :value="item.id">
												<span style="float: left">
													{{ showAccType(item.account_type) }}:
													{{ item.bank_name }} -
													{{ item.owner_name }} -
													{{ item.account_number }}</span>
											</el-option>
										</el-select>
										<error-message :errors="errors" field="bank_id"></error-message>
									</ValidationProvider>
								</div>
							</div>
							<div v-if="order2.addon_payment_method == 3">
								<div class="form-group row required">
									<div class="col-md-7">
										<label><strong>Chọn tài khoản: </strong></label>
										<ValidationProvider vid="bank_id" name="Tài khoản" rules="required" v-slot="{ errors }">
											<el-select filterable class="w-100" placeholder="Tài khoản" v-model="order2.addon_bank_id" clearable>
												<el-option v-for="item in banks" :key="item.id" :label="item.bank_name +
													' - ' +
													item.owner_name +
													' - ' +
													item.account_number
													" :value="item.id">
													<span style="float: left">
														{{ showAccType(item.account_type) }}:
														{{ item.bank_name }} -
														{{ item.owner_name }} -
														{{ item.account_number }}</span>
												</el-option>
											</el-select>
											<error-message :errors="errors" field="bank_id"></error-message>
										</ValidationProvider>
									</div>
									<div class="col-md-5">
										<label><strong>Tổng tiền chuyển khoản: </strong></label>
										<ValidationProvider vid="bank_transfer_amount" name="Tổng tiền chuyển khoản" rules="required|numeric|min:1" v-slot="{ errors }">
											<money v-model="order2.bank_transfer_amount" v-bind="money" class="form-control" placeholder="Tổng tiền chuyển khoản"></money>
											<error-message :errors="errors" field="bank_transfer_amount"></error-message>
										</ValidationProvider>
									</div>
								</div>
								<div class="form-group row required">
									<div class="col-md-12">
										<label><strong>Tổng tiền mặt: </strong></label>
										<ValidationProvider vid="cash_amount" name="Tổng tiền mặt" rules="required|numeric|min:1" v-slot="{ errors }">
											<money v-model="price" v-bind="money" class="form-control" placeholder="Tổng tiền mặt"></money>
											<error-message :errors="errors" field="cash_amount"></error-message>
										</ValidationProvider>
									</div>
								</div>
							</div>
						</div>

						<div v-if="[1,2].includes(parseInt(order2.addon_payment_method))" class="col-md-12 form-group">
                            <label for="addon-amount"><strong>Nhập số tiền gia hạn thêm<span class="text-danger">(*)</span></strong></label>
                            <ValidationProvider vid="price" name="Số tiền gia hạn thêm" rules="required" v-slot="{ errors }">
                                <money id="addon-amount" v-model="price" v-bind="money" class="form-control"></money>
                                <error-message :errors="errors" field="addon-amount"></error-message>
                            </ValidationProvider>
                        </div>

						<div class="col-md-12">
                            <div class="form-group">
                                <label><strong>Ngày thu tiền gia hạn<span class="text-danger">(*)</span></strong></label>
                                <ValidationProvider vid="created_at" name="Ngày thu tiền gia hạn" rules="required" v-slot="{ errors }">
                                    <el-date-picker class="w-100" v-model="created_at" format="dd-MM-yyyy HH:mm:ss" type="datetime" placeholder="Ngày thu tiền gia hạn"></el-date-picker>
									<error-message :errors="errors" field="created_at"></error-message>
                                </ValidationProvider>
                            </div>
                        </div>
						
                    </div>
                    <div class="row d-flex justify-content-end">
                        <button
                            type="button"
                            class="btn btn-primary"
                            @click="handleOk"
                        >
                            Gia hạn
                        </button>
                    </div>
                </form>
            </ValidationObserver>
        </b-modal>
    </div>
</template>

<script>
import moment from "moment";
import { ORDER_REN_CAR_ADD_ON_PRICE } from "../../../../core/services/store/order.module";
import { HOAN_THANH } from "../../../../option/orderOption";
import ErrorMessage from "../../common/ErrorMessage";

export default {
    name: "ModalAddOnPrice",
    props: {
        order: {
            type: Object,
            default: () => {
                return null;
            },
        },
        banks: {
            type: Array,
            default: () => {
                return null;
            },
        },
    },
    data() {
        return {
            order2: {
                // addon_bank_id: 1,
                addon_bank_id: null,
                addon_payment_method: 1,
				bank_transfer_amount: 0,
				line_item_id: null,
            },
            isByCash: true,
            HOAN_THANH: HOAN_THANH,
            price: "",
            return_at: "",
            dialogVisible: false,
			created_at: new Date(),
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
    watch: {
        "order2.addon_payment_method"(newValue) {
            this.isByCash = newValue === 1;
        },
        addonBankId(value) {
            this.order2.addon_bank_id = value;
        },
    },
    computed: {
        isRequiredBank() {
            return this.isByCash ? "" : "required";
        },
        addonBankId: {
            get() {
                const bank_id = this.order?.bank_id;
                if (bank_id > -1) {
                    this.order2.addon_bank_id = bank_id;
                } else {
                    this.order2.addon_bank_id = null;
                }
            },
            set(value) {
                this.order2.addon_bank_id = value;
            },
        },
    },
    mounted() {
        if (!this.order.order_items) {
            this.return_at = "";
        }
        this.return_at = this.order.order_items.length ? this.order.order_items[0].return_at : "";
		this.order2.line_item_id = this.order.order_items.length ? this.order.order_items[0].id : null;
    },
    methods: {
		showAccType(type) {
			if (1 == type) {
				return 'TK Thu';
			} else if (2 == type) {
				return 'TK Chi';
			}
			return 'TK Thu & Chi';
		},

        onChangePaymentMethod(value) {
            this.order2.addon_payment_method;
        },

        handleOk() {
            this.$store
                .dispatch(ORDER_REN_CAR_ADD_ON_PRICE, {
                    order_id: this.order.id,
                    price: this.price,
                    store_id: this.order.store_id,
                    return_at: this.return_at,
					return_at_formatted: moment(this.return_at).format('DD-MM-YYYY HH:mm:ss'),
					created_at: moment(this.created_at).format('DD-MM-YYYY HH:mm:ss'),
                    payment_method: this.order2.addon_payment_method,
                    bank_id: this.order2.addon_bank_id,
					bank_transfer_amount: this.order2.bank_transfer_amount,
					line_item_id: this.order2.line_item_id,
                })
                .then((data) => {
                    this.$emit("addOnSuccess");
                    this.$message.success(data.message);
                    this.dialogVisible = false;
                })
                .catch((e) => {
                    // this.$message.error(e.data.message);
                    if (e.data.errors) {
                        this.$refs.form.setErrors(e.data.errors);
                    }
                });
        },
    },
	components: {
        ErrorMessage,
    },
};
</script>

<style scoped></style>
