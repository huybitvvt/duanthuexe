<template>
    <div>
        <b-button class="btn mr-2 btn-primary" @click="showModal()"><i class="fas"></i>Hoàn thành</b-button>
        <b-modal centered v-model="dialogVisible" title="Hoàn thành" hide-footer id="modal-complete-order" @close="onCloseByX">
            <ValidationObserver v-slot="{ handleSubmit }" ref="form">
                <form class="form" @submit.prevent="handleSubmit(completeOrder)">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label><strong>Thời điểm trả xe<span class="text-danger">(*)</span></strong></label>
                                <ValidationProvider vid="completed_at" name="Thời điểm trả xe" rules="required"
                                    v-slot="{ errors }">
                                    <el-date-picker class="w-100" v-model="completed_at" format="dd-MM-yyyy HH:mm:ss"
                                        type="datetime" placeholder="Chọn thời gian">
                                    </el-date-picker>
									<error-message :errors="errors" field="completed_at"></error-message>
                                </ValidationProvider>
                            </div>
                        </div>
                        <div class="col-md-12 form-group">
							<div>
								<label for="custom-note"><strong>Lưu ý:</strong></label>
								<div class="custom-note-list">
									<ul>
										<li>Các trường giá tiền hiển thị <strong>âm(-)</strong> tức là khoản tiền <strong>cần hoàn trả lại cho khách hàng</strong>.</li>
										<li>Các trường giá tiền hiển thị <strong>dương</strong> tức là khoản tiền <strong>cần thu thêm từ khách hàng</strong>.</li>
									</ul>
								</div>
							</div>

							<h5 v-if="showVehicleEditFee">Chi phí phát sinh theo xe(nếu có)</h5>
							<div v-if="showVehicleEditFee" v-for="(item, key) in order.order_items" :key="key" class="mt-5">
								<div :index="key" class="row">
									<div class="col-md-3">
										<label for="debt"><strong>Xe</strong></label>
										<el-input placeholder="Chọn phương tiện" :value="`${item.vehicle.name}(${item.vehicle.license})`" :disabled="true"> </el-input>
									</div>
									<div class="col-md-2">
										<label for="debt" v-if="item.handler_price > 0">
											<el-tooltip content="Là giá chốt cuối cùng(tổng tiền cuối cùng mà khách phải trả theo thỏa thuận cho hợp đồng này, không tính thêm bất kì khoản phí nào kể cả phí quá giờ), quá hạn cũng không tính thêm tiền.">
												<span>
													<strong>Giá Tổng Khác</strong> <i class="fa fa-question" style="font-size: 8px;vertical-align: text-top;"></i>
												</span>
											</el-tooltip>
										</label>
										<label for="debt" v-else><strong>Phí thuê</strong></label>
										<money id="debt" v-if="order && order.data_version == null && item.hiringFee > 0" :value="item.hiringFee" v-bind="money" class="form-control" disabled></money>
										<money id="debt" v-else-if="item.handler_price > 0" :value="item.handler_price" v-bind="money" class="form-control" disabled></money>
										<money id="debt" v-else :value="item.hiring_fee" v-bind="money" class="form-control" disabled></money>
									</div>
									<div class="col-md-2">
										<label for="debt"><strong>Số km hiện tại</strong></label>
										<ValidationProvider vid="odometer_after" name="Số km hiện tại" rules="numeric" v-slot="{ errors }">
											<el-input id="odometer_after" placeholder="Số km hiện tại" v-model="item.odometer_after"></el-input>
											<error-message :errors="errors" field="odometer_after"></error-message>
										</ValidationProvider>
									</div>
									<div class="col-md-2">
										<label for="debt" v-if="item.money_out_date >= 0"><strong>Phí quá hạn</strong></label>
										<label for="debt" v-if="item.money_out_date < 0"><strong>Hoàn lại do trả sớm</strong></label>
										<money id="debt" v-model="item.money_out_date" v-bind="money" class="form-control" :disabled="item.handler_price > 0" @blur.native="resetItemOutdateTime(key)"></money>
									</div>
									<div class="col-md-3">
										<div v-if="item.handler_price == 0">
											<label for="debt" v-if="item.money_out_date >= 0"><strong>Thời gian quá hạn</strong></label>
											<label for="debt" v-if="item.money_out_date < 0"><strong>Thời gian trả sớm</strong></label>
											<div v-if="item.handler_price == 0">
												<span class="badge" :class=" item.money_out_date >= 0 ? 'badge-danger' : 'badge-success'">
													<span v-if="convertMinutesToTime(Math.abs(item.minute_out_date)).days != 0">{{ convertMinutesToTime(Math.abs(item.minute_out_date)).days }} ngày</span>
													<span v-if="convertMinutesToTime(Math.abs(item.minute_out_date)).hours != 0">{{ convertMinutesToTime(Math.abs(item.minute_out_date)).hours }} giờ</span>
													<span v-if="convertMinutesToTime(Math.abs(item.minute_out_date)).minutes != 0">{{ convertMinutesToTime(Math.abs(item.minute_out_date)).minutes }} phút</span>
												</span>
											</div>
										</div>
									</div>
									
								</div>
							</div>

							<div class="mt-5">
								<label for="debt">
									<strong v-if="tempDebt > 0">Tổng số tiền khách còn nợ</strong>
									<strong v-else>Tổng số tiền cần hoàn lại cho khách</strong>
									<span style="display:inline-block;width: 16px;cursor: pointer;" @click="editCustomRefundAmount"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024" data-v-d2e47025=""><path fill="currentColor" d="m199.04 672.64 193.984 112 224-387.968-193.92-112-224 388.032zm-23.872 60.16 32.896 148.288 144.896-45.696zM455.04 229.248l193.92 112 56.704-98.112-193.984-112-56.64 98.112zM104.32 708.8l384-665.024 304.768 175.936L409.152 884.8h.064l-248.448 78.336zm384 254.272v-64h448v64h-448z"></path></svg></span>
								</label>

								<money v-if="editing_custom_refund" id="debt" v-model="custom_refund_amount" v-bind="money" class="form-control"></money>
								<money v-else id="debt" :value="tempDebt" v-bind="money" class="form-control" disabled></money>
							</div>

                            <div class="mt-5">
								<label for="is-customer-paid">
									<el-tooltip content="Chỉ khi khách hàng đã đặt cọc hoặc đã thanh toán phí cho hợp đồng này rồi thì mới cần tính toán khoản tiền hoàn lại cho khách.">
										<span>
											<strong>Khách hàng đã thanh toán cho hợp đồng này chưa?</strong> <i class="fa fa-question" style="font-size: 8px;vertical-align: text-top;"></i>
										</span>
									</el-tooltip>
								</label>
								<div>
									<el-switch v-model="isPaid" active-text="Đã thanh toán" inactive-text="Chưa thanh toán" id="is-customer-paid">
									</el-switch>
								</div>

                                <div class="mt-5" v-if="isPaid">
                                    <label for="complete-transaction-via" v-if="tempDebt >= 0"><strong>Phương thức thu nợ:</strong></label>
                                    <label for="complete-transaction-via" v-else><strong>Phương thức hoàn cọc:</strong></label>
									<div id="complete-transaction-via">
										<el-radio-group v-model="refund_payment_method" size="medium">
											<el-radio-button label="1">Tiền mặt</el-radio-button>
											<el-radio-button label="2">Chuyển khoản</el-radio-button>
											<el-radio-button label="3">Tiền mặt & Chuyển khoản</el-radio-button>
										</el-radio-group>
									</div>
									
									<div v-if="refund_payment_method == 2">
										<div class="form-group required">
											<label><strong>Chọn tài khoản: </strong></label>
											<ValidationProvider vid="refund_bank_id" name="Tài khoản" rules="required"
												v-slot="{ errors, classes }">
												<el-select filterable class="w-100" placeholder="Tài khoản"
													v-model="refund_bank_id" clearable :class="classes">
													<el-option v-for="item in listBanks" :key="item.id" :label="item.bank_name +
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
												<error-message :errors="errors" field="refund_bank_id"></error-message>
											</ValidationProvider>
										</div>
                                    </div>

									<div v-if="refund_payment_method == 3">
										<div class="form-group row required">
											<div class="col-md-7">
												<label><strong>Chọn tài khoản: </strong></label>
												<ValidationProvider vid="refund_bank_id" name="Tài khoản" rules="required"
													v-slot="{ errors, classes }">
													<el-select filterable class="w-100" placeholder="Tài khoản"
														v-model="refund_bank_id" clearable :class="classes">
														<el-option v-for="item in bank_outs" :key="item.id" :label="item.bank_name +
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
													<error-message :errors="errors" field="refund_bank_id"></error-message>
												</ValidationProvider>
											</div>
											<div class="col-md-5">
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
                    <div class="row mt-4 mb-2">
                        <div class="col-md-12">
                            <h6 class="font-weight-bold text-primary">Xác nhận bàn giao & trả xe (Theo mẫu hợp đồng Himoto)</h6>
                        </div>
                        <div class="col-md-6 form-group">
                            <label><strong>Đại diện Bên A nhận xe</strong></label>
                            <el-input placeholder="Tên nhân viên nhận xe" v-model="return_signer_a_name"></el-input>
                        </div>
                        <div class="col-md-6 form-group">
                            <label><strong>Đại diện Bên B trả xe</strong></label>
                            <el-input placeholder="Họ tên người trả xe" v-model="return_signer_b_name"></el-input>
                        </div>
                        <div class="col-md-12 form-group">
                            <label><strong>Ghi chú tình trạng xe lúc trả</strong></label>
                            <el-input type="textarea" :rows="2" placeholder="Tình trạng xe, xăng xe, vết xước, phụ kiện..." v-model="return_additional_note"></el-input>
                        </div>
                    </div>
                    <div class="row d-flex justify-content-end">
                        <el-button native-type="button" class="btn-hoan-thanh-order"
                            style="color: #fff; background: #8950FC" @click="completeOrder" :loading="loadingComplete" :disabled="loadingCalc">
                            Hoàn thành
                        </el-button>
                    </div>
                </form>
            </ValidationObserver>
        </b-modal>
    </div>
</template>

<script>
import moment from "moment";
import { HOAN_THANH } from "../../../../option/orderOption";
import { COMPLETE_ORDER, CALC_ORDER_RETURN_EARLY_AMOUNT, CALC_ORDER_BEFORE_COMPLETE } from "../../../../core/services/store/order.module";
import ErrorMessage from "../../common/ErrorMessage";
import {Money} from 'v-money';
import { get } from "lodash";
import Swal from "sweetalert2";

export default {
    name: "ModalComplete",
    props: {
        order: {
            type: Object,
            default: () => {
                return null;
            },
        },
		debt: {
            type: Number,
            default: () => {
                return 0;
            },
        },
        banks: {
            type: Array,
            default: () => {
                return [];
            },
        },
		bank_outs: {
            type: Array,
            default: () => {
                return [];
            },
        },
    },
	components: {
        ErrorMessage,
		Money,
    },
    data() {
        return {
            isPaid: true,
            refund_bank_id: null,
            refund_payment_method: 1,
            isByCash: true,
            loadingComplete: false,
			loadingCalc: false,
            HOAN_THANH: HOAN_THANH,
            price: "",
            return_at: "",
            completed_at: new Date(),
            dialogVisible: false,
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
			return_early_amount: 0,
			temp_refund_amount: 0,

			custom_refund_amount: 0,
			editing_custom_refund: false,

            return_signer_a_name: "",
            return_signer_b_name: "",
            return_additional_note: "",
        };
    },
    watch: {
		order(val) {
			this.order = val;
        },
        'order.order_status': {
            handler: 'watchOrderStatus',
            deep: true
        },
        refund_payment_method(newValue) {
            // this.isByCash = newValue === 1;
        },
        refundBankValue(value) {
            this.refund_bank_id = value;
        },
		completed_at(value) {
			this.calcReturnEarlyAmount();
		},
		
		bank_transfer_amount(value) {
			if ( parseInt(this.refund_payment_method) == 3 && value >= 0 ) {
				let totalAmount = Math.abs(this.tempDebt);
				if (this.editing_custom_refund) {
					totalAmount = Math.abs(this.custom_refund_amount);
				}
				this.cash_amount = totalAmount - value;
			}
		},
		cash_amount(value) {
			if ( parseInt(this.refund_payment_method) == 3 ) {
				let totalAmount = Math.abs(this.tempDebt);
				if (this.editing_custom_refund) {
					totalAmount = Math.abs(this.custom_refund_amount);
				}
				this.bank_transfer_amount = totalAmount - value;
			}
		},

		tempDebt(val) {
			this.editing_custom_refund = false;
			this.custom_refund_amount = val;
		},
    },
    computed: {
        isPaidInput: {
            get() {
                return this.isPaid
            },
            set(val) {
                this.isPaid = val ? true : false
            }
        },
        isRequiredBank() {
            // return this.isByCash ? "" : "required";
			return this.refund_payment_method == 1 ? "" : "required";
        },
        refundBankValue: {
            get() {
                const bank_id = this.order?.bank_id;
                if (bank_id > -1 && this.debt >= 0) {
                    this.refund_bank_id = bank_id;
                } else {
                    this.refund_bank_id = null;
                }
            },
            set(value) {
                this.refund_bank_id = value;
            },
        },
		listBanks() {
			if (this.debt >= 0) { // khách đang nợ tiền, thu tiền vào tài khoản tổng
				return this.banks;
			}
			return this.bank_outs; // Phải trả khách tiền thừa, chi từ tài khoản của cửa hàng.
		},
		tempDebtOld() {
			if (this.return_early_amount < 0) {
				return this.debt + this.return_early_amount;
			}
			return this.debt;
		},
		tempDebt2: {
			get() {
				if (this.return_early_amount < 0) {
					this.temp_refund_amount = this.debt + this.return_early_amount;
					return this.temp_refund_amount;
				}
				this.temp_refund_amount = this.debt;
				return this.temp_refund_amount;
			},
			set(val) {
				this.custom_refund_amount = val;
			}
		},
		tempDebt() {
			if (this.order.first_deposit_amount) {
				if (!this.order.data_version) {
					return this.debt;
				}
				let amount = this.order.first_deposit_amount ? this.order.first_deposit_amount : 0;
				if (this.order && this.order.v1_total_rental_fees) {
					amount = amount - this.order.v1_total_rental_fees;
				}

				if (this.order.additional_deposit_amount) {
					amount += this.order.additional_deposit_amount;
				}

				let outdateOrEarlyAmount = this.order.order_items.reduce((sum, item) => {
					return sum = sum + (-1 * item.money_out_date);
				}, 0);
				let final = -1*(amount + outdateOrEarlyAmount);
				return final;
			}

			if (this.return_early_amount < 0) {
				console.log('case 2 - debt: ', this.debt);
				console.log('case 2 - return_early_amount: ', this.return_early_amount);
				return this.debt + this.return_early_amount;
			}
			console.log('case 3: ', this.debt);
			return this.debt;
		},
		showVehicleEditFee() {
			/*
				let show = false;
				if (this.order.order_items.length > 0) {
					this.order.order_items.forEach(item => {
						if (item.money_out_date != 0) {
							show = true;
						}
					});
				}
				return show;
			*/
			return true;
		}
    },
    mounted() {
        this.return_at = this.order.order_items.length
            ? this.order.order_items[0].return_at
            : "";
    },
    methods: {
		resetItemOutdateTime(key) {
			this.order.order_items[key].minute_out_date = 0;
		},
		editCustomRefundAmount() {
			this.editing_custom_refund = !this.editing_custom_refund;
			this.custom_refund_amount = this.tempDebt;
		},
		showModal() {
			Swal.fire({
                title: "Bạn chắc chắn muốn hoàn thành hợp đồng này?",
                showDenyButton: true,
                showCancelButton: true,
                confirmButtonText: "Đồng ý",
                cancelButtonText: "Hủy thao tác",
            }).then((result) => {
                if (result.isConfirmed) {
					this.dialogVisible = !this.dialogVisible;
					this.calcReturnEarlyAmount();
					if (this.order) {
						this.return_signer_a_name = this.order.return_signer_a_name || this.order.contract_signer_a_name || "";
						this.return_signer_b_name = this.order.return_signer_b_name || this.order.contract_signer_b_name || this.order.customer?.name || "";
						this.return_additional_note = this.order.return_additional_note || "";
					}
				}
			});
		},
		showAccType(type) {
			if (1 == type) {
				return 'TK Thu';
			} else if (2 == type) {
				return 'TK Chi';
			}
			return 'TK Thu & Chi';
		},
        completeOrder() {
            this.loadingComplete = true;
			let payload = {
				order_id: this.order.id,
				isPaid: this.isPaid,
				store_id: this.order.store_id,
				order_items: this.order.order_items,
				refund_payment_method: this.refund_payment_method,
				refund_bank_id: this.refund_bank_id,
				completed_at: moment(this.completed_at).format('DD-MM-YYYY HH:mm:ss'),
				cash_amount: this.cash_amount,
				bank_transfer_amount: this.bank_transfer_amount,
				total_refund_amount: this.tempDebt, // Default refund amount.
				editing_custom_refund: this.editing_custom_refund, // Is using custom refund amount.
				custom_refund_amount: this.custom_refund_amount, // Custom refund amount enter by staff.
				return_signer_a_name: this.return_signer_a_name,
				return_signer_b_name: this.return_signer_b_name,
				return_additional_note: this.return_additional_note,
			};
			
            this.$store.dispatch(COMPLETE_ORDER, payload)
                .then((res) => {
                    this.$emit("addOnSuccess");
                    this.noticeMessage(
                        "success",
                        "Đóng hợp đồng thành công",
                        res.data?.message,
                    );
                    this.dialogVisible = false;
                })
                .catch((err) => {
                    this.noticeMessage("error", "Không thể hoàn thành hợp đồng", err.data?.message);
                })
                .finally(() => (this.loadingComplete = false));
        },
		calcReturnEarlyAmount() {
			this.loadingCalc = true;
			this.$store
                .dispatch(CALC_ORDER_BEFORE_COMPLETE, {
					order_id: this.order.id,
                    completed_at: moment(this.completed_at).format('DD-MM-YYYY HH:mm:ss'),
                })
                .then((res) => {
					this.$emit("calc_before_order_complete", res);
                })
                .catch((err) => {
                    this.noticeMessage("error", "Tính toán giá hợp đồng thất bại", err.data?.message);
                })
                .finally(() => (this.loadingCalc = false));
		},
        onChangePaymentMethod(value) {
            this.refund_payment_method = value;
        },
        watchOrderStatus(val) {
            this.isPaid = val == 'wait_payment' ? false : true			
        },
		convertMinutesToTime(minutes) {
			const days = Math.floor(minutes / 1440);
			const remainingMinutes = minutes % 1440;
			const hours = Math.floor(remainingMinutes / 60);
			const mins = remainingMinutes % 60;

			return {
				days: days,
				hours: hours,
				minutes: mins
			};
		},
		onCloseByX() {

		},
    },
};
</script>

<style scoped>
.custom-note-list {
	margin-left: 15px;
}

</style>
