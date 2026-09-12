<template>
    <div>
        <b-button class="btn btn-primary" @click="is_dialog_visible = !is_dialog_visible" native-type="button"><i class="fas"></i>Kích hoạt hợp đồng</b-button>
        <b-modal centered v-model="is_dialog_visible" title="Kích hoạt hợp đồng cọc thành hợp đồng thuê xe" hide-footer>
            <ValidationObserver v-slot="{ handleSubmit }" ref="form">
                <form class="form form-start-contract" @submit.prevent="handleSubmit(startOrder)">
                    <div class="row">
                       
                        <div class="col-md-12 form-group">
							<div>
								<label for="debt"><strong>Số tiền khách đã cọc</strong></label>
								<money id="debt" :value="order.first_deposit_amount" v-bind="money" class="form-control" :disabled="true"></money>
							</div>

							<div class="d-flex justify-content-center mb-6">
								<h2 class="font-weight-bold">Thông tin phương tiện</h2>
							</div>

							<div v-if="order.order_items" v-for="(item, key) in order.order_items" :key="key">
								<items-order
									:index="key" :order_id="id"
									:order_item="item"
									:priceVehicles="priceVehicles"
									:banks="banks"
									:ref="'itemOrder-' + key" 
									:vehicles="vehicles"
									:order_status="order.order_status"
									
									>
								</items-order>
							</div>

                            <div class="mt-5">
								<div class="d-flex align-items-center">
									<input type="checkbox" class="checkbox-input" v-model="is_collect_additional_payment" id="collect-additional-payment">
									<label for="collect-additional-payment" class="ml-2 mb-0"><strong>Thu thêm tiền cho hợp đồng này?</strong></label>
								</div>

                                <div class="mt-5" v-if="is_collect_additional_payment">
                                    <label for="complete-transaction-via"><strong>Thu thêm cọc hợp đồng thông qua:</strong></label>
									<div id="complete-transaction-via">
										<el-radio-group v-model="payment_method" size="medium">
											<el-radio-button label="1">Tiền mặt</el-radio-button>
											<el-radio-button label="2">Chuyển khoản</el-radio-button>
											<el-radio-button label="3">Tiền mặt & Chuyển khoản</el-radio-button>
										</el-radio-group>
									</div>
									
									<div v-if="payment_method == 2">
										<div class="form-group required">
											<label><strong>Chọn tài khoản: </strong></label>
											<ValidationProvider vid="bank_id" name="Tài khoản" rules="required"
												v-slot="{ errors, classes }">
												<el-select filterable class="w-100" placeholder="Tài khoản" v-model="bank_id" clearable :class="classes">
													<el-option v-for="item in banks" :key="item.id" :label="item.bank_name +
														' - ' +
														item.owner_name +
														' - ' +
														item.account_number
														" :value="item.id">
														<span style="float: left">{{ item.bank_name }} -
															{{ item.owner_name }} -
															{{
																item.account_number
															}}</span>
													</el-option>
												</el-select>
												<error-message :errors="errors" field="bank_id"></error-message>
											</ValidationProvider>
										</div>
                                    </div>

									<div v-if="payment_method == 3">
										<div class="form-group row required">
											<div class="col-md-8">
												<label><strong>Chọn tài khoản: </strong></label>
												<ValidationProvider vid="bank_id" name="Tài khoản" rules="required"
													v-slot="{ errors, classes }">
													<el-select filterable class="w-100" placeholder="Tài khoản"
														v-model="bank_id" clearable :class="classes">
														<el-option v-for="item in banks" :key="item.id" :label="item.bank_name +
															' - ' +
															item.owner_name +
															' - ' +
															item.account_number
															" :value="item.id">
															<span style="float: left">{{ item.bank_name }} -
																{{ item.owner_name }} -
																{{
																	item.account_number
																}}</span>
														</el-option>
													</el-select>
													<error-message :errors="errors" field="bank_id"></error-message>
												</ValidationProvider>
											</div>
											<div class="col-md-4">
												<label><strong>Tổng tiền chuyển khoản: </strong></label>
												<ValidationProvider vid="bank_transfer_amount" name="Tổng tiền chuyển khoản" rules="required|numeric|min:1" v-slot="{ errors }">
													<money v-model="bank_transfer_amount" v-bind="money" class="form-control" placeholder="Tổng tiền chuyển khoản"></money>
													<error-message :errors="errors" field="bank_transfer_amount"></error-message>
												</ValidationProvider>
											</div>
										</div>
										<div class="form-group row required">
											<div class="col-md-12">
												<label><strong>Tổng tiền mặt: </strong></label>
												<ValidationProvider vid="cash_amount" name="Tổng tiền mặt" rules="required|numeric|min:1" v-slot="{ errors }">
													<money v-model="cash_amount" v-bind="money" class="form-control" placeholder="Tổng tiền mặt"></money>
													<error-message :errors="errors" field="cash_amount"></error-message>
												</ValidationProvider>
											</div>
										</div>
                                    </div>
									

                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row d-flex justify-content-end">
                        <el-button native-type="button" class="btn-hoan-thanh-order" style="color: #fff; background: #8950FC" @click="startOrder" :loading="is_loading">
                            Kích hoạt hợp đồng
                        </el-button>
                    </div>
                </form>
            </ValidationObserver>
        </b-modal>
    </div>
</template>

<script>
import ItemsOrder from "./ItemsOrder";
import { START_ORDER } from "../../../../core/services/store/order.module";
import ErrorMessage from "../../common/ErrorMessage";
import {Money} from 'v-money';

export default {
    name: "ModalStart",
    props: {
		id: {
            type: Number,
            default: () => {
                return 0;
            },
        },
        order: {
            type: Object,
            default: () => {
                return null;
            },
        },
        banks: {
            type: Array,
            default: () => {
                return [];
            },
        },
		priceVehicles: {
			type: Array,
			default: () => {
				return [];
			}
		},
		vehicles: {
			type: Array,
			default: () => {
				return [];
			}
		},
    },
	components: {
        ErrorMessage,
		Money,
		ItemsOrder,
    },
    data() {
        return {
			is_collect_additional_payment: false,
			payment_method: 1,
			is_dialog_visible: false,
            money: {
                decimal: ",",
                thousands: ",",
                prefix: "",
                suffix: " VNĐ",
                precision: 0,
                masked: false,
            },
			cash_amount: 0,
			bank_transfer_amount: 0,
			is_loading: false,
			bank_id: null,
        };
    },
    methods: {
        startOrder() { // Kích hoạt hợp đồng từ dạng đặt cọc sang dạng thuê xe.
            this.is_loading = true;
            this.$store
                .dispatch(START_ORDER, {
                    order_id: this.order.id,
                    isPaid: this.isPaid,
                    store_id: this.order.store_id,
                    order_items: this.order.order_items,
                    refund_payment_method: this.refund_payment_method,
                    refund_bank_id: this.refund_bank_id,
					cash_amount: this.cash_amount,
					bank_transfer_amount: this.bank_transfer_amount,
                })
                .then((res) => {
                    this.$emit("addOnSuccess");
                    this.noticeMessage(
                        "success",
                        "Đóng hợp đồng thành công",
                        res.data?.message,
                    );
                    this.is_dialog_visible = false;
                })
                .catch((err) => {
                    this.noticeMessage("error", "Thất bại", err.data?.message);
                })
                .finally(() => (this.is_loading = false));
        },
        onChangePaymentMethod(value) {
            this.refund_payment_method = value;
        },
        watchOrderStatus(val) {
            this.isPaid = val == 'wait_payment' ? false : true
        }
    },
};
</script>

<style scoped>
.form-start-contract .checkbox-input:checked {
    background-color: #009688;
    border: 2px solid #009688;
}

.form-start-contract .checkbox-input:checked::after {
	color: #fff;
}
</style>
