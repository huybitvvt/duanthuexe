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

                <!-- BẢNG TẤT CẢ CỬA HÀNG CÓ MŨI TÊN CHI TIẾT TỪNG LOẠI PHÍ -->
                <div class="example mb-10">
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap">
                        <h4 class="section-title my-0">
                            <i class="fa fa-chart-bar text-primary mr-1"></i> Tất cả cửa hàng
                        </h4>
                        <button 
                            type="button" 
                            class="btn btn-sm btn-outline-primary font-weight-bolder shadow-sm"
                            @click="toggleAllStoresDetail"
                        >
                            <i :class="showDetail ? 'fa fa-chevron-up mr-1 text-primary' : 'fa fa-chevron-down mr-1 text-primary'"></i>
                            {{ showDetail ? 'Thu gọn chi tiết' : 'Mũi tên xem chi tiết từng loại phí ▼' }}
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" v-if="reports_all_stores">
                            <thead class="thead-light">
                                <tr>
                                    <th scope="col" class="cursor-pointer fee-header" @click="toggleFeeDetail('real_in')" title="Bấm để xem chi tiết Thu thực tế">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <span>Tổng thu thực tế</span>
                                            <span class="fee-arrow" :class="{ 'arrow-active': selectedFee === 'real_in' && showDetail }">
                                                <i :class="selectedFee === 'real_in' && showDetail ? 'fa fa-chevron-up text-primary' : 'fa fa-chevron-down text-muted'"></i>
                                            </span>
                                        </div>
                                    </th>
                                    <th scope="col" class="cursor-pointer fee-header" @click="toggleFeeDetail('real_refund')" title="Bấm để xem chi tiết Chi thực tế">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <span>Tổng chi thực tế</span>
                                            <span class="fee-arrow" :class="{ 'arrow-active': selectedFee === 'real_refund' && showDetail }">
                                                <i :class="selectedFee === 'real_refund' && showDetail ? 'fa fa-chevron-up text-primary' : 'fa fa-chevron-down text-muted'"></i>
                                            </span>
                                        </div>
                                    </th>
                                    <th scope="col" class="cursor-pointer fee-header" @click="toggleFeeDetail('deposit')" title="Bấm để xem chi tiết Thu cọc">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <span>Tổng thu cọc</span>
                                            <span class="fee-arrow" :class="{ 'arrow-active': selectedFee === 'deposit' && showDetail }">
                                                <i :class="selectedFee === 'deposit' && showDetail ? 'fa fa-chevron-up text-primary' : 'fa fa-chevron-down text-muted'"></i>
                                            </span>
                                        </div>
                                    </th>
                                    <th scope="col" class="cursor-pointer fee-header" @click="toggleFeeDetail('renew')" title="Bấm để xem chi tiết Thu gia hạn">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <span>Tổng thu gia hạn</span>
                                            <span class="fee-arrow" :class="{ 'arrow-active': selectedFee === 'renew' && showDetail }">
                                                <i :class="selectedFee === 'renew' && showDetail ? 'fa fa-chevron-up text-primary' : 'fa fa-chevron-down text-muted'"></i>
                                            </span>
                                        </div>
                                    </th>
                                    <th scope="col" class="cursor-pointer fee-header" @click="toggleFeeDetail('rental_fees')" title="Bấm để xem chi tiết Phí thuê">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <span>Tổng thu phí thuê</span>
                                            <span class="fee-arrow" :class="{ 'arrow-active': selectedFee === 'rental_fees' && showDetail }">
                                                <i :class="selectedFee === 'rental_fees' && showDetail ? 'fa fa-chevron-up text-primary' : 'fa fa-chevron-down text-muted'"></i>
                                            </span>
                                        </div>
                                    </th>
                                    <th scope="col" class="cursor-pointer fee-header" @click="toggleFeeDetail('early')" title="Bấm để xem chi tiết Trả sớm">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <span>Tổng trả sớm</span>
                                            <span class="fee-arrow" :class="{ 'arrow-active': selectedFee === 'early' && showDetail }">
                                                <i :class="selectedFee === 'early' && showDetail ? 'fa fa-chevron-up text-primary' : 'fa fa-chevron-down text-muted'"></i>
                                            </span>
                                        </div>
                                    </th>
                                    <th scope="col" class="cursor-pointer fee-header" @click="toggleFeeDetail('out_date')" title="Bấm để xem chi tiết Phạt muộn">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <span>Tổng phạt muộn</span>
                                            <span class="fee-arrow" :class="{ 'arrow-active': selectedFee === 'out_date' && showDetail }">
                                                <i :class="selectedFee === 'out_date' && showDetail ? 'fa fa-chevron-up text-primary' : 'fa fa-chevron-down text-muted'"></i>
                                            </span>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="total-highlight">
                                    <td class="cursor-pointer" @click="toggleFeeDetail('real_in')" :class="{ 'bg-light-success': selectedFee === 'real_in' && showDetail }">
                                        {{ totalInForAllStores | formatPrice }}
                                    </td>
                                    <td class="cursor-pointer" @click="toggleFeeDetail('real_refund')" :class="{ 'bg-light-danger': selectedFee === 'real_refund' && showDetail }">
                                        {{ reports_all_stores.total_real_refund | formatPrice }}
                                    </td>
                                    <td class="cursor-pointer" @click="toggleFeeDetail('deposit')" :class="{ 'bg-light-info': selectedFee === 'deposit' && showDetail }">
                                        {{ reports_all_stores.total_deposit | formatPrice }}
                                    </td>
                                    <td class="cursor-pointer" @click="toggleFeeDetail('renew')" :class="{ 'bg-light-info': selectedFee === 'renew' && showDetail }">
                                        {{ reports_all_stores.total_renew | formatPrice }}
                                    </td>
                                    <td class="cursor-pointer" @click="toggleFeeDetail('rental_fees')" :class="{ 'bg-light-info': selectedFee === 'rental_fees' && showDetail }">
                                        {{ reports_all_stores.total_rental_fees | formatPrice }}
                                    </td>
                                    <td class="cursor-pointer" @click="toggleFeeDetail('early')" :class="{ 'bg-light-info': selectedFee === 'early' && showDetail }">
                                        {{ Math.abs(reports_all_stores.total_money_early) | formatPrice }}
                                    </td>
                                    <td class="cursor-pointer" @click="toggleFeeDetail('out_date')" :class="{ 'bg-light-info': selectedFee === 'out_date' && showDetail }">
                                        {{ reports_all_stores.total_money_out_date | formatPrice }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- KHỐI MỞ RỘNG CHI TIẾT TỪNG LOẠI PHÍ & TỪNG CỬA HÀNG -->
                    <div v-if="showDetail" class="detail-box card card-body bg-light border border-primary p-4 mt-3 mb-6 rounded shadow-sm">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h5 class="font-weight-bolder text-primary mb-0">
                                <i class="fa fa-info-circle mr-1 text-primary"></i> Chi tiết loại phí:
                                <span v-if="selectedFeeLabel" class="badge badge-primary ml-2 font-size-sm">{{ selectedFeeLabel }}</span>
                                <span v-else class="badge badge-light-primary ml-2 font-size-sm">Tất cả loại phí</span>
                            </h5>
                            <button type="button" class="btn btn-xs btn-outline-secondary" @click="showDetail = false">
                                <i class="fa fa-times mr-1"></i> Đóng
                            </button>
                        </div>

                        <!-- 1. Cơ cấu loại phí (Thu - Chi) -->
                        <div class="row mb-4">
                            <div class="col-md-6 mb-2">
                                <div class="card p-3 border h-100 shadow-none" :class="{ 'border-success bg-white': selectedFee === 'real_in' || selectedFee === 'deposit' || selectedFee === 'renew' || selectedFee === 'rental_fees' }">
                                    <div class="font-weight-bolder text-success mb-2 d-flex justify-content-between align-items-center">
                                        <span><i class="fa fa-arrow-down mr-1"></i> Cơ cấu Thu thực tế:</span>
                                        <span class="font-size-h6">{{ totalInForAllStores | formatPrice }}</span>
                                    </div>
                                    <ul class="list-unstyled mb-0 font-size-sm">
                                        <li class="d-flex justify-content-between py-1 border-bottom" :class="{ 'font-weight-bolder text-primary': selectedFee === 'deposit' }">
                                            <span>• Thu tiền cọc:</span>
                                            <span>{{ (reports_all_stores.total_deposit || 0) | formatPrice }}</span>
                                        </li>
                                        <li class="d-flex justify-content-between py-1 border-bottom" :class="{ 'font-weight-bolder text-primary': selectedFee === 'renew' }">
                                            <span>• Thu tiền gia hạn:</span>
                                            <span>{{ (reports_all_stores.total_renew || 0) | formatPrice }}</span>
                                        </li>
                                        <li class="d-flex justify-content-between py-1" :class="{ 'font-weight-bolder text-primary': selectedFee === 'rental_fees' }">
                                            <span>• Thu phí thuê xe:</span>
                                            <span>{{ (reports_all_stores.total_rental_fees || 0) | formatPrice }}</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="card p-3 border h-100 shadow-none" :class="{ 'border-danger bg-white': selectedFee === 'real_refund' || selectedFee === 'early' || selectedFee === 'out_date' }">
                                    <div class="font-weight-bolder text-danger mb-2 d-flex justify-content-between align-items-center">
                                        <span><i class="fa fa-arrow-up mr-1"></i> Cơ cấu Chi & Trừ thực tế:</span>
                                        <span class="font-size-h6">{{ (reports_all_stores.total_real_refund || 0) | formatPrice }}</span>
                                    </div>
                                    <ul class="list-unstyled mb-0 font-size-sm">
                                        <li class="d-flex justify-content-between py-1 border-bottom" :class="{ 'font-weight-bolder text-primary': selectedFee === 'real_refund' }">
                                            <span>• Tiền hoàn cọc thực tế cho khách:</span>
                                            <span>{{ (reports_all_stores.total_real_refund || 0) | formatPrice }}</span>
                                        </li>
                                        <li class="d-flex justify-content-between py-1 border-bottom" :class="{ 'font-weight-bolder text-primary': selectedFee === 'early' }">
                                            <span>• Hoàn trừ do khách trả xe sớm:</span>
                                            <span>{{ Math.abs(reports_all_stores.total_money_early || 0) | formatPrice }}</span>
                                        </li>
                                        <li class="d-flex justify-content-between py-1" :class="{ 'font-weight-bolder text-primary': selectedFee === 'out_date' }">
                                            <span>• Phạt trả xe quá hạn (thu bù thêm):</span>
                                            <span>{{ (reports_all_stores.total_money_out_date || 0) | formatPrice }}</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Bảng phân bổ đóng góp từng cửa hàng -->
                        <div class="card p-3 border bg-white shadow-none">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="font-weight-bolder text-dark">
                                    <i class="fa fa-store mr-1 text-primary"></i> Phân bổ số tiền từng loại phí theo từng cửa hàng:
                                </span>
                                <span class="text-muted font-size-xs">Cập nhật theo khoảng thời gian đã lọc</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered table-striped mb-0">
                                    <thead class="bg-primary text-white">
                                        <tr>
                                            <th>Cửa hàng</th>
                                            <th :class="{ 'bg-success font-weight-bolder': selectedFee === 'real_in' }">Thu thực tế</th>
                                            <th :class="{ 'bg-danger font-weight-bolder': selectedFee === 'real_refund' }">Chi thực tế</th>
                                            <th :class="{ 'bg-dark font-weight-bolder': selectedFee === 'deposit' }">Thu cọc</th>
                                            <th :class="{ 'bg-dark font-weight-bolder': selectedFee === 'renew' }">Thu gia hạn</th>
                                            <th :class="{ 'bg-dark font-weight-bolder': selectedFee === 'rental_fees' }">Phí thuê</th>
                                            <th :class="{ 'bg-dark font-weight-bolder': selectedFee === 'early' }">Trả sớm</th>
                                            <th :class="{ 'bg-dark font-weight-bolder': selectedFee === 'out_date' }">Phạt muộn</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="st in (reports_all_stores.by_store || [])" :key="st.store_id">
                                            <td class="font-weight-bold">{{ st.store_name }}</td>
                                            <td class="text-success font-weight-bold" :class="{ 'bg-light-success': selectedFee === 'real_in' }">
                                                {{ st.total_real_in | formatPrice }}
                                            </td>
                                            <td class="text-danger font-weight-bold" :class="{ 'bg-light-danger': selectedFee === 'real_refund' }">
                                                {{ st.total_real_refund | formatPrice }}
                                            </td>
                                            <td :class="{ 'bg-light-info font-weight-bolder text-primary': selectedFee === 'deposit' }">
                                                {{ st.total_deposit | formatPrice }}
                                            </td>
                                            <td :class="{ 'bg-light-info font-weight-bolder text-primary': selectedFee === 'renew' }">
                                                {{ st.total_renew | formatPrice }}
                                            </td>
                                            <td :class="{ 'bg-light-info font-weight-bolder text-primary': selectedFee === 'rental_fees' }">
                                                {{ st.total_rental_fees | formatPrice }}
                                            </td>
                                            <td :class="{ 'bg-light-info font-weight-bolder text-primary': selectedFee === 'early' }">
                                                {{ Math.abs(st.total_money_early) | formatPrice }}
                                            </td>
                                            <td :class="{ 'bg-light-info font-weight-bolder text-primary': selectedFee === 'out_date' }">
                                                {{ st.total_money_out_date | formatPrice }}
                                            </td>
                                        </tr>
                                        <tr v-if="!(reports_all_stores.by_store && reports_all_stores.by_store.length)">
                                            <td colspan="8" class="text-center text-muted py-3">Chưa có dữ liệu phân bổ theo từng cửa hàng.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
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
            dateMode: 'range', // 'single' | 'range'
            singleDate: moment().format("YYYY-MM-DD"),
            rangeStartDate: moment().startOf("month").format("YYYY-MM-DD"),
            rangeEndDate: moment().format("YYYY-MM-DD"),
            showDetail: false,
            selectedFee: null,
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
        selectedFeeLabel() {
            const map = {
                real_in: 'Tổng thu thực tế',
                real_refund: 'Tổng chi thực tế',
                deposit: 'Tổng thu cọc',
                renew: 'Tổng thu gia hạn',
                rental_fees: 'Tổng thu phí thuê',
                early: 'Tổng trả sớm',
                out_date: 'Tổng phạt muộn',
            };
            return map[this.selectedFee] || '';
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
        toggleAllStoresDetail() {
            this.showDetail = !this.showDetail;
            if (!this.showDetail) {
                this.selectedFee = null;
            }
        },
        toggleFeeDetail(feeKey) {
            if (this.showDetail && this.selectedFee === feeKey) {
                this.showDetail = false;
                this.selectedFee = null;
            } else {
                this.showDetail = true;
                this.selectedFee = feeKey;
            }
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

.cursor-pointer {
    cursor: pointer;
}

.fee-header {
    user-select: none;
    transition: background-color 0.2s;
}

.fee-header:hover {
    background-color: #e8f4fd !important;
}

.fee-arrow {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    margin-left: 6px;
    background: rgba(0, 0, 0, 0.05);
    transition: all 0.2s ease;
}

.fee-header:hover .fee-arrow,
.fee-arrow.arrow-active {
    background: #e1f0ff;
}

.detail-box {
    background: #fbfcfe !important;
    animation: fadeIn 0.25s ease-in-out;
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

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-5px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
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
