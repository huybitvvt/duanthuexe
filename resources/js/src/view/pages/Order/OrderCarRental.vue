<template>
    <div>
        <div class="card card-custom gutter-b">
            <div class="card-header">
                <div class="card-title">
                    <h3 class="card-label">Danh sách hợp đồng</h3>
                </div>
                <div class="card-title">
                    <button class="btn btn-success" @click="openModalCreate()">Thêm mới hợp đồng</button>
                    <button @click="exportFile" class="ml-2 btn btn-primary">Export</button>
                </div>

            </div>
            <div class="card-body">
                <div class="alert alert-custom alert-white alert-shadow fade show gutter-b" role="alert">
                    <div class="alert-text">
                        <div class="row">
                            <div class="col-md-2 d-flex align-items-center">
                                <p class="font-weight-bold">Tổng số hợp đồng: <span class="font-weight-bold">{{
                                    order_stats.total_order }}</span></p>
                            </div>
                            <div class="col-md-2">
                                <p class="font-weight-bold">Hoàn thành: <span class="font-weight-bold">{{
                                    order_stats.total_contracts_completed }}</span></p>
                                <p class="font-weight-bold">Đang thuê: <span class="font-weight-bold">{{
                                    order_stats.total_contracts_renting }}</span></p>
                                <p class="text-danger font-weight-bold">Quá hạn: <span class="font-weight-bold">{{
                                    order_stats.total_out_of_date }}</span></p>
                            </div>
                            <div class="col-md-4">
                                <div class="font-weight-bold"><span>Tổng thu thực tế:</span> {{ totalIn | formatPrice }}</div>
								<div class="font-weight-bold"><span>&#x2022; Tổng thu cọc:</span> {{ money_stats.total_deposit | formatPrice }}</div>
								<div class="font-weight-bold"><span>&#x2022; Tổng thu gia hạn:</span> {{ money_stats.total_renew | formatPrice }}</div>
								<div class="font-weight-bold"><span>&#x2022; Tổng thu phí thuê:</span> {{ money_stats.total_rental_fees | formatPrice }}</div>
                            </div>

							<div class="col-md-4">
								<div class="font-weight-bold"><span>Tổng chi thực tế:</span> {{ money_stats.total_real_refund | formatPrice }}</div>
								<div class="font-weight-bold"><span>Tổng tiền cọc phải trả:</span> {{ money_stats.total_origin_refund | formatPrice }}</div>
								<div class="font-weight-bold"><span>&#x2022; Tổng tiền hoàn lại do trả sớm:</span> {{ Math.abs(money_stats.total_money_early) | formatPrice }}</div>
								<div class="font-weight-bold"><span>&#x2022; Tổng tiền phạt muộn:</span> {{ money_stats.total_money_out_date | formatPrice }}</div>
							</div>
                        </div>
                    </div>
                </div>
                <div class="example">


                    <div class="row filter-row-2">
                        <div class="col-md-3 ">
                            <el-input clearable placeholder="#ID, tên, SĐT, biển số" v-model="query.keyword"></el-input>
                        </div>
                        <div class="col-md-3 ">
                            <el-select v-model="query.store_id" filterable clearable placeholder="Cửa hàng"
                                class="w-100">
                                <el-option v-for="item in stores" :key="item.id" :label="item.store_name"
                                    :value="item.id">
                                </el-option>
                            </el-select>
                        </div>
                        <div class="col-md-3 ">
                            <el-select v-model="query.order_status" filterable clearable placeholder="Trạng thái"
                                class="w-100">
                                <el-option v-for="item in ORDER_STATUS" :key="item.value" :label="item.label"
                                    :value="item.value">
                                </el-option>
                            </el-select>
                        </div>

                        <div class=" col-md-3">
                            <el-select multiple filterable class="w-100" placeholder="Nguồn lead" v-model="query.source"
                                clearable>
                                <el-option label="Landing page Himoto" value="NULL"></el-option>
                                <el-option v-for="item in sources" :key="item.user_id" :label="item?.user?.name" :value="item.user_id">
                                    <span style="float: left">{{
                                        item?.user?.name
                                    }}</span>
                                </el-option>
                            </el-select>

                        </div>

                        <div class=" col-md-3">
                            <el-date-picker class="w-100" v-model="query.start_date" type="date" format="yyyy-MM-dd"
                                value-format="yyyy-MM-dd" @change="pickStart" :picker-options="pickerStartOptions"
                                placeholder="Từ ngày">
                            </el-date-picker>
                        </div>
                        <div class=" col-md-3">
                            <el-date-picker class="w-100" v-model="query.end_date" type="date" ref="picker"
                                :onPick="pickEnd" format="yyyy-MM-dd" @change="pickEnd" value-format="yyyy-MM-dd"
                                :picker-options="pickerEndOptions" placeholder="Đến ngày">
                            </el-date-picker>
                        </div>
                        <!-- <div class=" col-md-3  align-self-end hop-dong-qua-han">
                            <el-checkbox v-model="query.is_out_of_date" @change="changeOutOfDate"><span
                                    class="badge badge-danger ">Hợp đồng quá hạn</span></el-checkbox>
                        </div> -->

						<div class="col-md-3 ">
                            <el-select v-model="query.is_out_of_date" filterable clearable placeholder="Hợp đồng quá hạn" class="w-100">
                                <el-option v-for="item in ORDER_OUTDATE_FILTERS" :key="item.value" :label="item.label" :value="item.value"></el-option>
                            </el-select>
                        </div>

                        <div class=" col-md-3 ">
                            <el-button :loading="loading" icon="fa fa-search"
                                class=" btn btn-primary font-weight-bold  " @click="search">
                                Tìm kiếm
                            </el-button>
                            <span class="" v-if="checkedCount > 0">
                                <el-button :loading="loading" icon="fa fa-trash"
                                    class=" btn btn-danger font-weight-bold  " @click="deleteMany">

                                </el-button>


                            </span>

                        </div>





                    </div>



                    <div class="table-responsive">
                        <table class="table table-vertical-center table-hover table-bordered">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Ngày tạo</th>
                                    <th scope="col">Khách hàng</th>
                                    <th scope="col">Xe</th>
                                    <th scope="col">Thời gian</th>
                                    <th scope="col">Ghi chú</th>
                                    <th scope="col">Nguồn lead</th>
                                    <th scope="col">Chi phí</th>
                                    <th scope="col">Trạng thái</th>
                                    <th scope="col">
                                        <div class="d-flex justify-content-between">
                                            <span>Hành động</span>

                                            <span class="checkbox-wrapper">
                                                <input type="checkbox" class="checkbox-input" v-model="selectAll"
                                                    @change="toggleSelectAll" @click="">

                                            </span>
                                        </div>


                                    </th>
                                </tr>
                            </thead>
                            <tbody v-if="orders.length">
                                <tr v-for="(item, index) in orders" :key="index">
                                    <th scope="row">{{ item.id }}</th>
                                    <td>{{ item.created_at }}</td>
                                    <td style="width: 150px;">
                                        <span>{{ item.customer_name }}<br></span>
                                        <span>{{ item.customer_phone }}<br></span>
                                        <span class="label label-inline label-light-primary font-weight-bold">
                                            {{ item.store ? item.store.store_name : '' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column justify-content-center">
                                            <div class="badge badge-primary" v-for="(vehicle, key) in item.vehicles"
                                                :key="key" :class="key ? 'mt-2' : ''">
                                                {{ vehicle.name }} - {{ vehicle.license }} <br />
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column justify-content-center">
                                            <div class="d-inline" v-for="(orderItem, key) in item.orderItems" :key="key"
                                                :class="key ? 'mt-2' : ''">
												<div v-if="item.order_status != 'deposit_contract'">
													<span>Từ {{
														(orderItem.rent_at) | formatDate
														}} đến {{ (orderItem.return_at) | formatDate }}
													</span>
													<br />
													<div class="badge badge-info mb-1">{{ countDateAndHours(orderItem) }} ngày </div>
													<span v-if="item.out_date && item.orderItems.length == key + 1" class="badge badge-danger"><span v-if="item.out_date !== 'Đến giờ trả xe'">Quá hạn:</span> {{ item.out_date }}</span>
												</div>
												<div v-else>
													<div>Ngày cọc: {{ (orderItem.rent_at) | formatDate }}</div>
													<div>Ngày hẹn lấy xe : {{ (orderItem.return_at) | formatDate }}</div>
												</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <el-tooltip :content="item.note">
                                            <span>{{ getDesc(item.note) }}</span>
                                        </el-tooltip>
                                    </td>
                                    <td>
                                        <p v-for="lead in item.leads" v-if="item.leads.length > 0">
                                            {{ lead.user ? lead.user.name : 'Landing page Himoto' }}
                                        </p>
                                    </td>
                                    <td>
                                        <div v-if="item.deposit_closed">
                                            <span v-if="item.deposit_closed == 1">Đặt cọc:</span>
                                            <span v-else>Phí thuê:</span>
											<span class="text-danger">{{ item.first_deposit_amount | formatPrice }}</span>
                                        </div>
										<div v-else>
                                            Đặt cọc:
											<span class="text-danger">{{ calcTotalDeposit(item) | formatPrice }}</span>
                                        </div>
                                        <div v-if="item.order_status != 'deposit_contract' && !item.deposit_closed">
                                            <span v-if="item.order_status !== STATUS_COMPLETED">Tạm tính</span>
                                            <span v-else>Thành tiền:</span>
                                            <span class="text-danger">{{
                                                (parseInt(item.total)
                                                    // + parseInt(item.total_addon)
                                                ) | formatPrice
                                            }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="font-weight-bold"
                                            :class="ORDER_STATUS_DEFINE_CSS[item.order_status]">
                                            {{ ORDER_STATUS_DEFINE[item.order_status] }}
                                        </span>
                                    </td>
                                    <td class="button-container text-center">
										<div class="d-flex">
											<button class="btn btn-xs btn-icon btn-success" title="Sửa hợp đồng"
												@click="openUpdateModal(item)">
												<i class="fas fa-pen-nib"></i>
											</button>
											<!--                                    <button class="btn btn-sm btn-primary" title="Thanh toán hợp đồng" @click="openPaymentModal(item)">-->
											<!--                                        <i class="fas fa-hand-holding-usd"></i>-->
											<!--                                    </button>-->
											<button class="btn btn-xs btn-icon btn-outline-info" title="Xem chi tiết"
												@click="openShowOrder(item)">
												<i class="far fa-eye"></i>
											</button>
											<button class="btn btn-xs btn-icon  btn-danger" title="Xóa hợp đồng"
												@click="deleteOrder(item.id)"><i class="fas fa-trash"></i></button>

											<!-- <el-checkbox v-model="checkedItems[item.id]"></el-checkbox> -->
											<div class="checkbox-wrapper">
												<input type="checkbox" :id="'checkbox_' + item.id" class="checkbox-input"
													v-model="checkedItems[item.id]">

											</div>
										</div>
                                    </td>
                                </tr>
                            </tbody>
                            <tbody v-else>
                                <tr>
                                    <td scope="row" colspan="9">Không tìm thấy hợp đồng phù hợp</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <b-modal title="Tạo hợp đồng" size="xl" ref="modal-contract-create" :centered="true" :scrollable="true"
                hide-footer>
                <order-update @createSuccess="createSuccess"></order-update>
            </b-modal>
            <b-modal :title='"Sửa hợp đồng  " + orderId' size="xl" ref="modal-contract-update" :centered="true"
                :scrollable="true" hide-footer>
                <order-update :id="orderId" @updateSuccess="updateSuccess"></order-update>
            </b-modal>
            <b-modal :title='"Xem hợp đồng  " + orderId' size="xl" ref="modal-contract-show" :centered="true"
                :scrollable="true">
                <order-show :order="order_show"></order-show>
            </b-modal>
            <b-modal title="Thu chi hợp đồng" size="xl" ref="modal-contract-payment" :centered="true" :scrollable="true"
                hide-footer>
                <order-payment :id="orderId" :order-status="order_status_prop"
                    @paymentSuccess="paymentSuccess"></order-payment>
            </b-modal>
            <div class="edu-paginate mx-auto text-center" v-if="orders.length">
                <paginate v-model="page" :page-count="last_page" :page-range="3" :margin-pages="1"
                    :click-handler="clickCallback" :prev-text="'Trước'" :next-text="'Sau'"
                    :container-class="'pagination b-pagination'" :pageLinkClass="'page-link'"
                    :next-link-class="'next-link-item'" :prev-link-class="'prev-link-item'" :prev-class="'page-link'"
                    :next-class="'page-link'" :page-class="'page-item'">
                </paginate>
            </div>
        </div>
    </div>
</template>

<script>
import moment from 'moment-timezone';
import { LEAD_UNIQUE_USERS } from "@/core/services/store/lead.module";
import { SET_BREADCRUMB } from "@/core/services/store/breadcrumbs.module";
import { EXPORT_ORDERS } from "@/core/services/store/exports.module";
import { mapGetters, mapState } from "vuex";
import { SHOW_ORDER_CAR_RENTAL, GET_ORDER_CAR_RENTAL, GET_ORDER_CAR_RENTAL_REPORT } from "@/core/services/store/order.module";
import { REPORT_CAR_RENTAL, REPORT_CAR_RENTAL_NEW } from '../../../core/services/store/report.module';
import OrderUpdate from "./components-order/OrderUpdate";
import OrderShow from "./components-order/OrderShow";
import OrderPayment from "./components-order/OrderPayment";
import { STORE_GET_ALL } from "@/core/services/store/store.module";
import { ORDER_STATUS } from "@/option/orderOption";
import { ORDER_STATUS_DEFINE, ORDER_STATUS_DEFINE_CSS, STATUS_COMPLETED, ORDER_OUTDATE_FILTERS } from "../../../option/orderOption";
import { DELETE_ORDER } from "../../../core/services/store/order.module";
import { getTextShort } from '../../../utils';
import queryMixin from '@/utils/queryMixin.js';

export default {
    name: "OrderCarRental",
    mixins: [queryMixin],
    data() {
        const { page, store_id, ...restQuery } = this.$route?.query || {};
        return {
            selectAll: false,
            checkedItems: {},
            ORDER_STATUS_DEFINE: ORDER_STATUS_DEFINE,
            ORDER_STATUS_DEFINE_CSS: ORDER_STATUS_DEFINE_CSS,
            ORDER_OUTDATE_FILTERS: ORDER_OUTDATE_FILTERS,
            STATUS_COMPLETED: STATUS_COMPLETED,
            moment: moment,
            orders: [],
            order_show: null,
            stores: [],
            sources: [],
            page: +page || 1,
            last_page: 1,
            showModalCreate: false,
            loading: false,
            order_stats: [],
            money_stats: {},
            ORDER_STATUS,
            query: {
                keyword: '',
                store_id: store_id ? +store_id : '',
                order_status: '',
                start_date: '',
                end_date: '',
                is_out_of_date: '',
                ...(restQuery || {}),
            },
            pickerStartOptions: {
                // disabledDate: this.disabledStartDate
            },
            pickerEndOptions: {
                // disabledDate: this.disabledEndDate
            },
            orderId: 0,
            order_status_prop: '',
        }
    },
    components: {
        OrderShow,
        OrderUpdate, OrderPayment
    },

    computed: {


        ...mapGetters(["currentUser"]),
        checkedCount() {
            return Object.values(this.checkedItems).filter(item => item).length;
        },
        checkedItemsArr() {
            return Object.entries(this.checkedItems)
                .filter(([key, value]) => value === true)
                .map(([key, value]) => key);
        },
        profitDetails() {

            const str = `<p>Tiền thuê: ${this.$options.filters.formatPrice(this.money_stats.profit_hiring_fee)} </p>
            <p>Tiền gia hạn: ${this.$options.filters.formatPrice(this.money_stats.addon)} </p>
            <p>Tiền quá hạn: ${this.$options.filters.formatPrice(this.money_stats.money_out_date)} </p>
            <p>Tiền trả sớm: ${this.$options.filters.formatPrice(this.money_stats.money_out_date_early)}</p> `;
            return str;
        },
		totalIn() {
			return parseInt(this.money_stats.total_deposit) + parseInt(this.money_stats.total_renew) + parseInt(this.money_stats.total_rental_fees);
		},
    },
    created() {

        this.calculateDate();
        this.getStore();
        this.getList();
        this.getReport();
        this.listSources();
    },
    mounted() {
        this.$store.dispatch(SET_BREADCRUMB, [{ title: "Đơn hàng" }]);
    },
    methods: {
		calcTotalDeposit(item) {
			if (item?.created_without_collect_deposit && item.created_without_collect_deposit) {
				return 0;
			}
			let total = 0;
			if ( item.first_deposit_amount ) {
				total = item.first_deposit_amount;
				if ( item.additional_deposit_amount ) {
					total += item.additional_deposit_amount;
				}
			} else {
				total = item.pid;
			}
			return total;
		},
        listSources() {
            this.$store.dispatch(LEAD_UNIQUE_USERS, {}).then((data) => {
                this.sources = data?.data || [];
            });
        },
        toggleSelectAll() {
            const shouldSelectAll = Object.values(this.checkedItems).every(value => !value);
            for (const item of this.orders) {
                this.$set(this.checkedItems, item.id, shouldSelectAll);
            }
            this.selectAll = shouldSelectAll;
        },

        getDesc(str) {
            return getTextShort(str);
        },
        getList() {
            this.loading = true;
            this.$store.dispatch(GET_ORDER_CAR_RENTAL, { page: this.page, ...this.query }).then((data) => {
                this.orders = data.data;
                this.last_page = data.pagination.last_page
            }).finally(() => this.loading = false)
        },
        getReport() {
            this.is_loading_search = true;
            this.$store.dispatch(GET_ORDER_CAR_RENTAL_REPORT, this.query).then(data => {
                this.order_stats = data.data;
            });
            this.$store.dispatch(REPORT_CAR_RENTAL_NEW, this.query).then((data) => {
                this.money_stats = data.data;
            });

        },
        getStore() {
            this.$store.dispatch(STORE_GET_ALL, {}).then((data) => {
                this.stores = data.data;
            });
        },
        clickCallback(obj) {
            this.page = obj;
            this.getList();
        },
        search() {

            // this.pushParamsUrl(this.query);
            this.getList();
            this.getReport();

        },
        pushParamsUrl() {
            if (this.$route.path !== '')
                this.$router.push({
                    path: '', query: {
                        page: this.page,
                        ...this.query
                    }
                })
        },
        openModalCreate() {
            this.showModalCreate = true;
            this.$refs['modal-contract-create'].show();

        },
        openUpdateModal(order) {
            this.orderId = order.id;
            this.$refs['modal-contract-update'].show();
        },
        openShowOrder(item) {



            this.$store
                .dispatch(SHOW_ORDER_CAR_RENTAL, item.id)
                .then((res) => {
                    this.order_show = {
                        ...res.data,
                    };
                    this.orderId = this.order_show.id;
                })
                .finally();

            this.$refs['modal-contract-show'].show();
        },
        openPaymentModal(order) {
            this.orderId = order.id;
            this.order_status_prop = order.order_status;
            this.$refs['modal-contract-payment'].show();
        },
        createSuccess() {
            this.showModalCreate = false;
            this.$refs['modal-contract-create'].hide();
            this.getList();
            this.getReport();
        },
        updateSuccess() {
            this.$refs['modal-contract-update'].hide();
            this.getList();
            this.getReport();
        },
        paymentSuccess() {
            this.$refs['modal-contract-payment'].hide();
            this.getList();
            this.getReport();
        },
        changeOutOfDate() {
            if (this.query.is_out_of_date) {
                this.query.order_status = 'renting';
            }
        },
        /* date picker methods */
        pickStart(date) {
            this.fromDate = '';
            if (date) {
                this.fromDate = new Date(date);
            }
        },
        pickEnd(date) {
            this.toDate = '';
            if (date) {
                this.toDate = new Date(date);
            }
        },
        disabledStartDate(date) {
            if (this.toDate) {
                return this.toDate < date
            }
            return date > new Date();
        },
        disabledEndDate(date) {
            if (this.fromDate) {
                return this.fromDate > date || date > new Date();
            }
            return date > new Date();
        },
        calculateDate() {
            let date = new Date();
            this.query.start_date = this.moment().startOf('month').format('YYYY-MM-DD');
            this.query.end_date = this.moment().format('YYYY-MM-DD');
            this.fromDate = new Date(date.getFullYear(), date.getMonth(), 1);
            this.toDate = new Date();
        },
        countDateAndHours(orderItem) {
            let dateRent = new Date(orderItem.rent_at);
            let dateReturn = new Date(orderItem.return_at);
            let diffTime = Math.abs(dateReturn - dateRent);
            return Math.floor(diffTime / (1000 * 60 * 60 * 24));
        },

        deleteOrder(orderId) {
            this.$swal.fire({
                title: 'Bạn có chắc chắn muốn xóa hợp đồng này?',
                showDenyButton: true,
                showCancelButton: false,
                cancelButtonText: "Hủy thao tác",
                confirmButtonText: 'Xóa hợp đồng',
            }).then((result) => {
                if (result.isConfirmed) {
                    this.$store.dispatch(DELETE_ORDER, orderId).then(() => {
                        this.noticeMessage('success', 'Thành công', 'Xóa hợp đồng thành công');
                        this.getList();
                        this.getReport();
                    });
                }
            })
        },
        deleteMany() {
            this.$swal.fire({
                title: `Bạn có chắc chắn muốn xóa ${this.checkedCount} hợp đồng?`,
                showDenyButton: true,
                showCancelButton: false,
                cancelButtonText: "Hủy thao tác",
                confirmButtonText: 'Xóa hợp đồng',
            }).then((result) => {
                if (result.isConfirmed) {
                    this.$store.dispatch(DELETE_ORDER, this.checkedItemsArr).then(() => {
                        this.noticeMessage('success', 'Thành công', 'Xóa nhiều hợp đồng thành công');
                        this.getList();
                        this.getReport();
                    });
                    this.checkedItems = []
                }
            })
        },
        exportFile() {
            this.loading = true;
            this.$store.dispatch(EXPORT_ORDERS, this.query).then().catch((error) => {
                this.noticeMessage('error', 'Thất bại', error.message);
            }).finally(() => {
                this.loading = false;
            })
        }
    }
}
</script>

<style>
.font-weight-bold {
    font-weight: 700 !important;
}

.el-checkbox__inner {
    width: 20px;
    height: 20px;
    border: 2px solid #28468d;
}


/* .el-checkbox__input.is-checked .el-checkbox__inner {
    background-color: #3699FF;

} */

.el-checkbox__inner::after {
    height: 11px;
    left: 7px;
}

.button-container button,
.button-container .checkbox-wrapper {
    margin-left: 5px;
}

.checkbox-wrapper {
    display: flex;
    align-items: center;
    gap: 10px;
}

.checkbox-input {
    appearance: none;
    width: 24px;
    height: 24px;
    border: 2px solid #999;
    border-radius: 5px;
    outline: none;
    cursor: pointer;
    position: relative;
}

.checkbox-input:checked {
    /* background-color: #007bff; */
}

.checkbox-input:checked::after {
    content: "✔";
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 16px;
    color: black;
}

.checkbox-name {
    font-size: 16px;
}

.hop-dong-qua-han .el-checkbox__label {
    padding-left: 4px;
}

.filter-row-2>div {
    margin-bottom: 20px;
}
</style>
