<template>
    <div class="report-car-rental">
        <div class="card card-custom gutter-b">
            <div class="card-header">
                <div class="card-title">
                    <h3 class="card-label">Tổng quan báo cáo thuê xe</h3>
                </div>
                <div class="card-title">
                    <button
                        type="button"
                        class="btn btn-success"
                        :class="{ 'spinner spinner-white spinner-right': is_exporting }"
                        :disabled="is_exporting"
                        @click="exportFile"
                    >
                        <i v-if="!is_exporting" class="fa fa-file-excel mr-2"></i>
                        {{ is_exporting ? 'Đang xuất...' : 'Xuất Excel' }}
                    </button>
                </div>

            </div>
            <div class="card-body">
                <!-- BỘ CHỌN NGÀY VÀ BỘ LỌC ĐA NĂNG -->
                <div class="filter-wrapper bg-light p-4 rounded mb-6 border">
                    <div class="d-flex align-items-center justify-content-between flex-wrap mb-3">
                        <div class="d-flex align-items-center flex-wrap">
                            <span class="font-weight-bolder text-dark mr-3 mb-2">
                                <i class="fa fa-filter text-primary mr-1"></i> Lựa chọn ngày:
                            </span>
                            <div class="btn-group mr-3 mb-2" role="group">
                                <button 
                                    type="button" 
                                    class="btn btn-sm" 
                                    :class="dateMode === 'single' ? 'btn-primary font-weight-bolder' : 'btn-white border text-dark'" 
                                    @click="switchDateMode('single')"
                                >
                                    <i class="fa fa-calendar-day mr-1"></i> 1. Lựa chọn từng ngày (lẻ 1 ngày)
                                </button>
                                <button 
                                    type="button" 
                                    class="btn btn-sm" 
                                    :class="dateMode === 'range' ? 'btn-primary font-weight-bolder' : 'btn-white border text-dark'" 
                                    @click="switchDateMode('range')"
                                >
                                    <i class="fa fa-calendar-alt mr-1"></i> 2. Lựa chọn khoảng ngày
                                </button>
                            </div>
                            <button 
                                type="button" 
                                class="btn btn-sm btn-warning font-weight-bolder mb-2 shadow-sm" 
                                @click="selectToday" 
                                title="Xem nhanh báo cáo hôm nay"
                            >
                                Hôm nay
                            </button>
                        </div>
                        <div v-if="activeDateDisplay" class="mb-2">
                            <span class="badge badge-light-primary font-size-sm p-2">
                                <i class="fa fa-clock mr-1"></i> Đang xem: <strong>{{ activeDateDisplay }}</strong>
                            </span>
                        </div>
                    </div>

                    <div class="row align-items-start filter-row">
                        <!-- CHẾ ĐỘ 1: CHỌN LẺ 1 NGÀY -->
                        <div v-if="dateMode === 'single'" class="col-lg-4 col-md-4 mb-3">
                            <label class="filter-label">
                                <i class="flaticon2-calendar-9 text-primary mr-1"></i> Chọn ngày cần xem:
                            </label>
                            <el-date-picker 
                                v-model="singleDate" 
                                type="date" 
                                format="dd-MM-yyyy" 
                                value-format="yyyy-MM-dd" 
                                placeholder="Chọn ngày cụ thể" 
                                class="w-100"
                                :clearable="false"
                                @change="onSingleDateChange"
                                @keyup.enter.native="confirmAndSearch"
                            ></el-date-picker>
                        </div>

                        <!-- CHẾ ĐỘ 2: CHỌN KHOẢNG NGÀY -->
                        <div v-else class="col-lg-6 col-md-5 mb-3">
                            <label class="filter-label">
                                <i class="flaticon2-calendar-8 text-primary mr-1"></i> Khoảng ngày (Từ ngày ~ Đến ngày):
                            </label>
                            <div class="d-flex align-items-center">
                                <el-date-picker 
                                    v-model="rangeStartDate" 
                                    type="date" 
                                    format="dd-MM-yyyy" 
                                    value-format="yyyy-MM-dd" 
                                    placeholder="Từ ngày" 
                                    style="flex: 1; min-width: 0;"
                                    :clearable="false"
                                    @keyup.enter.native="confirmAndSearch"
                                ></el-date-picker>
                                <span class="mx-2 font-weight-bolder text-muted font-size-lg">~</span>
                                <el-date-picker 
                                    v-model="rangeEndDate" 
                                    type="date" 
                                    format="dd-MM-yyyy" 
                                    value-format="yyyy-MM-dd" 
                                    placeholder="Đến ngày" 
                                    style="flex: 1; min-width: 0;"
                                    :clearable="false"
                                    @keyup.enter.native="confirmAndSearch"
                                ></el-date-picker>
                            </div>
                        </div>

                        <!-- LỌC THEO CỬA HÀNG -->
                        <div :class="dateMode === 'single' ? 'col-lg-6 col-md-5 mb-3' : 'col-lg-4 col-md-4 mb-3'">
                            <label class="filter-label">
                                Cửa hàng:
                            </label>
                            <el-select filterable class="w-100" placeholder="Toàn hệ thống (Tất cả)" v-model="query.store_id" clearable @change="search">
                                <el-option v-for="item in stores" :key="item.id" :label="item.store_name" :value="item.id">
                                    <span>{{ item.store_name }}</span>
                                </el-option>
                            </el-select>
                        </div>

                        <!-- NÚT TÌM KIẾM (CẢ 2 CHẾ ĐỘ ĐỀU CÓ Ở BÊN PHẢI NGOÀI CÙNG) -->
                        <div class="col-lg-2 col-md-3 mb-3">
                            <label class="filter-label filter-label-spacer" aria-hidden="true">&nbsp;</label>
                            <button 
                                type="button"
                                class="btn btn-primary font-weight-bolder w-100 btn-search" 
                                :class="{ 'spinner spinner-white spinner-right': is_loading_search }" 
                                :disabled="is_loading_search"
                                @click="confirmAndSearch"
                            >
                                <i class="fa fa-search mr-1" v-if="!is_loading_search"></i> Tìm kiếm
                            </button>
                        </div>
                    </div>
                </div>

                <!-- BẢNG TỔNG QUAN THEO BỘ LỌC -->
                <div class="example mb-10">
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap">
                        <h4 class="section-title my-0">
                            <i class="fa fa-chart-bar text-primary mr-1"></i>
                            {{ selectedStoreName ? `Tổng quan cửa hàng: ${selectedStoreName}` : 'Tất cả cửa hàng' }}
                        </h4>
                        <button
                            type="button" 
                            class="btn btn-sm btn-outline-primary font-weight-bolder shadow-sm"
                            @click="viewSelectedStoreDetail"
                        >
                            <i class="fa fa-chevron-down mr-1 text-primary"></i>
                            Xem chi tiết cửa hàng
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" v-if="reports_all_stores">
                            <thead class="thead-light">
                                <tr>
                                    <th scope="col">
                                        Tổng thu thực tế
                                    </th>
                                    <th scope="col">
                                        Tổng chi thực tế
                                    </th>
                                    <th scope="col">
                                        Tổng thu cọc
                                    </th>
                                    <th scope="col">
                                        Tổng thu gia hạn
                                    </th>
                                    <th scope="col">
                                        Tổng thu phí thuê
                                    </th>
                                    <th scope="col">
                                        Tổng trả sớm
                                    </th>
                                    <th scope="col">
                                        Tổng phạt muộn
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="total-highlight">
                                    <td>
                                        {{ totalInForAllStores | formatPrice }}
                                    </td>
                                    <td>
                                        {{ reports_all_stores.total_real_refund | formatPrice }}
                                    </td>
                                    <td>
                                        {{ reports_all_stores.total_deposit | formatPrice }}
                                    </td>
                                    <td>
                                        {{ reports_all_stores.total_renew | formatPrice }}
                                    </td>
                                    <td>
                                        {{ reports_all_stores.total_rental_fees | formatPrice }}
                                    </td>
                                    <td>
                                        {{ Math.abs(reports_all_stores.total_money_early) | formatPrice }}
                                    </td>
                                    <td>
                                        {{ reports_all_stores.total_money_out_date | formatPrice }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                </div>
                    <h4 id="store-details" class="section-title">Từng cửa hàng</h4>

					<div
                        class="example-preview table-responsive store-detail-section"
                        v-for="(report, key) in reports"
                        :key="key"
                        :id="`store-report-${report.store.id}`"
                    >
                        <el-collapse v-model="active_store_collapses[key]" accordion @change="onCollapseChanged($event, report.store.id)">
                            <el-collapse-item name="1">
                               
								<template slot="title">
                                    <p class="font-weight-bold mb-0">
                                        Cửa hàng: {{ report.store.store_name }}
                                        <i
                                            v-if="store_detail_loading[report.store.id]"
                                            class="fa fa-spinner fa-spin text-primary ml-2"
                                            title="Đang tải chi tiết"
                                        ></i>
                                    </p>
                                </template>

								<table v-if="!store_detail_loading[report.store.id]" class="table">
                                    <thead>
                                        <tr>
                                            <th scope="col">Ngày</th>
                                            <th scope="col">Tổng thu thực tế</th>
                                            <th scope="col">Tổng chi thực tế</th>
											<th scope="col">Tổng thu cọc</th>
                                            <th scope="col">Tổng thu gia hạn</th>
                                            <th scope="col">Tổng thu phí thuê</th>
                                            <th scope="col">Tổng trả sớm</th>
                                            <th scope="col">Tổng phạt muộn</th>
                                        </tr>
                                    </thead>
									<tbody>
                                        <tr class="total-highlight">
                                            <td>Tổng</td>
                                            <td>{{ calcTotalReportValue(report.data, 'real_in') | formatPrice }}</td>
                                            <td>{{ calcTotalReportValue(report.data, 'total_real_refund') | formatPrice }}</td>
                                            <td>{{ calcTotalReportValue(report.data, 'total_deposit') | formatPrice }}</td>
                                            <td>{{ calcTotalReportValue(report.data, 'total_renew') | formatPrice }}</td>
                                            <td>{{ calcTotalReportValue(report.data, 'total_rental_fees') | formatPrice }}</td>
                                            <td>{{ calcTotalReportValue(report.data, 'total_money_early') | formatPrice }}</td>
                                            <td>{{ calcTotalReportValue(report.data, 'total_money_out_date') | formatPrice }}</td>
                                        </tr>

                                        <tr v-if="report" v-for="(item, index) in getLongestData(report.data)" :key="index">
											<td>{{ item.date }}</td>
											<td>{{ totalRealIn(item.date, report.data) | formatPrice }}</td>
                                            <td>{{ getDetailReportValue(item.date, report.data, 'total_real_refund') | formatPrice }}</td>
											<td>{{ getDetailReportValue(item.date, report.data, 'total_deposit') | formatPrice }}</td>
                                            <td>{{ getDetailReportValue(item.date, report.data, 'total_renew') | formatPrice }}</td>
                                            <td>{{ getDetailReportValue(item.date, report.data, 'total_rental_fees') | formatPrice }}</td>
                                            <td>{{ getDetailReportValue(item.date, report.data, 'total_money_early') | formatPrice }}</td>
                                            <td>{{ getDetailReportValue(item.date, report.data, 'total_money_out_date') | formatPrice }}</td>
                                        </tr>
                                    </tbody>
                                </table>

                                <div v-else class="text-center text-muted py-8">
                                    <i class="fa fa-spinner fa-spin text-primary mr-2"></i>
                                    Đang tải dữ liệu chi tiết...
                                </div>
							</el-collapse-item>
                        </el-collapse>
                    </div>

                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { STORE_GET_ALL } from "../../../core/services/store/store.module";
import { EXPORT_GENERAL_REPORT } from "../../../core/services/store/exports.module";
import { REPORT_CAR_RENTAL_NEW, REPORT_CAR_RENTAL_DAY_BY_DAY } from "../../../core/services/store/report.module";
import moment from "moment-timezone";
import { SET_BREADCRUMB } from "@/core/services/store/breadcrumbs.module";
import queryMixin from '@/utils/queryMixin.js';

const _ = require("lodash");

export default {
    mixins: [queryMixin],
    name: "ReportCardRental",
    data() {
        const { store_id, ...restQuery } = this.$route?.query || {};
        return {
            moment: moment,
            is_loading_search: false,
            is_exporting: false,
            reports: {},
            reports_all_stores: {},
            store_detail_loading: {},
            active_store_collapses: {},
            stores: [],
            dateMode: 'range', // 'single' | 'range'
            singleDate: moment().format("YYYY-MM-DD"),
            rangeStartDate: moment().startOf("month").format("YYYY-MM-DD"),
            rangeEndDate: moment().format("YYYY-MM-DD"),
            query: {
                store_id: store_id ? +store_id : "",
                dates: [],
                ...(restQuery || {}),
            },
			collapse_opened_ids: [],
        };
    },
	computed: {
		totalInForAllStores() {
			const dep = parseInt(this.reports_all_stores && this.reports_all_stores.total_deposit) || 0;
			const ren = parseInt(this.reports_all_stores && this.reports_all_stores.total_renew) || 0;
			const fee = parseInt(this.reports_all_stores && this.reports_all_stores.total_rental_fees) || 0;
			return dep + ren + fee;
		},
        activeDateDisplay() {
            if (this.query.dates && this.query.dates.length === 2) {
                if (this.query.dates[0] === this.query.dates[1]) {
                    return `Ngày ${this.moment(this.query.dates[0]).format('DD/MM/YYYY')}`;
                }
                return `Từ ${this.moment(this.query.dates[0]).format('DD/MM/YYYY')} đến ${this.moment(this.query.dates[1]).format('DD/MM/YYYY')}`;
            }
            return '';
        },
        selectedStoreName() {
            const store_id = parseInt(this.query.store_id);
            const store = this.stores.find(item => parseInt(item.id) === store_id);
            return store ? store.store_name : '';
        },
	},
    mounted() {
        this.$store.dispatch(SET_BREADCRUMB, [{ title: "Báo cáo" }]);
        this.pullParamsUrl();
        this.getDateDefault();
        this.getStore();
        this.report();
    },
    methods: {
		calcTotalReportValue(data, field) {
			if ('real_in' == field) {
				let total_deposit = 0;
				let total_renew = 0;
				let total_rental_fees = 0;
				
				if (data.total_deposit) {
					total_deposit = data.total_deposit.reduce(
						(sum, item) => {
							let val = 0;
							if (parseInt(item.total_value)) {
								val = parseInt(item.total_value);
							}
							return sum + val;
						}, 0 
					);
				}
				if (data.total_renew) {
					total_renew = data.total_renew.reduce(
						(sum, item) => {
							let val = 0;
							if (parseInt(item.total_value)) {
								val = parseInt(item.total_value);
							}
							return sum + val;
						}, 0 
					);
				}
				if (data.total_rental_fees) {
					total_rental_fees = data.total_rental_fees.reduce(
						(sum, item) => {
							let val = 0;
							if (parseInt(item.total_value)) {
								val = parseInt(item.total_value);
							}
							return sum + val;
						}, 0 
					);
				}
				
				return total_deposit + total_renew + total_rental_fees;
			} else {
				let data_by_keys = data[field];
				if (data_by_keys) {
					let total = data_by_keys.reduce(
						(sum, item) => {
							let val = 0;
							if (parseInt(item.total_value)) {
								val = parseInt(item.total_value);
							}
							return sum + val;
						}, 0 
					);
					if (total) {
						return total;
					}
				}
			}
			return 0;
		},
		totalRealIn(date, data) {
			let total_deposit = this.getDetailReportValue(date, data, 'total_deposit');
			let total_renew = this.getDetailReportValue(date, data, 'total_renew');
			let total_rental_fees = this.getDetailReportValue(date, data, 'total_rental_fees');

			return parseInt(total_deposit) + parseInt(total_renew) + parseInt(total_rental_fees);
		},
		getDetailReportValue(date, data, field ) {
			let rows = data[field];
			if (rows) {
				let row = rows.find((item) => item.date === date);
				if ( row && row.total_value ) {
					return Math.abs(row.total_value);
				}
			}
			return 0;
		},
		getLongestData(store_report) {
			if ( store_report ) {
				const dateMap = {};
				const keys = Object.keys(store_report);
				for (const key of keys) {
					let values = store_report[key];
					if (Array.isArray(values)) {
						values.forEach(v => {
							if (v && v.date) {
								dateMap[v.date] = true;
							}
						});
					}
				}
				const dates = Object.keys(dateMap).sort().reverse();
				return dates.map(d => ({ date: d }));
			}
			return [];
		},
		async onCollapseChanged(is_opened, store_id) {
            store_id = parseInt(store_id);
			if (is_opened) {
				const index = this.collapse_opened_ids.indexOf(store_id);
				if (index === -1) {
					this.collapse_opened_ids.push(store_id);
				}

                const params = JSON.parse(JSON.stringify(this.query));
                params.store_id = store_id;
                await this.getStoreDetailReport(params, store_id);
			} else {
				const index = this.collapse_opened_ids.indexOf(store_id);
				if (index !== -1) {
					this.collapse_opened_ids.splice(index, 1);
				}
			}
		},
        switchDateMode(mode) {
            this.dateMode = mode;
            if (mode === 'single') {
                if (!this.singleDate) {
                    this.singleDate = this.moment().format("YYYY-MM-DD");
                }
                this.query.dates = [this.singleDate, this.singleDate];
                this.search();
            } else {
                if (!this.rangeStartDate || !this.rangeEndDate) {
                    this.rangeStartDate = this.moment().startOf("month").format("YYYY-MM-DD");
                    this.rangeEndDate = this.moment().format("YYYY-MM-DD");
                }
                this.query.dates = [this.rangeStartDate, this.rangeEndDate];
                this.search();
            }
        },
        selectToday() {
            const today = this.moment().format("YYYY-MM-DD");
            this.dateMode = 'single';
            this.singleDate = today;
            this.rangeStartDate = today;
            this.rangeEndDate = today;
            this.query.dates = [today, today];
            this.search();
        },
        onSingleDateChange(val) {
            if (val) {
                this.singleDate = val;
                this.query.dates = [val, val];
                this.search();
            }
        },
        confirmAndSearch() {
            if (this.dateMode === 'single') {
                if (!this.singleDate) {
                    if (this.$message && this.$message.warning) {
                        this.$message.warning('Vui lòng chọn ngày cần xem');
                    }
                    return;
                }
                this.query.dates = [this.singleDate, this.singleDate];
            } else {
                if (!this.rangeStartDate || !this.rangeEndDate) {
                    if (this.$message && this.$message.warning) {
                        this.$message.warning('Vui lòng chọn đầy đủ từ ngày và đến ngày');
                    }
                    return;
                }
                if (this.rangeStartDate > this.rangeEndDate) {
                    if (this.$message && this.$message.warning) {
                        this.$message.warning('Ngày kết thúc phải từ ngày bắt đầu trở đi');
                    }
                    return;
                }
                this.query.dates = [this.rangeStartDate, this.rangeEndDate];
            }
            this.search();
        },
        async viewSelectedStoreDetail() {
            const store_id = parseInt(this.query.store_id);
            if (!store_id) {
                if (this.$message && this.$message.warning) {
                    this.$message.warning('Vui lòng chọn cửa hàng trước khi xem chi tiết');
                }
                return;
            }

            const report_key = `store_${store_id}`;
            if (!this.reports[report_key]) {
                if (this.$message && this.$message.warning) {
                    this.$message.warning('Không tìm thấy cửa hàng đã chọn');
                }
                return;
            }

            this.$set(this.active_store_collapses, report_key, '1');
            if (this.collapse_opened_ids.indexOf(store_id) === -1) {
                this.collapse_opened_ids.push(store_id);
            }

            const params = JSON.parse(JSON.stringify(this.query));
            params.store_id = store_id;
            const detail_request = this.getStoreDetailReport(params, store_id);

            this.$nextTick(() => {
                const store_section = document.getElementById(`store-report-${store_id}`);
                if (store_section) {
                    store_section.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });

            await detail_request;
        },
        getDateDefault() {
            const start = this.moment().startOf("month").format("YYYY-MM-DD");
            const end = this.moment().format("YYYY-MM-DD");
            this.rangeStartDate = start;
            this.rangeEndDate = end;
            this.singleDate = end;
            this.query.dates = [start, end];
        },
        async search() {
            this.is_loading_search = true;
            try {
                await this.report();
                if (this.collapse_opened_ids && this.collapse_opened_ids.length > 0) {
                    let params = JSON.parse(JSON.stringify(this.query));
                    for (let store_id of this.collapse_opened_ids) {
                        params['store_id'] = store_id;
                        await this.getStoreDetailReport(params, store_id);
                    }
                }
            } finally {
                this.is_loading_search = false;
            }
        },

		async getStoreDetailReport(params, store_id) {
            store_id = parseInt(store_id);
            if (this.store_detail_loading[store_id]) {
                return;
            }

            this.$set(this.store_detail_loading, store_id, true);
            try {
                const data = await this.$store.dispatch(REPORT_CAR_RENTAL_DAY_BY_DAY, params);
                const report_key = `store_${store_id}`;
                if (!this.reports[report_key]) {
                    const store = this.stores.find(item => parseInt(item.id) === store_id);
                    this.$set(this.reports, report_key, { store, data: [] });
                }
                this.$set(this.reports[report_key], 'data', data.data);
            } catch (error) {
                this.noticeMessage('error', 'Thất bại', 'Không thể tải chi tiết cửa hàng');
            } finally {
                this.$set(this.store_detail_loading, store_id, false);
            }
		},

        report() {
            return this.$store.dispatch(REPORT_CAR_RENTAL_NEW, this.query).then((data) => {
				this.reports_all_stores = data.data;
            });
        },
        calcProfit(items) {
            return _.reduce(
                items,
                function (result, item, key) {
                    result += item.profit;
                    return result;
                },
                0,
            );
        },
        calcProfitAddon(items) {
            return _.reduce(
                items,
                function (result, item, key) {
                    result += item.profit_addon;
                    return result;
                },
                0,
            );
        },
        calcProfitHiringFee(items) {
            return _.reduce(
                items,
                function (result, item, key) {
                    result += item.profit_hiring_fee;
                    return result;
                },
                0,
            );
        },
        calcTotalAmountIn(items) {
            return _.reduce(
                items,
                function (result, item, key) {
                    result += item.in ? item.in : 0;
                    return result;
                },
                0,
            );
        },
        calcTotalAmountOut(items) {
            return _.reduce(
                items,
                function (result, item, key) {
                    result += item.out ? item.out : 0;
                    return result;
                },
                0,
            );
        },
        pushParamsUrl() {
            this.$router.push({
                path: "",
                query: {
                    page: this.page,
                    ...this.query,
                },
            });
        },
        pullParamsUrl() {
            // this.query.dates = this.$router.query.dates ? this.$router.query.dates : [];
            // this.query.store_id = this.$router.query.store_id ? this.$router.query.store_id : null;
        },
        getStore() {
            this.$store.dispatch(STORE_GET_ALL, {}).then((res) => {
                this.stores = res.data;

				let report_data = {};
				res.data.forEach((store) => {
					if ( store ) {
						let store_id = parseInt(store.id);
						if ( store_id ) {
							report_data['store_' + store_id] = {
								store,
								data: []
							}
                            this.$set(this.active_store_collapses, 'store_' + store_id, '');
						}
					}
				});
				this.reports = report_data;
            });
        },
        exportFile() {
            this.is_exporting = true;
            this.$store.dispatch(EXPORT_GENERAL_REPORT, this.query).catch(() => {
                this.noticeMessage('error', 'Thất bại', 'Không thể xuất file Excel');
            }).finally(() => {
                this.is_exporting = false;
            })
        }
    },
};
</script>

<style scoped>
.mx-datepicker {
    width: 100%;
}

.example .example-preview {
    padding: 0;
    overflow-y: hidden;
}

.total-highlight {
    color: #009343;
    font-weight: bold;
}

.el-collapse-item__header .el-collapse-item__header:hover {
    background-color: rgb(155, 222, 239);
}

.el-collapse-item__wrap {
    height: fit-content;
    overflow: visible;
}

.el-collapse-item__arrow {
    color: #0962c4;
    font-weight: 900;
}

.section-title {
    margin: 40px 0;
}

.store-detail-section {
    scroll-margin-top: 90px;
}

.filter-row .filter-label {
    display: block;
    font-weight: 600;
    color: #181C32;
    margin-bottom: 0.5rem;
    height: 18px;
    line-height: 18px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.filter-row .filter-label-spacer {
    visibility: hidden;
    user-select: none;
}

@media (max-width: 767.98px) {
    .filter-row .filter-label-spacer {
        display: none !important;
    }
}

</style>

<style>
.filter-row .el-input__inner {
    height: 38px !important;
    line-height: 38px !important;
}

.filter-row .el-date-editor.el-input {
    height: 38px !important;
}

.filter-row .el-input__icon {
    line-height: 38px !important;
}

.filter-row .btn-search {
    height: 38px !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
</style>
