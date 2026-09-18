<template>
    <div>
        <div class="card card-custom gutter-b">
            <div class="card-header">
                <div class="card-title">
                    <h3 class="card-label">Danh sách hợp đồng</h3>
                </div>
                <div class="card-title">
                    <button class="btn btn-success" @click="openModalCreate()">Thêm mới hợp đồng</button>
                    <button @click="exportFile" class="ml-2 btn btn-primary">Xuất Excel theo bộ lọc thời gian</button>
                </div>

            </div>
            <div class="card-body">
                <section class="contract-overview" aria-label="Thống kê hợp đồng">
                    <div class="contract-overview-card"><h4>Hợp đồng</h4><dl>
                        <div><dt>Tổng số hợp đồng</dt><dd>{{ order_stats.total_order }}</dd></div>
                        <div><dt>Hoàn thành</dt><dd>{{ order_stats.total_contracts_completed }}</dd></div>
                        <div><dt>Đang thuê</dt><dd>{{ order_stats.total_contracts_renting }}</dd></div>
                        <div class="text-danger"><dt>Quá hạn</dt><dd>{{ order_stats.total_out_of_date }}</dd></div>
                    </dl></div>
                    <div class="contract-overview-card"><h4>Thu thực tế</h4><dl>
                        <div><dt>Tổng thu</dt><dd>{{ totalIn | formatPrice }}</dd></div>
                        <div><dt>Thu cọc</dt><dd>{{ money_stats.total_deposit | formatPrice }}</dd></div>
                        <div><dt>Thu gia hạn</dt><dd>{{ money_stats.total_renew | formatPrice }}</dd></div>
                        <div><dt>Thu phí thuê</dt><dd>{{ money_stats.total_rental_fees | formatPrice }}</dd></div>
                    </dl></div>
                    <div class="contract-overview-card"><h4>Chi & hoàn trả</h4><dl>
                        <div><dt>Tổng chi thực tế</dt><dd>{{ money_stats.total_real_refund | formatPrice }}</dd></div>
                        <div><dt>Tiền cọc phải trả</dt><dd>{{ money_stats.total_origin_refund | formatPrice }}</dd></div>
                        <div><dt>Hoàn do trả sớm</dt><dd>{{ Math.abs(money_stats.total_money_early) | formatPrice }}</dd></div>
                        <div><dt>Phạt muộn</dt><dd>{{ money_stats.total_money_out_date | formatPrice }}</dd></div>
                    </dl></div>
                </section>
                <div class="example">
                    <!-- Tabs Lọc Đơn Trong Ngày (P2) -->
                    <div class="today-filter-tabs d-flex flex-wrap align-items-center mb-3">
                        <span class="font-weight-bold text-muted mr-3 font-size-sm">ĐƠN TRONG NGÀY:</span>
                        <button
                            type="button"
                            class="btn btn-sm mr-2 mb-1"
                            :class="!query.today_filter ? 'btn-primary' : 'btn-light'"
                            @click="setTodayFilter('')"
                        >
                            Tất cả
                        </button>
                        <button
                            type="button"
                            class="btn btn-sm mr-2 mb-1 font-weight-bold"
                            :class="query.today_filter === 'created_today' ? 'btn-primary' : 'btn-light-primary'"
                            @click="setTodayFilter('created_today')"
                        >
                            Tạo hôm nay
                        </button>
                        <button
                            type="button"
                            class="btn btn-sm mr-2 mb-1 font-weight-bold"
                            :class="query.today_filter === 'pickup_today' ? 'btn-success' : 'btn-light-success'"
                            @click="setTodayFilter('pickup_today')"
                        >
                            Nhận xe hôm nay
                        </button>
                        <button
                            type="button"
                            class="btn btn-sm mr-2 mb-1 font-weight-bold"
                            :class="query.today_filter === 'return_today' ? 'btn-warning' : 'btn-light-warning'"
                            @click="setTodayFilter('return_today')"
                        >
                            Hẹn trả hôm nay
                        </button>
                        <button
                            type="button"
                            class="btn btn-sm mr-2 mb-1 font-weight-bold"
                            :class="query.today_filter === 'transaction_today' ? 'btn-info' : 'btn-light-info'"
                            @click="setTodayFilter('transaction_today')"
                        >
                            Giao dịch hôm nay
                        </button>
                    </div>

                    <div class="row filter-row-2">
                        <div class="col-md-3 ">
                            <el-input clearable placeholder="#ID, Số HĐ, tên, SĐT, biển số" v-model="query.keyword"></el-input>
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
                            <el-button :loading="loading"
                                class=" btn btn-primary font-weight-bold " @click="search">
                                Tìm kiếm
                            </el-button>
                            <span class="" v-if="checkedCount > 0">
                                <el-button :loading="loading"
                                    class=" btn btn-danger font-weight-bold ml-2 " @click="deleteMany">
                                    Xóa đã chọn
                                </el-button>
                            </span>
                        </div>





                    </div>



                    <HimotoErrorState v-if="errorMessage" title="Không thể tải danh sách hợp đồng" :message="errorMessage" @retry="getList" />
                    <HimotoTableSkeleton v-else-if="loading" :rows="6" :columns="10" />
                    <div
                        v-else-if="orders.length"
                        v-drag-scroll
                        class="table-responsive"
                        role="region"
                        aria-label="Danh sách hợp đồng, có thể kéo ngang bằng chuột"
                    >
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
                                                    @change="toggleSelectAll">

                                            </span>
                                        </div>


                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in orders" :key="item.id || index">
                                    <th scope="row">
                                        <div>#{{ item.id }}</div>
                                        <div v-if="item.contract_number" class="badge badge-light-primary text-primary font-weight-bolder mt-1" style="font-size: 11px;">
                                            {{ item.contract_number }}
                                        </div>
                                    </th>
                                    <td class="contract-date-cell"><div>{{ (item.created_at || "").split(" ")[0] }}</div><small class="text-muted">{{ (item.created_at || "").split(" ")[1] }}</small></td>
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
                                            <span v-if="item.vehicle_exchange_count" class="badge badge-light-warning text-warning mt-2">
                                                Đã đổi xe {{ item.vehicle_exchange_count }} lần
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column justify-content-center">
                                            <div class="d-inline" v-for="(orderItem, key) in item.orderItems" :key="key"
                                                :class="key ? 'mt-2' : ''">
												<div v-if="item.order_status != 'deposit_contract'">
													<span class="contract-date-line">Từ {{
														(orderItem.rent_at) | formatDate
														}}</span><span class="contract-date-line">Đến {{ (orderItem.return_at) | formatDate }}
													</span>
													<br />
													<div class="badge badge-info mb-1">{{ countDateAndHours(orderItem) }} ngày </div>
													<span v-if="item.out_date && item.orderItems.length == key + 1" class="badge badge-danger"><span v-if="item.out_date !== 'Đến giờ trả xe'">Quá hạn:</span> {{ item.out_date }}</span>
												</div>
												<div v-else>
													<div class="contract-date-line">Ngày cọc: {{ (orderItem.rent_at) | formatDate }}</div>
													<div class="contract-date-line">Ngày hẹn lấy xe: {{ (orderItem.return_at) | formatDate }}</div>
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
                                        <template v-if="item.leads && item.leads.length">
                                            <p v-for="lead in item.leads" :key="lead.id">
                                                {{ lead.user ? lead.user.name : 'Landing page Himoto' }}
                                            </p>
                                        </template>
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
										<div class="d-flex align-items-center">
											<button class="btn btn-xs btn-success font-weight-bold mr-1" title="Sửa hợp đồng"
												@click="openUpdateModal(item)">
												Sửa
											</button>
											<button class="btn btn-xs btn-outline-info font-weight-bold mr-1" title="Xem chi tiết"
												@click="openShowOrder(item)">
												Xem
											</button>
											<button class="btn btn-xs btn-outline-primary font-weight-bold mr-1" title="In hợp đồng"
												@click="printOrderContract(item)">
												In
											</button>
											<button v-if="item.order_status === 'renting'" class="btn btn-xs btn-warning font-weight-bold mr-1" title="Thu thêm, gia hạn hoặc quyết toán trả xe"
												@click="openUpdateModal(item)">
												Thu thêm / Trả xe
											</button>
											<button class="btn btn-xs btn-danger font-weight-bold mr-2" title="Xóa hợp đồng"
												@click="deleteOrder(item.id)">
												Xóa
											</button>

											<div class="checkbox-wrapper">
												<input type="checkbox" :id="'checkbox_' + item.id" class="checkbox-input"
													v-model="checkedItems[item.id]">
											</div>
										</div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <HimotoEmptyState v-else title="Không tìm thấy hợp đồng nào" description="Thử thay đổi bộ lọc hoặc thêm mới hợp đồng vào hệ thống." actionText="Thêm mới hợp đồng" @action="openModalCreate()" />
                </div>
            </div>

            <b-modal title="Tạo hợp đồng" size="xl" modal-class="contract-modal-wide" ref="modal-contract-create" :centered="true" :scrollable="true"
                hide-footer>
                <order-update @createSuccess="createSuccess"></order-update>
            </b-modal>
            <b-modal :title='"Sửa hợp đồng  " + orderId' size="xl" modal-class="contract-modal-wide" ref="modal-contract-update" :centered="true"
                :scrollable="true" hide-footer>
                <order-update :id="orderId" @updateSuccess="updateSuccess"></order-update>
            </b-modal>
            <b-modal :title='"Xem hợp đồng  " + orderId' size="xl" modal-class="contract-modal-wide" ref="modal-contract-show" :centered="true"
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
            <ModalContractPreview v-model="showPrintModal" :doc="printDocumentDto" />
        </div>
    </div>
</template>

<script>
import moment from 'moment-timezone';
import { LEAD_UNIQUE_USERS } from "@/core/services/store/lead.module";
import { SET_BREADCRUMB } from "@/core/services/store/breadcrumbs.module";
import { EXPORT_ORDERS } from "@/core/services/store/exports.module";
import { mapGetters } from "vuex";
import { SHOW_ORDER_CAR_RENTAL, GET_ORDER_CAR_RENTAL, GET_ORDER_CAR_RENTAL_REPORT, DELETE_ORDER, GET_ORDER_DOCUMENT } from "@/core/services/store/order.module";
import { REPORT_CAR_RENTAL_NEW } from '../../../core/services/store/report.module';
import OrderUpdate from "./components-order/OrderUpdate";
import OrderShow from "./components-order/OrderShow";
import OrderPayment from "./components-order/OrderPayment";
import ModalContractPreview from "./components-order/ModalContractPreview";
import { STORE_GET_ALL } from "@/core/services/store/store.module";
import { ORDER_STATUS } from "@/option/orderOption";
import { ORDER_STATUS_DEFINE, ORDER_STATUS_DEFINE_CSS, STATUS_COMPLETED, ORDER_OUTDATE_FILTERS } from "../../../option/orderOption";
import { getTextShort } from '../../../utils';
import queryMixin from '@/utils/queryMixin.js';
import HimotoTableSkeleton from "@/view/components/himoto/HimotoTableSkeleton.vue";
import HimotoEmptyState from "@/view/components/himoto/HimotoEmptyState.vue";
import HimotoErrorState from "@/view/components/himoto/HimotoErrorState.vue";
import { normalizePaginator } from "@/utils/paginatorAdapter";
import { getApiMessage } from "@/utils/apiErrorHandler";

export default {
    name: "OrderCarRental",
    mixins: [queryMixin],
    data() {
        const { page, store_id, open_order, ...restQuery } = this.$route?.query || {};
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
            errorMessage: null,
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
                today_filter: '',
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
            lastFetchedAt: 0,
            showPrintModal: false,
            printDocumentDto: null,
            pendingOpenOrderId: open_order ? Number(open_order) : null,
        }
    },
    components: {
        OrderShow,
        OrderUpdate,
        OrderPayment,
        ModalContractPreview,
        HimotoTableSkeleton,
        HimotoEmptyState,
        HimotoErrorState
    },

    computed: {


        ...mapGetters(["currentUser"]),
        checkedCount() {
            return Object.values(this.checkedItems).filter(item => item).length;
        },
        checkedItemsArr() {
            return Object.entries(this.checkedItems)
                .filter(([, value]) => value === true)
                .map(([key]) => key);
        },
        profitDetails() {

            const str = `<p>Tiền thuê: ${this.$options.filters.formatPrice(this.money_stats.profit_hiring_fee)} </p>
            <p>Tiền gia hạn: ${this.$options.filters.formatPrice(this.money_stats.addon)} </p>
            <p>Tiền quá hạn: ${this.$options.filters.formatPrice(this.money_stats.money_out_date)} </p>
            <p>Tiền trả sớm: ${this.$options.filters.formatPrice(this.money_stats.money_out_date_early)}</p> `;
            return str;
        },
		totalIn() {
			return [
				this.money_stats.total_deposit,
				this.money_stats.total_renew,
				this.money_stats.total_rental_fees,
			].reduce((total, value) => total + (Number(value) || 0), 0);
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
        if (this.pendingOpenOrderId) {
            this.$nextTick(() => this.openShowOrder({ id: this.pendingOpenOrderId }));
        }
    },
    watch: {
        "$route.query.open_order"(value) {
            const orderId = Number(value || 0);
            if (orderId && orderId !== Number(this.order_show?.id || 0)) {
                this.openShowOrder({ id: orderId });
            }
        },
    },
    activated() {
        const queryPage = +this.$route?.query?.page || 1;
        const queryKeyword = this.$route?.query?.keyword || '';
        const paramsChanged = queryPage !== this.page || queryKeyword !== (this.query.keyword || '');
        const isTtlExpired = !this.lastFetchedAt || (Date.now() - this.lastFetchedAt > 60000);

        if (paramsChanged) {
            this.page = queryPage;
            this.query.keyword = queryKeyword;
            this.getList();
            this.getReport();
        } else if (isTtlExpired) {
            this.getList();
            this.getReport();
        }
    },
    methods: {
        setTodayFilter(filter) {
            this.query.today_filter = filter;
            this.page = 1;
            this.getList();
            this.getReport();
        },
		async printOrderContract(item) {
			try {
				const res = await this.$store.dispatch(GET_ORDER_DOCUMENT, item.id);
				this.printDocumentDto = res.data || res;
				this.showPrintModal = true;
			} catch (err) {
				const msg = getApiMessage(err, "Không thể tải tài liệu hợp đồng");
				this.$message.error(msg);
			}
		},
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
            }).catch(() => {});
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
            this.errorMessage = null;
            this.$store.dispatch(GET_ORDER_CAR_RENTAL, { page: this.page, ...this.query })
                .then((data) => {
                    const paginated = normalizePaginator(data);
                    this.orders = paginated.items || [];
                    this.last_page = paginated.lastPage || 1;
                    this.lastFetchedAt = Date.now();
                })
                .catch((err) => {
                    this.errorMessage = getApiMessage(err);
                })
                .finally(() => {
                    this.loading = false;
                });
        },
        getReport() {
            this.is_loading_search = true;
            const p1 = this.$store.dispatch(GET_ORDER_CAR_RENTAL_REPORT, this.query).then(data => {
                this.order_stats = data?.data || {};
            }).catch(() => {});
            const p2 = this.$store.dispatch(REPORT_CAR_RENTAL_NEW, this.query).then((data) => {
                this.money_stats = data?.data || {};
            }).catch(() => {});
            Promise.all([p1, p2]).finally(() => {
                this.is_loading_search = false;
            });
        },
        getStore() {
            this.$store.dispatch(STORE_GET_ALL, {}).then((data) => {
                this.stores = data?.data || [];
            }).catch(() => {});
        },
        clickCallback(obj) {
            this.page = obj;
            this.pushParamsUrl();
            this.getList();
        },
        search() {
            this.page = 1;
            this.pushParamsUrl();
            this.getList();
            this.getReport();
        },
        pushParamsUrl() {
            this.$router.push({
                path: '',
                query: {
                    page: this.page,
                    ...this.query
                }
            }).catch(() => {});
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
            this.order_show = null;
            return this.$store
                .dispatch(SHOW_ORDER_CAR_RENTAL, item.id)
                .then((res) => {
                    this.order_show = {
                        ...res.data,
                    };
                    this.orderId = this.order_show.id;
                    this.$refs['modal-contract-show'].show();
                })
                .catch((err) => {
                    this.noticeMessage('error', 'Thất bại', getApiMessage(err));
                });
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
                    }).catch((err) => {
                        this.noticeMessage('error', 'Thất bại', getApiMessage(err));
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
                    }).catch((err) => {
                        this.noticeMessage('error', 'Thất bại', getApiMessage(err));
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

<style>
.contract-date-cell, .contract-date-line { white-space: nowrap; }
.contract-date-line { display: block; line-height: 1.7; }
.contract-overview { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin-bottom: 24px; }
.contract-overview-card { border: 1px solid #e5e9f0; border-radius: 12px; padding: 18px 20px; background: #fff; }
.contract-overview-card h4 { font-size: 15px; font-weight: 700; margin: 0 0 12px; color: #334155; }
.contract-overview-card dl { margin: 0; }
.contract-overview-card dl > div { display: flex; align-items: baseline; justify-content: space-between; gap: 16px; padding: 9px 0; border-top: 1px solid #eef1f5; }
.contract-overview-card dt { font-weight: 400; }
.contract-overview-card dd { margin: 0; font-weight: 600; white-space: nowrap; }
@media (max-width: 991px) { .contract-overview { grid-template-columns: 1fr; } }
</style>
