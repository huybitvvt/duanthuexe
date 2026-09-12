<template>
    <ValidationObserver v-slot="{ handleSubmit }" ref="form">
        <div class="card card-custom gutter-b">
            <div class="card-header">
                <div class="card-title">
                    <h3 class="card-label">{{ cardLabel }}</h3>

                </div>
            </div>
            <div class="card-body">
                <form class="form" @submit.prevent="handleSubmit(onSubmit)">
                    <div class="row">
						<div class="col-md-4">
                            <div class="form-group">
                                <label>Loại phiếu<span class="text-danger">(*)</span></label>
                                <ValidationProvider vid="type" name="Loại phiếu" rules="required"
                                    v-slot="{ errors, classes }">
                                    <el-select class="w-100" placeholder="Loại phiếu" v-model="receipt.type"
                                        :class="classes">
                                        <el-option value="in" label="Thu"></el-option>
                                        <el-option value="out" label="Chi"></el-option>
                                    </el-select>

                                    <error-message :errors="errors" field="type"></error-message>
                                </ValidationProvider>
                            </div>
                        </div>

                        <div class="col-md-4" v-if="currentUser.role_id === 1">
                            <div class="form-group">
                                <label>Cửa hàng<span class="text-danger">(*)</span></label>
                                <ValidationProvider vid="store_id" name="Cửa hàng" rules="required" v-slot="{ errors }">
                                    <el-select v-model="receipt.store_id" clearable filterable class="w-100"
                                        placeholder="Chọn cửa hàng" @change="onStoreChange">
                                        <el-option v-for="item in stores" :key="item.name" :label="item.store_name"
                                            :value="item.id">
                                        </el-option>
                                    </el-select>
                                    <error-message :errors="errors" field="store_id"></error-message>
                                </ValidationProvider>
                            </div>
                        </div>

						<div class="col-md-4">
                            <div class="form-group">
                                <label>Hợp đồng</label>
                                <ValidationProvider vid="type" name="Hợp đồng" v-slot="{ errors, classes }">
									<el-autocomplete
										:fetch-suggestions="querySearchAsync"
										@select="handleSelectOrder"
										class="w-100" clearable filterable placeholder="Tìm kiếm hợp đồng" v-model="receipt.order_id"
                                        :class="classes"
									></el-autocomplete>

                                    <error-message :errors="errors" field="type"></error-message>
                                </ValidationProvider>
                            </div>
                        </div>

						<div class="col-md-4">
							<div class="form-group">
								<label for="receipt-date">Ngày tạo phiếu<span class="text-danger">(*)</span></label>
								<ValidationProvider vid="receipt-date" name="Thời gian thuê" rules="required" v-slot="{ errors }">
									<el-date-picker id="receipt-date" class="w-100" type="datetime" v-model="receipt.created_at" format="dd-MM-yyyy HH:mm:ss" placeholder="Chọn thời gian"></el-date-picker>
									<error-message :errors="errors" field="receipt-date"></error-message>
								</ValidationProvider>
							</div>
						</div>
                        
                        <div class="col-md-8">
							<PaymentAmount
								:banks="banks"
								@bank_id_changed="on_bank_id_changed"
								@payment_method_changed="on_payment_method_changed"
								@bank_transfer_amount_changed="on_bank_transfer_amount_changed"
								@cash_amount_changed="on_cash_amount_changed"
							></PaymentAmount>
                           
							<!-- <div class=" ">
                                <label>Số tiền<span class="text-danger">(*)</span></label>
                                <ValidationProvider vid="money" name="Số tiền" rules="required"
                                    v-slot="{ errors, classes }">
                                    <money v-model="receipt.value" v-bind="money" class="form-control"></money>
                                    <error-message :errors="errors" field="money"></error-message>
                                </ValidationProvider>
                            </div>

                            <div class="mt-3 ml-1">
                                <div class="mb-2">
                                    <el-switch v-model="isByCash" active-text="Tiền mặt" inactive-text="Chuyển khoản">
                                    </el-switch>
                                </div>
                                <div class="form-group" :class="{
										'd-none': isRequiredBank !== 'required',
									}">
                                    <ValidationProvider vid="bank_id" name="Tài khoản" :rules="isRequiredBank"
                                        v-slot="{ errors, classes }">
                                        <el-select filterable class="w-100" placeholder="Tài khoản"
                                            v-model="receipt.bank_id" clearable :class="classes">
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
                            </div> -->

                        </div>

                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Ghi chú</label>
                                <ValidationProvider vid="note" name="Ghi chú" rules="required"
                                    v-slot="{ errors, classes }">
                                    <textarea v-model="receipt.note" class="form-control"></textarea>
                                    <error-message :errors="errors" field="note"></error-message>
                                </ValidationProvider>
                            </div>
                        </div>
                    </div>
                    <div class="card-toolbar">
                        <el-button native-type="submit" type="btn btn-success mr-2" :loading="loading">{{ buttonText }}
                        </el-button>
                    </div>
                </form>
            </div>
        </div>
    </ValidationObserver>
</template>

<script>
import moment from "moment";
import ErrorMessage from "../common/ErrorMessage";
import { mapGetters } from "vuex";
import { Money } from 'v-money';
import { SET_BREADCRUMB } from "@/core/services/store/breadcrumbs.module";
import { BANK_INDEX, BANK_SHOW } from "@/core/services/store/banks.module";
import { STORE_GET_ALL } from "@/core/services/store/store.module";
import { RECEIPT_CREATE, RECEIPT_SHOW, RECEIPT_UPDATE } from "../../../core/services/store/receipt.module";
import { GET_ORDER_CAR_RENTAL } from "@/core/services/store/order.module";
import PaymentAmount from '../components/PaymentAmount';

export default {
    name: "receiptCreate",
    data() {
        return {

            banks: [],
            stores: [],
            money: {
                decimal: ',',
                thousands: ',',
                prefix: '',
                suffix: ' VNĐ',
                precision: 0,
                masked: false,

            },
            receipt: {
                store_id: null,
                type: '',
                value: 0,
                note: '',
                bank_id: null,
                payment_method: 1,
				created_at: new Date(),
				order_id: '',
				cash_amount: '',
				bank_transfer_amount: '',
            },

            loading: false,
			timeout: null,
			orders: [],
			last_search_orders: [],
        }
    },
    watch: {
        'receipt.store_id'(newVal,oldVal){
            // if (!this.receipt.bank_id) {
            //     return;
            // }
            this.getBankByStoreId(newVal)
        },
		'receipt.type'(newVal){
			if (newVal) {
				this.getBankByStoreId(this.receipt.store_id)
			}
        },
    },
    async mounted() {
        this.$store.dispatch(SET_BREADCRUMB, [{ title: this.cardLabel }]);
        await this.getReceipt();
		this.orders = await this.loadOrders();
    },
    async created() {
        await this.getStore();
        if (this.currentUser.role_id !== 1) {
            await this.getBankByStoreId(this.currentUser.store_id)
        } else {
			await this.getBankByStoreId(0)
		}
    },
    computed: {
        buttonText() {
            if (this.$route.name === "receipt-update") {
                return "Sửa";
            } else {
                return "Thêm mới";
            }
        },
        cardLabel() {
            return this.$route.name === 'receipt-create' ? 'Thêm phiếu thu chi' : 'Sửa phiếu thu chi';
        },
        ...mapGetters(["currentUser"]),
        isByCash: {
            get() {
                return this.receipt.payment_method == 1;
            },
            set(val) {
                this.receipt.payment_method = val ? 1 : 2;
            },
        },
        isRequiredBank() {
            return this.isByCash ? "" : "required";
        },


    },
    components: { Money, ErrorMessage, PaymentAmount },
    methods: {
        async getReceipt() {
            let id = this.$route?.params?.id;
			if (id) {
				this.$store.dispatch(RECEIPT_SHOW, id).then((res) => {
					this.receipt = res.data;
				}).catch((e) => {
					this.noticeMessage('error', 'Thất bại', e.data?.message);
				});
			}
        },
      
        async getBankByStoreId(id) {
            if (id == null) {
                return;
            }

            id = parseInt(id);

			let type = 20;
			if (this.receipt.type == 'in') {
				type = 10;
			}
            await this.$store
                .dispatch(BANK_INDEX, { store_id: id, account_type: type })
                .then((data) => {
                    const banks = data?.data?.data || [];
                    const [f] = banks || [];

                    this.banks = banks;
                })
                .catch(() => {
                    this.banks = [];
                });
        },
        async getStore() {
            await this.$store.dispatch(STORE_GET_ALL, {}).then((data) => {
                this.stores = data.data;
            });
        },
        onStoreChange(val) {
            this.receipt.bank_id = null;
            this.getBankByStoreId(val);
        },
        onSubmit: function () {
            this.loading = true;
            const actionType = this.$route.name === 'receipt-update' ? RECEIPT_UPDATE : RECEIPT_CREATE;
			this.receipt.created_at = moment(this.receipt.created_at).format('DD-MM-YYYY HH:mm:ss'),
            this.$store.dispatch(actionType, this.receipt).then((res) => {
                this.$router.push({ name: "receipt" }).then(() => {
                    this.noticeMessage('success', 'Thành công', res.message);
                })
            }).catch((e) => {
                this.noticeMessage('error', 'Thất bại', e.data?.message);
            }).finally(() => this.loading = false);
        },

		async searchOrderByKeywords(keyword) {
			let payload = {
				page: 1,
				per_page: 50,
				query: {
					keyword: keyword,
				}
			};

			if (keyword && keyword.length > 0) {
				payload.query['keyword'] = keyword;
			}

            let data = await this.$store.dispatch(GET_ORDER_CAR_RENTAL, payload);
			return data;
        },
		async loadOrders() {
			let listOrders = [];
			let results = await this.searchOrderByKeywords();
			if (results && results.data && results.data.length > 0) {
				results.data.forEach(item => {
					listOrders.push({
						value: `HĐ #${item.id} - ${item.customer_name}`,
						order_id: item.id,
					});
				});
			}
			return listOrders;
		},
		handleSelectOrder(value) {
			let orderId = value?.order_id;
			if (orderId) {
				this.receipt.order_id = orderId.toString();
			}
			
		},
		querySearchAsync(queryString, cb) {
			let filterRes = [];
			if (queryString) {
				if ( this.orders ) { // Check the previous data first
					let sources = [this.last_search_orders, this.orders];
					for ( let i=0; i < sources.length; i++ ) {
						let currentItems = sources[i];
						filterRes = currentItems.filter(this.createFilter(queryString));
						if (filterRes.length > 0) {
							cb(filterRes);
							return;
						}
					}
				}

				clearTimeout(this.timeout);
				this.timeout = setTimeout(async () => { // Wait atleast 3 second when last typing.
					let results = await this.searchOrderByKeywords(queryString);
					let listOrders = [];
					if (results && results.data && results.data.length > 0) {
						results.data.forEach(item => {
							listOrders.push({
								value: `HĐ #${item.id} - ${item.customer_name}`,
								order_id: item.id,
							});
						});
					}
					this.last_search_orders = listOrders;
					let filterRes = (listOrders.length > 0) ? listOrders.filter(this.createFilter(queryString)) : [];
					cb(filterRes);
				}, 3000 * Math.random());
			} else {
				cb(this.orders);
			}
		},
		createFilter(keyword) {
			return (item) => {
				return item.value.toLowerCase().includes(keyword.toLowerCase());
			};
		},
		on_payment_method_changed(value) {
			this.receipt.payment_method = value;
		},
		on_bank_id_changed(value) {
			this.receipt.bank_id = value;
		},
		on_cash_amount_changed(value) {
			this.receipt.cash_amount = value;
		},
		on_bank_transfer_amount_changed(value) {
			this.receipt.bank_transfer_amount = value;
		},
    }

}
</script>

<style scoped></style>
