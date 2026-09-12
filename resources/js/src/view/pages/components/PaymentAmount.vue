<template>
    <div class="form-group">
		<label for="complete-transaction-via"><strong>Phương thức thanh toán<span class="text-danger">(*)</span></strong></label>
		
		<div id="complete-transaction-via">
			<el-radio-group v-model="payment_method" size="medium">
				<el-radio-button label="1">Tiền mặt</el-radio-button>
				<el-radio-button label="2">Chuyển khoản</el-radio-button>
				<el-radio-button label="3">Tiền mặt & Chuyển khoản</el-radio-button>
			</el-radio-group>
		</div>
		
		<div v-if="payment_method == 2">
			<div class="form-group required mt-2">
				<label><strong>Chọn tài khoản<span class="text-danger">(*)</span></strong></label>
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

		<div v-if="[1,2].includes(parseInt(payment_method))">
			<div class="form-group row required mt-2">
				<div class="col-md-12">
					<label><strong>Tổng tiền {{ comboLabelValue }}<span class="text-danger">(*)</span></strong></label>
					<ValidationProvider vid="cash_amount" name="Tổng tiền" rules="required|numeric|min:1" v-slot="{ errors }">
						<money v-if="1 == parseInt(payment_method)" v-model="cash_amount" v-bind="money" class="form-control" placeholder="Tổng tiền"></money>
						<money v-else v-model="bank_transfer_amount" v-bind="money" class="form-control" placeholder="Tổng tiền"></money>
						<error-message :errors="errors" field="cash_amount"></error-message>
					</ValidationProvider>
				</div>
			</div>
		</div>

		<div v-if="payment_method == 3">
			<div class="form-group row required mt-2">
				<div class="col-md-6">
					<label><strong>Chọn tài khoản<span class="text-danger">(*)</span></strong></label>
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
				<div class="col-md-6">
					<label><strong>Tổng tiền chuyển khoản<span class="text-danger">(*)</span></strong></label>
					<ValidationProvider vid="bank_transfer_amount" name="Tổng tiền chuyển khoản" rules="required|numeric|min:1" v-slot="{ errors }">
						<money v-model="bank_transfer_amount" v-bind="money" class="form-control" placeholder="Tổng tiền chuyển khoản"></money>
						<error-message :errors="errors" field="bank_transfer_amount"></error-message>
					</ValidationProvider>
				</div>
			</div>
			<div class="form-group row required">
				<div class="col-md-12">
					<label><strong>Tổng tiền mặt<span class="text-danger">(*)</span></strong></label>
					<ValidationProvider vid="cash_amount" name="Tổng tiền mặt" rules="required|numeric|min:1" v-slot="{ errors }">
						<money v-model="cash_amount" v-bind="money" class="form-control" placeholder="Tổng tiền mặt"></money>
						<error-message :errors="errors" field="cash_amount"></error-message>
					</ValidationProvider>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import { Money } from 'v-money';
import ErrorMessage from "../common/ErrorMessage";

export default {
    name: "PaymentAmount",
    props: {
		banks: {
			type: Array,
			default: () => {
                return [];
            },
		}
    },
	components: {
		Money,
		ErrorMessage,
    },
    data() {
        return {
			payment_method: 1,
			bank_id: null,
			bank_transfer_amount: 0,
			cash_amount: 0,
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
		showAccType(type) {
			if (1 == type) {
				return 'TK Thu';
			} else if (2 == type) {
				return 'TK Chi';
			}
			return 'TK Thu & Chi';
		},
    },
	watch: {
		banks(val) {
			this.bank_id = null;
		},
		bank_id(val) {
			console.log('the bank id changed: ', val);
			this.$emit('bank_id_changed', val);
		},
		payment_method(val) {
			this.$emit('payment_method_changed', val);
		},
		bank_transfer_amount(val) {
			this.$emit('bank_transfer_amount_changed', val);
		},
		cash_amount(val) {
			this.$emit('cash_amount_changed', val);
		}
	},
	computed: {
		comboLabelValue() {
			let label = '';
			if ( 1 == this.payment_method ) {
				label = 'tiền mặt';
			} else if ( 2 == this.payment_method ) {
				label = 'chuyển khoản';
			}
			return label;
		}
	}
};
</script>

<style scoped>

</style>
