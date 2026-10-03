<template>
    <div>
        <div class="card card-custom gutter-b">
            <div class="card-header flex-wrap align-items-center">
                <div class="card-title">
                    <h3 class="card-label">Đơn thuê xe</h3>
                </div>
                <div class="card-toolbar d-flex flex-wrap align-items-center">
                    <button v-if="canReportStore" @click="exportFile" class="btn btn-primary font-weight-bold mr-2 mb-1">Xuất Excel theo bộ lọc thời gian</button>
                    <router-link
                        :to="rentalWarehouseLink"
                        class="btn btn-warning font-weight-bold mb-1 text-dark"
                        title="Mở kho thuê xe"
                    >
                        <i class="fas fa-warehouse mr-1"></i>Kho thuê xe
                    </router-link>
                </div>
            </div>
            <div class="card-body">
                <details class="rental-filters mb-4">
                    <summary>Thống kê và bộ lọc đơn thuê</summary>
                    <div class="pt-4">
                <section class="contract-overview" aria-label="Thống kê hợp đồng">
                    <div class="contract-overview-card"><h4>Hợp đồng</h4><dl>
                        <div><dt>Tổng số hợp đồng</dt><dd>{{ order_stats.total_order }}</dd></div>
                        <div><dt>Hoàn thành</dt><dd>{{ order_stats.total_contracts_completed }}</dd></div>
                        <div><dt>Đang thuê</dt><dd>{{ order_stats.total_contracts_renting }}</dd></div>
                        <div class="text-danger cursor-pointer" style="cursor: pointer;" title="Bấm để lọc danh sách khách quá hạn" @click="filterOverdueOnly">
                            <dt>Quá hạn <i class="fas fa-filter ml-1" style="font-size: 11px;"></i></dt>
                            <dd>{{ order_stats.total_out_of_date }}</dd>
                        </div>
                    </dl></div>
                    <div v-if="canReportStore" class="contract-overview-card"><h4>Thu thực tế</h4><dl>
                        <div><dt>Tổng thu</dt><dd>{{ totalIn | formatPrice }}</dd></div>
                        <div><dt>Thu cọc</dt><dd>{{ money_stats.total_deposit | formatPrice }}</dd></div>
                        <div><dt>Thu gia hạn</dt><dd>{{ money_stats.total_renew | formatPrice }}</dd></div>
                        <div><dt>Thu phí thuê</dt><dd>{{ money_stats.total_rental_fees | formatPrice }}</dd></div>
                    </dl></div>
                    <div v-if="canReportStore" class="contract-overview-card"><h4>Chi & hoàn trả</h4><dl>
                        <div><dt>Tổng chi thực tế</dt><dd>{{ money_stats.total_real_refund | formatPrice }}</dd></div>
                        <div><dt>Tiền cọc phải trả</dt><dd>{{ money_stats.total_origin_refund | formatPrice }}</dd></div>
                        <div><dt>Hoàn do trả sớm</dt><dd>{{ Math.abs(money_stats.total_money_early) | formatPrice }}</dd></div>
                        <div><dt>Phạt muộn</dt><dd>{{ money_stats.total_money_out_date | formatPrice }}</dd></div>
                    </dl></div>
                </section>

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
                            <search-suggest endpoint="/api/auth/order/car-rental" :params="query" query-key="keyword" fields="id,contract_number,customer.name,customer.phone,vehicles.name,vehicles.license,order_items.vehicle.name,order_items.vehicle.license" @select="search" @submit="search" clearable placeholder="#ID, Số HĐ, tên, SĐT, tên xe, biển số" v-model="query.keyword"></search-suggest>
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

                        <div v-if="canManageLeads" class=" col-md-3">
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
                            <span class="" v-if="canDeleteOrder && checkedCount > 0">
                                <el-button :loading="loading"
                                    class=" btn btn-danger font-weight-bold ml-2 " @click="deleteMany">
                                    Xóa đã chọn
                                </el-button>
                            </span>
                        </div>





                    </div>



                    </div>
                </details>
                <div class="example">
                    <section class="contract-category-list" aria-label="Danh sách theo loại hồ sơ">
                        <div class="contract-category-tabs nav nav-tabs" aria-label="Loại hồ sơ">
                            <button v-for="category in categories" :key="category.value" type="button" class="nav-link"
                                :aria-pressed="activeCategory === category.value"
                                :class="{ active: activeCategory === category.value }" @click="setCategory(category.value)">
                                {{ category.label }}
                            </button>
                        </div>
                        <OrderQuickCreate v-if="canCreateOrder" ref="quickCreate" :mode="activeCategory" :stores="stores" @continue="openQuickCreate" />
                        <div class="rental-list-heading">
                            <h4>Danh sách {{ activeCategoryLabel.toLowerCase() }}</h4>
                            <button v-if="canCreateOrder" type="button" class="btn btn-sm btn-outline-primary font-weight-bold" @click="openModalCreate(activeCategory)">
                                Thêm mới {{ activeCategoryLabel.toLowerCase() }}
                            </button>
                        </div>
                        <HimotoErrorState v-if="categoryError" title="Không thể tải danh sách theo loại" :message="categoryError" @retry="getCategoryList" />
                        <HimotoTableSkeleton v-else-if="categoryLoading" :rows="4" :columns="9" />
                        <RentalOrderTable v-else :orders="categoryOrders" :categories="categories" :label="'Danh sách ' + activeCategoryLabel.toLowerCase()"
                            :empty-message="'Chưa có ' + activeCategoryLabel.toLowerCase() + ' phù hợp với bộ lọc.'"
                            :can-delete="canDeleteOrder" @delete="deleteOrder"
                            @edit="openUpdateModal" @view="openShowOrder" @print="printOrderContract" @reminder="openCustomerReminder" />
                        <div v-if="!categoryLoading && !categoryError && categoryLastPage > 1" class="d-flex justify-content-center mt-3">
                            <paginate v-model="categoryPage" :page-count="categoryLastPage" :page-range="3" :margin-pages="1"
                                :click-handler="clickCategoryPage" :prev-text="'Trước'" :next-text="'Sau'"
                                :container-class="'pagination b-pagination'" :pageLinkClass="'page-link'"
                                :next-link-class="'next-link-item'" :prev-link-class="'prev-link-item'" :prev-class="'page-link'"
                                :next-class="'page-link'" :page-class="'page-item'" />
                        </div>
                    </section>

                    <section class="contract-master-list" aria-label="Danh sách tổng hợp đồng">
                        <div class="rental-master-heading">
                            <h4>Danh sách đơn tổng</h4>
                            <span>Tất cả loại hồ sơ theo bộ lọc hiện tại</span>
                        </div>
                        <HimotoErrorState v-if="errorMessage" title="Không thể tải danh sách tổng" :message="errorMessage" @retry="getList" />
                        <HimotoTableSkeleton v-else-if="loading" :rows="6" :columns="10" />
                        <RentalOrderTable v-else :orders="orders" :categories="categories" label="Danh sách đơn tổng" show-category
                            :selectable="canDeleteOrder" :can-delete="canDeleteOrder" :selected="checkedItems"
                            @select="selectOrder" @select-all="toggleSelectAll" @delete="deleteOrder"
                            @edit="openUpdateModal" @view="openShowOrder" @print="printOrderContract" @reminder="openCustomerReminder" />
                    <div class="edu-paginate mx-auto text-center" v-if="!loading && !errorMessage && orders.length">
                        <paginate v-model="page" :page-count="last_page" :page-range="3" :margin-pages="1"
                            :click-handler="clickCallback" :prev-text="'Trước'" :next-text="'Sau'"
                            :container-class="'pagination b-pagination'" :pageLinkClass="'page-link'"
                            :next-link-class="'next-link-item'" :prev-link-class="'prev-link-item'" :prev-class="'page-link'"
                            :next-class="'page-link'" :page-class="'page-item'">
                        </paginate>
                    </div>
                    </section>
                </div>
            </div>

            <b-modal v-model="showModalCreate" :title="modalCreateTitle" size="xl" modal-class="contract-modal-wide" ref="modal-contract-create" :centered="true" :scrollable="true"
                hide-footer>
                <order-update v-if="showModalCreate" :initial-mode="createMode" :initial-data="createInitialData" :print-after-create="createAndPrint" @createSuccess="createSuccess"></order-update>
            </b-modal>
            <b-modal v-model="showModalUpdate" :title='"Sửa hợp đồng  " + orderId' size="xl" modal-class="contract-modal-wide" ref="modal-contract-update" :centered="true"
                :scrollable="true" hide-footer>
                <order-update v-if="showModalUpdate" :id="orderId" @updateSuccess="updateSuccess"></order-update>
            </b-modal>
            <b-modal v-model="showModalDetail" :title='"Xem hợp đồng  " + orderId' size="xl" modal-class="contract-modal-wide" ref="modal-contract-show" :centered="true"
                :scrollable="true">
                <order-show v-if="showModalDetail" :order="order_show"></order-show>
            </b-modal>
            <b-modal v-model="showModalPayment" title="Thu chi hợp đồng" size="xl" ref="modal-contract-payment" :centered="true" :scrollable="true"
                hide-footer>
                <order-payment v-if="showModalPayment" :id="orderId" :order-status="order_status_prop" :suggested-amount="paymentSuggestedAmount"
                    @paymentSuccess="paymentSuccess" @updateSuccess="paymentSuccess"></order-payment>
            </b-modal>
            <ModalContractPreview v-if="showPrintModal" v-model="showPrintModal" :doc="printDocumentDto" />
        </div>
    </div>
</template>

<script>
import moment from 'moment';
import { LEAD_UNIQUE_USERS } from "@/core/services/store/lead.module";
import { SET_BREADCRUMB } from "@/core/services/store/breadcrumbs.module";
import { EXPORT_ORDERS } from "@/core/services/store/exports.module";
import { mapGetters } from "vuex";
import { SHOW_ORDER_CAR_RENTAL, GET_ORDER_CAR_RENTAL, GET_ORDER_CAR_RENTAL_REPORT, DELETE_ORDER, GET_ORDER_DOCUMENT } from "@/core/services/store/order.module";
import { REPORT_CAR_RENTAL_NEW } from '../../../core/services/store/report.module';
import OrderQuickCreate from "./components-order/OrderQuickCreate";
import RentalOrderTable from "./components-order/RentalOrderTable";
import { STORE_GET_ALL } from "@/core/services/store/store.module";
import { ORDER_STATUS } from "@/option/orderOption";
import { ORDER_STATUS_DEFINE, ORDER_STATUS_DEFINE_CSS, STATUS_COMPLETED, ORDER_OUTDATE_FILTERS } from "../../../option/orderOption";
import { getTextShort } from '../../../utils';
import queryMixin from '@/utils/queryMixin.js';
import HimotoTableSkeleton from "@/view/components/himoto/HimotoTableSkeleton.vue";
import HimotoErrorState from "@/view/components/himoto/HimotoErrorState.vue";
import { normalizePaginator } from "@/utils/paginatorAdapter";
import { getApiMessage } from "@/utils/apiErrorHandler";

const OrderUpdate = () => import(/* webpackChunkName: "contract-form" */ "./components-order/OrderUpdate");
const OrderShow = () => import(/* webpackChunkName: "contract-detail" */ "./components-order/OrderShow");
const OrderPayment = () => import(/* webpackChunkName: "contract-payment" */ "./components-order/OrderPayment");
const ModalContractPreview = () => import(/* webpackChunkName: "contract-print" */ "./components-order/ModalContractPreview");

export default {
    name: "OrderCarRental",
    mixins: [queryMixin],
    data() {
        const { page, store_id, order_mode, open_order, open_payment, payment_amount, ...restQuery } = this.$route?.query || {};
        return {
            categories: [
                { value: 'standard', label: 'Đơn thuê xe phổ thông' },
                { value: 'draft', label: 'Đơn thuê xe nháp' },
                { value: 'handover', label: 'Hợp đồng 50cc' },
            ],
            activeCategory: ['standard', 'draft', 'handover'].includes(order_mode) ? order_mode : 'standard',
            categoryOrders: [],
            categoryPage: 1,
            categoryLastPage: 1,
            categoryLoading: false,
            categoryError: null,
            categoryRequestId: 0,
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
            showModalUpdate: false,
            showModalDetail: false,
            showModalPayment: false,
            createMode: 'standard',
            createInitialData: {},
            createAndPrint: false,
            modalCreateTitle: 'Tạo <Hợp đồng phổ thông>',
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
            paymentSuggestedAmount: 0,
            lastFetchedAt: 0,
            showPrintModal: false,
            printDocumentDto: null,
            pendingOpenOrderId: open_order ? Number(open_order) : null,
            pendingOpenPaymentId: open_payment ? Number(open_payment) : null,
            isFirstActivated: true,
        }
    },
    components: {
        OrderShow,
        OrderUpdate,
        OrderQuickCreate,
        RentalOrderTable,
        OrderPayment,
        ModalContractPreview,
        HimotoTableSkeleton,
        HimotoErrorState
    },

    computed: {


        ...mapGetters(["currentUser", "capabilities"]),
        canCreateOrder() {
            return this.capabilities.includes('*') || this.capabilities.includes('order.create');
        },
        canDeleteOrder() {
            return this.capabilities.includes('*') || this.capabilities.includes('order.delete');
        },
        canReportStore() {
            return this.capabilities.includes('*') || this.capabilities.includes('order.report_store');
        },
        canManageLeads() {
            return this.capabilities.includes('*') || this.capabilities.includes('lead.manage');
        },
        activeCategoryLabel() {
            return this.categoryLabel(this.activeCategory);
        },
        rentalWarehouseLink() {
            const storeId = this.query.store_id;
            return {
                name: 'warehouse',
                query: storeId ? { store_id: storeId } : {},
            };
        },
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
        if (this.pendingOpenPaymentId) {
            this.$nextTick(() => this.openPaymentModal(
                { id: this.pendingOpenPaymentId, order_status: 'renting' },
                Number(this.$route.query.payment_amount || 0)
            ));
        } else if (this.pendingOpenOrderId) {
            this.$nextTick(() => this.openShowOrder({ id: this.pendingOpenOrderId }));
        }
    },
    watch: {
        "$route.query.order_mode"(mode) {
            if (this.categories.some(category => category.value === mode) && mode !== this.activeCategory) {
                this.activeCategory = mode;
                this.categoryPage = 1;
                this.getCategoryList();
            }
        },
        "$route.query.open_order"(value) {
            const orderId = Number(value || 0);
            if (orderId && orderId !== Number(this.order_show?.id || 0)) {
                this.openShowOrder({ id: orderId });
            }
        },
        "$route.query.open_payment"(value) {
            const orderId = Number(value || 0);
            if (orderId) {
                this.openPaymentModal(
                    { id: orderId, order_status: 'renting' },
                    Number(this.$route.query.payment_amount || 0)
                );
            }
        },
    },
    activated() {
        if (this.isFirstActivated) {
            this.isFirstActivated = false;
            return;
        }
        const queryPage = +this.$route?.query?.page || 1;
        const queryKeyword = this.$route?.query?.keyword || '';
        const queryChanged = queryPage !== this.page || queryKeyword !== (this.query.keyword || '');
        if (queryChanged) {
            this.page = queryPage;
            this.query.keyword = queryKeyword;
        }
        if (!queryChanged && this.lastFetchedAt && Date.now() - this.lastFetchedAt < 60000) {
            return;
        }
        this.getList();
        this.getReport();
    },
    methods: {
        categoryLabel(mode) {
            const category = this.categories.find(item => item.value === mode);
            return category ? category.label : 'Hợp đồng phổ thông';
        },
        setCategory(mode) {
            if (mode === this.activeCategory) return;
            this.activeCategory = mode;
            this.categoryPage = 1;
            this.pushParamsUrl();
            this.getCategoryList();
        },
        clickCategoryPage(page) {
            this.categoryPage = page;
            this.getCategoryList();
        },
        getCategoryList() {
            const requestId = ++this.categoryRequestId;
            this.categoryLoading = true;
            this.categoryError = null;
            this.$store.dispatch(GET_ORDER_CAR_RENTAL, {
                ...this.query,
                order_mode: this.activeCategory,
                page: this.categoryPage,
            }).then((data) => {
                if (requestId !== this.categoryRequestId) return;
                const paginated = normalizePaginator(data);
                this.categoryOrders = paginated.items || [];
                this.categoryLastPage = paginated.lastPage || 1;
            }).catch((err) => {
                if (requestId === this.categoryRequestId) this.categoryError = getApiMessage(err);
            }).finally(() => {
                if (requestId === this.categoryRequestId) this.categoryLoading = false;
            });
        },
        setTodayFilter(filter) {
            this.query.today_filter = filter;
            this.page = 1;
            this.categoryPage = 1;
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
            if (!this.canManageLeads) return;
            this.$store.dispatch(LEAD_UNIQUE_USERS, {}).then((data) => {
                this.sources = data?.data || [];
            }).catch(() => {});
        },
        toggleSelectAll(checked) {
            for (const item of this.orders) this.$set(this.checkedItems, item.id, checked);
            this.selectAll = checked;
        },
        selectOrder({ id, checked }) {
            this.$set(this.checkedItems, id, checked);
        },

        getDesc(str) {
            return getTextShort(str);
        },
        getList() {
            this.getCategoryList();
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
            const p2 = this.canReportStore ? this.$store.dispatch(REPORT_CAR_RENTAL_NEW, this.query).then((data) => {
                this.money_stats = data?.data || {};
            }).catch(() => {}) : Promise.resolve();
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
            this.categoryPage = 1;
            this.pushParamsUrl();
            this.getList();
            this.getReport();
        },
        pushParamsUrl() {
            this.$router.push({
                path: '',
                query: {
                    page: this.page,
                    ...this.query,
                    order_mode: this.activeCategory,
                }
            }).catch(() => {});
        },
        filterOverdueOnly() {
            this.query.is_out_of_date = 1;
            this.search();
        },
        openCustomerReminder(item) {
            const searchVal = item.customer_phone || item.contract_number || ('#' + item.id);
            this.$router.push({
                path: '/customer-reminders',
                query: {
                    search: searchVal,
                    store_id: item.store_id || undefined,
                }
            });
        },
        openQuickCreate(data) {
            this.openModalCreate(this.activeCategory, data, true);
        },
        openModalCreate(mode = this.activeCategory, initialData = {}, print = false) {
            if (!this.canCreateOrder) return;
            this.createMode = mode;
            this.createInitialData = initialData;
            this.createAndPrint = print;
            if (mode === 'draft') {
                this.modalCreateTitle = 'Tạo hợp đồng nháp';
            } else if (mode === 'handover') {
                this.modalCreateTitle = 'Tạo hồ sơ 50cc / Biên bản bàn giao xe';
            } else {
                this.modalCreateTitle = 'Tạo hợp đồng phổ thông';
            }
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
        openPaymentModal(order, suggestedAmount = 0) {
            this.orderId = order.id;
            this.order_status_prop = order.order_status;
            this.paymentSuggestedAmount = Number(suggestedAmount || 0);
            this.$refs['modal-contract-payment'].show();
        },
        async createSuccess(result = {}) {
            this.showModalCreate = false;
            this.$refs['modal-contract-create'].hide();
            if (this.$refs.quickCreate) {
                this.$refs.quickCreate.reset();
                this.$refs.quickCreate.loadVehicles();
            }
            this.getList();
            this.getReport();
            if (result.print && result.id) await this.printOrderContract({ id: result.id });
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
.contract-category-list { border: 1px solid #cbdce7; border-radius: 10px; padding: 0 18px 18px; margin: 0 0 28px; background: #fff; }
.contract-category-tabs { gap: 0; overflow-x: auto; flex-wrap: nowrap; margin: 0 -18px; background: #e8f0f5; border-radius: 10px 10px 0 0; }
.contract-category-tabs .nav-link { flex: 0 0 auto; border: 0; background: transparent; color: #38586e; font-weight: 700; padding: 15px 18px; text-transform: uppercase; }
.contract-category-tabs .nav-link.active { background: #1b4a67; color: #fff; }
.contract-category-tabs .nav-link:focus-visible { outline: 3px solid #257bb5; outline-offset: -3px; }
.contract-category-actions { white-space: nowrap; }
.contract-master-list { border: 1px solid #cbdce7; border-radius: 10px; padding: 0 18px 18px; margin-top: 24px; background: #fff; }
.rental-list-heading { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin: 20px 0 14px; }
.rental-list-heading h4 { margin: 0; font-size: 15px; font-weight: 700; text-transform: uppercase; color: #243e50; }
.rental-master-heading { display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 8px; padding: 17px 18px; margin: 0 -18px 18px; background: #1b4a67; color: #fff; border-radius: 10px 10px 0 0; }
.rental-master-heading h4 { margin: 0; font-size: 20px; font-weight: 700; text-transform: uppercase; }
.rental-master-heading span { font-size: 12px; }
.rental-filters { padding: 14px 18px; border: 1px solid #e5e9f0; border-radius: 8px; background: #fff; }
.rental-filters summary { cursor: pointer; color: #38586e; font-weight: 600; }
.rental-filters summary:focus-visible { outline: 3px solid #257bb5; outline-offset: 3px; }
@media (max-width: 991px) { .contract-overview { grid-template-columns: 1fr; } }
@media (max-width: 575px) { .contract-category-list, .contract-master-list { padding-left: 12px; padding-right: 12px; } .contract-category-tabs, .rental-master-heading { margin-left: -12px; margin-right: -12px; } .contract-category-tabs .nav-link { font-size: 12px; padding: 14px 12px; } }
</style>
