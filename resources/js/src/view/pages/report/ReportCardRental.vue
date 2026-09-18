<template>
    <div class="report-car-rental">
        <div class="card card-custom gutter-b">
            <div class="card-header">
                <div class="card-title">
                    <h3 class="card-label">Tổng quan báo cáo thuê xe</h3>
                </div>
                <!-- <div class="card-title">
                    <button class="btn btn-success" @click="exportFile">
                        Export
                    </button>
                </div> -->

            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="d-block">Thời gian tạo</label>
                            <date-picker v-model="query.dates" type="date" range placeholder="Chọn thời gian tạo"
                                format="DD-MM-YYYY" valueType="YYYY-MM-DD"></date-picker>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Cửa hàng</label>

                            <el-select filterable class="w-100" placeholder="Cửa hàng" v-model="query.store_id"
                                clearable>
                                <el-option v-for="item in stores" :key="item.id" :label="item.store_name"
                                    :value="item.id">
                                    <span style="float: left">{{
                                        item.store_name
                                        }}</span>
                                </el-option>
                            </el-select>
                        </div>
                    </div>
                    <div class="col-md-3 mt-8">
                        <button class="btn btn-primary" :class="{
                            'spinner spinner-white spinner-right':
                                is_loading_search,
                        }" @click="search">
                            Tìm kiếm
                        </button>

                    </div>
                </div>
                <div class="example mb-10">
                    <h4 class="section-title">Tất cả cửa hàng</h4>
                    <table class="table" v-if="reports_all_stores">
                        <thead>
                            <tr>
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
                                <td>{{ totalInForAllStores | formatPrice }}</td>
                                <td>{{ reports_all_stores.total_real_refund | formatPrice }} </td>
                                <td>{{ reports_all_stores.total_deposit | formatPrice }}</td>
                                <td>{{ reports_all_stores.total_renew | formatPrice }}</td>
                                <td>{{ reports_all_stores.total_rental_fees | formatPrice }}</td>
                                <td>{{ Math.abs(reports_all_stores.total_money_early) | formatPrice }}</td>
								<td>{{ reports_all_stores.total_money_out_date | formatPrice }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <h4 class="section-title">Từng cửa hàng</h4>

					<div class="example-preview table-responsive" v-for="(report, key) in reports" :key="key">
                        <el-collapse accordion @change="onCollapseChanged($event, report.store.id)">
                            <el-collapse-item name="1">
                               
								<template slot="title">
                                    <p class="font-weight-bold">Cửa hàng: {{ report.store.store_name }}</p>
                                </template>

								<table class="table">
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
            reports: {},
            reports_all_stores: {},
            stores: [],
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
		onCollapseChanged(is_opened, store_id) {
			if ( is_opened ) {
				let params = JSON.parse(JSON.stringify(this.query)); // Deep clone {this.query} to prevent new changed in {params} will be overwride {this.query}
				params['store_id'] = store_id;
				this.$store.dispatch(REPORT_CAR_RENTAL_DAY_BY_DAY, params).then((data) => {
					store_id = parseInt(store_id);
					if (this.reports['store_' + store_id]) {
						this.$set(this.reports['store_' + store_id], 'data', data.data);
					}
				});

				const index = this.collapse_opened_ids.indexOf(store_id);
				if (index == -1) {
					this.collapse_opened_ids.push(store_id);
				}
			} else {
				const index = this.collapse_opened_ids.indexOf(store_id);
				if (index !== -1) {
					this.collapse_opened_ids.splice(index, 1);
				}
			}
		},
        getDateDefault() {
            this.query.dates.push(
                this.moment().startOf("month").format("YYYY-MM-DD"),
            );
            this.query.dates.push(this.moment().format("YYYY-MM-DD"));
        },
        async search() {
            // this.pushParamsUrl();
            this.report();
			if (this.collapse_opened_ids) {
				let params = JSON.parse(JSON.stringify(this.query)); // Deep clone {this.query} to prevent new changed in {params} will be overwride {this.query}
				for (let store_id of this.collapse_opened_ids) {
					params['store_id'] = store_id;
					await this.getStoreDetailReport(params, store_id);
				}
			}
        },

		async getStoreDetailReport(params, store_id) {
			return new Promise(resolve => {
				this.$store.dispatch(REPORT_CAR_RENTAL_DAY_BY_DAY, params).then((data) => {
					store_id = parseInt(store_id);

					if (!this.reports['store_' + store_id]) {
						this.$set(this.reports, 'store_' + store_id, { data: [] });
					}
					this.$set(this.reports['store_' + store_id], 'data', data.data);
					resolve();
				});
			});
		},

        report() {
            this.$store.dispatch(REPORT_CAR_RENTAL_NEW, this.query).then((data) => {
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

				console.log('store before: ', this.reports.length, ' -- data: ', this.reports);

				let report_data = {};
				res.data.forEach((store) => {
					if ( store ) {
						let store_id = parseInt(store.id);
						if ( store_id ) {
							report_data['store_' + store_id] = {
								store,
								data: []
							}
						}
					}
				});
				this.reports = report_data;
            });
        },
        exportFile() {
            this.is_loading_search = true;
            this.$store.dispatch(EXPORT_GENERAL_REPORT, this.query).then().catch((error) => {
                this.noticeMessage('error', 'Thất bại', error.message);
            }).finally(() => {
                this.is_loading_search = false;
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
</style>
