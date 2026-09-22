<template>
	<div class="payment-methods">
		<div class="payment-method-heading">
			<label><strong>{{ label }}</strong></label>
			<span class="payment-method-summary">{{ paymentMethodLabel }}</span>
		</div>
		<div class="payment-channel-toggles mt-2" role="group" aria-label="Kênh thu tiền">
			<label class="payment-channel-toggle">
				<input type="checkbox" :checked="hasCashPayment" @change="toggleChannel('cash', $event.target.checked)">
				<span>Tiền mặt</span>
			</label>
			<label class="payment-channel-toggle">
				<input type="checkbox" :checked="hasBankTransfer" @change="toggleChannel('bank', $event.target.checked)">
				<span>Chuyển khoản qua tài khoản ngân hàng</span>
			</label>
		</div>

		<div class="row mt-2 payment-amount-fields">
            <slot name="amount"></slot>
			<div :class="[($slots.amount || $scopedSlots.amount) ? 'col-md-3' : 'col-md-4', 'form-group']">
				<label><strong>Tổng tiền chuyển khoản</strong></label>
				<ValidationProvider vid="bank_transfer_amount" name="Tiền chuyển khoản" rules="numeric|min_value:0" v-slot="{ errors }">
					<money v-model="settings.bank_transfer_amount" v-bind="money" class="form-control" placeholder="Tiền chuyển khoản"></money>
					<error-message :errors="errors" field="bank_transfer_amount"></error-message>
				</ValidationProvider>
			</div>

			<div :class="[($slots.amount || $scopedSlots.amount) ? 'col-md-3' : 'col-md-4', 'form-group']">
				<label><strong>Tổng tiền mặt</strong></label>
				<ValidationProvider vid="cash_amount" name="Tiền mặt" rules="numeric|min_value:0" v-slot="{ errors }">
					<money v-model="settings.cash_amount" v-bind="money" class="form-control" placeholder="Tiền mặt"></money>
					<error-message :errors="errors" field="cash_amount"></error-message>
				</ValidationProvider>
			</div>

			<div v-if="hasBankTransfer" :class="[($slots.amount || $scopedSlots.amount) ? 'col-md-3' : 'col-md-4', 'form-group']">
				<label><strong>Tài khoản nhận tiền</strong></label>
				<ValidationProvider vid="bank_id" name="Tài khoản" :rules="hasBankTransfer ? 'required' : ''" v-slot="{ errors }">
					<el-select
						filterable
						class="w-100"
						:placeholder="hasBankTransfer ? 'Chọn tài khoản ngân hàng' : 'Không có chuyển khoản'"
						v-model="settings.bank_id"
						:disabled="!hasBankTransfer"
						clearable
					>
						<el-option v-for="item in banks" :key="item.id" :label="item.bank_name + ' - ' + item.owner_name + ' - ' + item.account_number + (item.owner_type === 'company' ? ' [Công ty]' : (item.owner_type === 'personal' ? ' [Cá nhân]' : ''))" :value="item.id">
							<span style="float: left">
								{{ showAccType(item.account_type) }}: {{ item.bank_name }} - {{ item.owner_name }} - {{ item.account_number }}
								<span v-if="item.owner_type === 'company'" class="badge badge-light-primary ml-1" style="font-size: 10px;">Công ty</span>
								<span v-else-if="item.owner_type === 'personal'" class="badge badge-light-info ml-1" style="font-size: 10px;">Cá nhân</span>
							</span>
						</el-option>
						<el-option label="-- Khác / Chuyển khoản ví khác (tự điền) --" :value="'other'">
							<span class="text-primary font-weight-bold">+ Khác / Chuyển khoản ví khác (tự điền)</span>
						</el-option>
					</el-select>
					<error-message :errors="errors" field="bank_id"></error-message>
				</ValidationProvider>
			</div>
		</div>

		<div v-if="totalAmount > 0" class="payment-allocation-summary">
			<span>Đã phân bổ: <strong>{{ allocatedAmount | formatPrice }}</strong></span>
			<span>Cần thu: <strong>{{ totalAmount | formatPrice }}</strong></span>
		</div>

		<div v-if="allocationError" class="payment-allocation-error">
			{{ allocationError }}
		</div>

		<div class="form-group mt-3 mb-0">
			<label><strong>Hình thức khác (nếu có)</strong></label>
			<el-input v-model="settings.other_method_note" clearable placeholder="VD: Ví điện tử, bù trừ công nợ..."></el-input>
		</div>
	</div>
</template>

<script>
import { Money } from 'v-money';
import ErrorMessage from "../common/ErrorMessage";

export default {
    name: "PaymentMethod",
    props: {
		label: {
			type: String,
			default: () => {
                return 'Phương thức thanh toán';
            },
		},
		settings: {
			type: Object,
			default: () => {
                return {
					payment_method: 1,
					bank_id: null,
					bank_transfer_amount: 0,
					cash_amount: 0,
					other_method_note: "",
				};
            },
		},
		banks: {
			type: Array,
			default: () => {
                return [];
            },
		},
		fixedAmount: {
			type: Number,
			default: () => {
				return 0;
			}
		}
    },
	components: {
		Money,
		ErrorMessage,
    },
    data() {
		return {
			allocationSyncing: false,
			money: {
                decimal: ',',
                thousands: ',',
                prefix: '',
                suffix: ' VNĐ',
                precision: 0,
                masked: false,
            },
        };
    },
    methods: {
		amount(value) {
			const parsed = Number(value);
			return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
		},
		showAccType(type) {
			if (1 == type) {
				return 'TK Thu';
			} else if (2 == type) {
				return 'TK Chi';
			}
			return 'TK Thu & Chi';
		},
		inferPaymentMethod() {
			const bankAmount = this.amount(this.settings.bank_transfer_amount);
			const cashAmount = this.amount(this.settings.cash_amount);
			let paymentMethod = 1;

			if (bankAmount > 0 && cashAmount > 0) {
				paymentMethod = 3;
			} else if (bankAmount > 0) {
				paymentMethod = 2;
			}

			if (this.settings.payment_method !== paymentMethod) {
				this.$set(this.settings, "payment_method", paymentMethod);
			}

			if (bankAmount === 0 && this.settings.bank_id !== null) {
				this.$set(this.settings, "bank_id", null);
			}
		},
		toggleChannel(channel, enabled) {
			const field = channel === 'bank' ? 'bank_transfer_amount' : 'cash_amount';
			const otherField = channel === 'bank' ? 'cash_amount' : 'bank_transfer_amount';
			if (!enabled) {
				this.$set(this.settings, field, 0);
				if (channel === 'bank') this.$set(this.settings, 'bank_id', null);
				return;
			}
			const total = this.amount(this.fixedAmount);
			const other = this.amount(this.settings[otherField]);
			this.$set(this.settings, field, Math.max(total - other, 0));
			if (channel === 'bank' && !this.settings.bank_id && this.banks.length === 1) {
				this.$set(this.settings, 'bank_id', this.banks[0].id);
			}
		},
		syncAllocation(field, value, oldValue) {
			if (this.allocationSyncing) {
				return;
			}

			const total = this.amount(this.fixedAmount);
			if (total === 0) {
				return;
			}

			const changedAmount = this.amount(value);
			const previousAmount = this.amount(oldValue);
			const otherField = field === "bank_transfer_amount" ? "cash_amount" : "bank_transfer_amount";
			const previousOtherAmount = this.amount(this.settings[otherField]);

			// When the previous allocation was complete, keep it complete while the
			// user edits either side. This makes entering "CK = 1.000.000, tiền mặt
			// = 0" work without requiring the old three-option selector.
			if (previousAmount + previousOtherAmount !== total) {
				return;
			}

			const nextOtherAmount = Math.max(total - changedAmount, 0);
			if (previousOtherAmount === nextOtherAmount) {
				return;
			}

			this.allocationSyncing = true;
			this.$set(this.settings, otherField, nextOtherAmount);
			this.$nextTick(() => {
				this.allocationSyncing = false;
			});
		},
		syncFixedAmount(value, oldValue) {
			const total = this.amount(value);
			const previousTotal = this.amount(oldValue);
			const bankAmount = this.amount(this.settings.bank_transfer_amount);
			const cashAmount = this.amount(this.settings.cash_amount);

			if (total === 0) {
				if (bankAmount !== 0) {
					this.$set(this.settings, "bank_transfer_amount", 0);
				}
				if (cashAmount !== 0) {
					this.$set(this.settings, "cash_amount", 0);
				}
				return;
			}

			if (bankAmount === 0 && cashAmount === 0) {
				if (Number(this.settings.payment_method) === 2) {
					this.$set(this.settings, "bank_transfer_amount", total);
				} else {
					this.$set(this.settings, "cash_amount", total);
				}
				return;
			}

			if (previousTotal > 0 && bankAmount === previousTotal && cashAmount === 0) {
				this.$set(this.settings, "bank_transfer_amount", total);
			} else if (previousTotal > 0 && cashAmount === previousTotal && bankAmount === 0) {
				this.$set(this.settings, "cash_amount", total);
			}
		},
		watch_settings(val, oldVal) {
			this.$emit("setting_changed", val);
		},
    },
	watch: {
		settings: {
			handler: "watch_settings",
			deep: true,
		},
		"settings.bank_transfer_amount": {
			handler(value, oldValue) {
				this.syncAllocation("bank_transfer_amount", value, oldValue);
				this.inferPaymentMethod();
			},
		},
		"settings.cash_amount": {
			handler(value, oldValue) {
				this.syncAllocation("cash_amount", value, oldValue);
				this.inferPaymentMethod();
			},
		},
		fixedAmount: {
			handler: "syncFixedAmount",
			immediate: true,
		},
	},
	computed: {
		totalAmount() {
			return this.amount(this.fixedAmount);
		},
		allocatedAmount() {
			return this.amount(this.settings.bank_transfer_amount) + this.amount(this.settings.cash_amount);
		},
		hasBankTransfer() {
			return this.amount(this.settings.bank_transfer_amount) > 0;
		},
		hasCashPayment() {
			return this.amount(this.settings.cash_amount) > 0;
		},
		selectedBank() {
			if (!this.settings.bank_id || !this.banks || !this.banks.length) {
				return null;
			}
			return this.banks.find(b => b.id === this.settings.bank_id) || null;
		},
		bankTypeLabel() {
			if (this.selectedBank) {
				if (this.selectedBank.owner_type === 'company') return 'CK công ty';
				if (this.selectedBank.owner_type === 'personal') return 'CK cá nhân';
			}
			return 'Chuyển khoản';
		},
		paymentMethodLabel() {
			if ((this.settings.other_method_note || "").trim()) {
				return `Khác: ${this.settings.other_method_note.trim()}`;
			}
			if (Number(this.settings.payment_method) === 3) {
				return `Tự xác định: Tiền mặt & ${this.bankTypeLabel}`;
			}
			if (Number(this.settings.payment_method) === 2) {
				return `Tự xác định: ${this.bankTypeLabel}`;
			}
			return "Tự xác định: Tiền mặt";
		},
		allocationError() {
			const total = this.totalAmount;
			if (total === 0) {
				return "";
			}

			if (this.allocatedAmount === total) {
				return "";
			}

			return "Tổng tiền mặt và chuyển khoản phải bằng số tiền cần thu.";
		},
	}
};
</script>

<style scoped>
.payment-method-heading {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
}

.payment-method-summary {
	color: #606266;
	font-size: 12px;
}

.payment-channel-toggles {
	display: flex;
	flex-wrap: wrap;
	gap: 16px;
}

.payment-channel-toggle {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	font-size: 13px;
	cursor: pointer;
}

.payment-allocation-summary {
	display: flex;
	gap: 16px;
	color: #606266;
	font-size: 12px;
	margin-top: -4px;
}

.payment-allocation-error {
	color: #f56c6c;
	font-size: 12px;
	line-height: 1;
	padding-bottom: 8px;
}
</style>

<style>
/* All amount fields share one flex row so wrapped labels align the inputs. */
.contract-payment-method .payment-amount-fields > .form-group {
    display: flex;
    flex-direction: column;
}
.contract-payment-method .payment-amount-fields > .form-group > label {
    flex: 1;
    margin-bottom: 8px;
}
.contract-payment-method .payment-amount-fields .form-control {
    min-height: 42px;
}
</style>
