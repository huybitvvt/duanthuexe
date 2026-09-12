<template>
	<div>
		<div class="row mb-8">
			<div class="col-md-6">
				<div class="card">
					<div class="card-body">
						<h4>Hôm nay</h4>

						<div class="row mt-8">
							<div class="col-md-6">
								<div class="font-size-sm text-muted font-weight-bold"><i class="fas fa-dollar-sign mr-2"></i>Tổng thu thực tế</div>
								<div class="font-size-h4 font-weight-bolder">{{ totalInByDay | formatPrice }}</div>
							</div>
							<div class="col-md-6">
								<div class="font-size-sm text-muted font-weight-bold"><i class="fas fa-dollar-sign mr-2"></i>Tổng chi thực tế</div>
								<div class="font-size-h4 font-weight-bolder">{{ reports.total_refund_in_day_new | formatPrice }}</div>
							</div>
						</div>
						<div><hr/></div>
						<div class="row">
							<div class="col-md-4">
								<div class="font-size-sm text-muted font-weight-bold"><i class="fas fa-dollar-sign mr-2"></i>Tổng thu cọc</div>
								<div class="font-size-h4 font-weight-bolder">{{ reports.total_deposit_in_day_new | formatPrice }}</div>
							</div>
							<div class="col-md-4">
								<div class="font-size-sm text-muted font-weight-bold"><i class="fas fa-dollar-sign mr-2"></i>Tổng thu gia hạn</div>
								<div class="font-size-h4 font-weight-bolder">{{ reports.total_renew_in_day_new | formatPrice }}</div>
							</div>
							<div class="col-md-4">
								<div class="font-size-sm text-muted font-weight-bold"><i class="fas fa-dollar-sign mr-2"></i>Tổng thu phí thuê</div>
								<div class="font-size-h4 font-weight-bolder">{{ reports.total_rental_fees_in_day_new | formatPrice }}</div>
							</div>
						</div>

						<div class="row mt-8">
							<div class="col-md-4">
								<div class="font-size-sm text-muted font-weight-bold"><i class="fas fa-dollar-sign mr-2"></i>Tổng tiền cọc phải trả</div>
								<div class="font-size-h4 font-weight-bolder">{{ reports.total_origin_refund_in_day_new | formatPrice }}</div>
							</div>
							<div class="col-md-4">
								<div class="font-size-sm text-muted font-weight-bold"><i class="fas fa-dollar-sign mr-2"></i>Tổng hoàn do trả sớm</div>
								<div class="font-size-h4 font-weight-bolder">{{ Math.abs(reports.total_money_early_in_day_new) | formatPrice }}</div>
							</div>
							<div class="col-md-4">
								<div class="font-size-sm text-muted font-weight-bold"><i class="fas fa-dollar-sign mr-2"></i>Tổng phạt muộn</div>
								<div class="font-size-h4 font-weight-bolder">{{ reports.total_money_out_date_in_day_new | formatPrice }}</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="col-md-6">
				<div class="card">
					<div class="card-body">
						<h4>Tháng này</h4>

						<div class="row mt-8">
							<div class="col-md-6">
								<div class="font-size-sm text-muted font-weight-bold"><i class="fas fa-dollar-sign mr-2"></i>Tổng thu thực tế</div>
								<div class="font-size-h4 font-weight-bolder">{{ totalInByMonth | formatPrice }}</div>
							</div>
							<div class="col-md-6">
								<div class="font-size-sm text-muted font-weight-bold"><i class="fas fa-dollar-sign mr-2"></i>Tổng chi thực tế</div>
								<div class="font-size-h4 font-weight-bolder">{{ reports.total_refund_in_month_new | formatPrice }}</div>
							</div>
						</div>
						<div><hr/></div>
						<div class="row">
							<div class="col-md-4">
								<div class="font-size-sm text-muted font-weight-bold"><i class="fas fa-dollar-sign mr-2"></i>Tổng thu cọc</div>
								<div class="font-size-h4 font-weight-bolder">{{ reports.total_deposit_in_month_new | formatPrice }}</div>
							</div>
							<div class="col-md-4">
								<div class="font-size-sm text-muted font-weight-bold"><i class="fas fa-dollar-sign mr-2"></i>Tổng thu gia hạn</div>
								<div class="font-size-h4 font-weight-bolder">{{ reports.total_renew_in_month_new | formatPrice }}</div>
							</div>
							<div class="col-md-4">
								<div class="font-size-sm text-muted font-weight-bold"><i class="fas fa-dollar-sign mr-2"></i>Tổng thu phí thuê</div>
								<div class="font-size-h4 font-weight-bolder">{{ reports.total_rental_fees_in_month_new | formatPrice }}</div>
							</div>
						</div>

						<div class="row mt-8">
							<!-- <div class="col-md-4">
								<div class="font-size-sm text-muted font-weight-bold"><i class="fas fa-dollar-sign mr-2"></i>Tổng tiền cọc phải trả</div>
								<div class="font-size-h4 font-weight-bolder">{{ reports.total_origin_refund_in_month_new | formatPrice }}</div>
							</div> -->
							<div class="col-md-4">
								<div class="font-size-sm text-muted font-weight-bold"><i class="fas fa-dollar-sign mr-2"></i>Tổng hoàn do trả sớm</div>
								<div class="font-size-h4 font-weight-bolder">{{ Math.abs(reports.total_money_early_in_month_new) | formatPrice }}</div>
							</div>
							<div class="col-md-4">
								<div class="font-size-sm text-muted font-weight-bold"><i class="fas fa-dollar-sign mr-2"></i>Tổng phạt muộn</div>
								<div class="font-size-h4 font-weight-bolder">{{ reports.total_money_out_date_in_month_new | formatPrice }}</div>
							</div>
						</div>

					</div>
				</div>
			</div>
		</div>
		<div class="card">
			<div class="card-body">
				<div class="row">
					<div class="col-md-2">
						<div class="font-size-sm text-muted font-weight-bold"><i class="fas fa-motorcycle mr-2"></i>Tổng xe
						</div>
						<div class="font-size-h4 font-weight-bolder">{{ reports.total_vehicle }}</div>
					</div>
					<div class="col-md-2 border-left">
						<div class="font-size-sm text-muted font-weight-bold"><i class="fas fa-motorcycle mr-2"></i>Đang cho
							thuê
						</div>
						<div class="font-size-h4 font-weight-bolder">{{ reports.total_vehicle_using }}</div>
					</div>
					<div class="col-md-2 border-left">
						<div class="font-size-sm text-muted font-weight-bold"><i class="fas fa-motorcycle mr-2"></i>Đang sẵn
							sàng
						</div>
						<div class="font-size-h4 font-weight-bolder">{{ reports.total_vehicle_ready }}</div>
					</div>
					<div class="col-md-2 border-left">
						<div class="font-size-sm text-muted font-weight-bold"><i class="fas fa-motorcycle mr-2"></i>Xe hỏng
						</div>
						<div class="font-size-h4 font-weight-bolder text-danger">{{ reports.total_vehicle_broken }}</div>
					</div>
					<div class="col-md-2 border-left">
						<div class="font-size-sm text-muted font-weight-bold"><i
								class="fas fa-sort-amount-up-alt mr-2"></i>Lượt
							thuê hôm nay
						</div>
						<div class="font-size-h4 font-weight-bolder">{{ reports.total_order_in_day }}</div>
					</div>

				</div>
				<div class="row mt-8">
					<div class="col-md-2">
						<div class="font-size-sm text-muted font-weight-bold"><i class="far fa-user mr-2"></i>Khách hàng
						</div>
						<div class="font-size-h4 font-weight-bolder">{{ reports.total_customer }}</div>
					</div>
					<div class="col-md-2 border-left">
						<div class="font-size-sm text-muted font-weight-bold"><i class="fas fa-house-user mr-2"></i>Nhân viên
						</div>
						<div class="font-size-h4 font-weight-bolder">{{ reports.total_staff }}</div>
					</div>
					<div class="col-md-2 border-left">
						<div class="font-size-sm text-muted font-weight-bold"><i
								class="fas fa-money-bill-wave-alt mr-2"></i>Số hợp đồng quá hạn
						</div>
						<div class="font-size-h4 font-weight-bolder">{{ reports.total_order_out_date_in_month }}</div>
					</div>
					<div class="col-md-2 border-left">
						<div class="font-size-sm text-muted font-weight-bold"><i
								class="fas fa-money-bill-wave-alt mr-2"></i>Lượt thuê tháng này
						</div>
						<div class="font-size-h4 font-weight-bolder">{{ reports.total_order_in_month }}</div>
					</div>
				</div>
			</div>
			<div class="card-body">
				<div>
					<zingchart :data="chartData" :theme="chartTheme"></zingchart>
				</div>
			</div>
			<lead-index context="dashboard"></lead-index>
		</div>
	</div>
</template>

<script>
import { SET_BREADCRUMB } from "@/core/services/store/breadcrumbs.module";
import { mapGetters } from "vuex";
import { DASHBOARD_REPORT, DASHBOARD_REPORT_CHART } from "../../core/services/store/dashboard.module";
import { zingChartTheme } from "../../core/config/zingChartTheme";
import LeadIndex from "@/view/pages/lead/LeadIndex";

export default {
    name: "dashboard",
    components: { LeadIndex },
    computed: {
        ...mapGetters(["currentUser"]),
    },
    data() {
        return {
            labels: [],
            values: [],
            chartData: {
                type: 'line',
                'scale-x': {
                    labels: [],
                    format: "$%v",
                    negation: "currency"
                },
                series: [
                    {
                        values: []
                    }
                ]
            },
            chartTheme: zingChartTheme,
            reports: []
        }
    },
    mounted() {
        this.$store.dispatch(SET_BREADCRUMB, [{ title: "Dashboard" }]);
        this.report();
        this.reportChart();
    },
	computed: {
		totalInByDay() {
			return parseInt(this.reports.total_deposit_in_day_new) + parseInt(this.reports.total_renew_in_day_new) + parseInt(this.reports.total_rental_fees_in_day_new);
		},
		totalInByMonth() {
			return parseInt(this.reports.total_deposit_in_month_new) + parseInt(this.reports.total_renew_in_month_new) + parseInt(this.reports.total_rental_fees_in_month_new);
		},
	},
    methods: {
        report() {
            this.$store.dispatch(DASHBOARD_REPORT, this.query).then(data => {
                this.reports = data.data;
            });
        },
        reportChart() {
            this.$store.dispatch(DASHBOARD_REPORT_CHART, this.query).then(data => {
                this.labels = data.data.labels;
                this.values = data.data.values;

                this.chartData = {
                    type: 'line',
                    'scale-x': {
                        labels: this.labels,
                    },
                    'scale-y': {
                        short: false,
                        'short-unit': "M",
                        'thousands-separator': ","
                    },
                    plot: {
                        'value-box': {
                            'thousands-separator': ","
                        }
                    },
                    series: [
                        {
                            values: this.values
                        }
                    ]
                };
            });
        },
        /**
         * Set current active on click
         * @param event
         */
        setActiveTab(event) {
            // get all tab links
            const tab = event.target.closest('[role="tablist"]');
            const links = tab.querySelectorAll(".nav-link");
            // remove active tab links
            for (let i = 0; i < links.length; i++) {
                links[i].classList.remove("active");
            }

            // set current active tab
            event.target.classList.add("active");

            // set clicked tab index to bootstrap tab
            return parseInt(event.target.getAttribute("data-tab"));
        }
    }
};
</script>
