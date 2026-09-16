<template>
    <div class="report-car-rental">
        <div class="card card-custom gutter-b">
            <div class="card-header">
                <div class="card-title">
                    <h3 class="card-label">Doanh thu xe</h3>
                </div>
                <div class="card-title">
                    <button class="btn btn-primary" @click="exportFile">Export</button>
                </div>



            </div>

            <div class="card-body">

                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Tên xe, biển số</label>
                            <el-input clearable placeholder="Tên xe, biển số" v-model="query.name"></el-input>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Trạng thái</label>
                            <el-select filterable class="w-100" placeholder="Trạng thái" v-model="query.status"
                                clearable>
                                <el-option v-for="item in status" :key="item.id" :label="item.name" :value="item.id">
                                    <span style="float: left">{{
                                        item.name
                                    }}</span>
                                </el-option>
                            </el-select>
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

                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Loại xe</label>
                            <el-select filterable class="w-100" placeholder="Loại xe" v-model="query.type" clearable>
                                <el-option v-for="item in types" :key="item.id" :label="item.name" :value="item.id">
                                    <span style="float: left">{{
                                        item.name
                                    }}</span>
                                </el-option>
                            </el-select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Loại dịch vụ</label>
                            <el-select filterable class="w-100" placeholder="Loại dịch vụ"
                                v-model="query.type_of_service_id" clearable>
                                <el-option v-for="item in typeOfServices" :key="item.id" :label="item.name"
                                    :value="item.id">
                                    <span style="float: left">{{
                                        item.name
                                    }}</span>
                                </el-option>
                            </el-select>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Từ ngày</label>
                            <el-date-picker class="w-100" v-model="query.start_date" type="date" format="yyyy-MM-dd"
                                value-format="yyyy-MM-dd" :picker-options="pickerStartOptions" placeholder="Từ ngày">
                            </el-date-picker>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Đến ngày</label>
                            <el-date-picker class="w-100" v-model="query.end_date" type="date" ref="picker"
                                format="yyyy-MM-dd" value-format="yyyy-MM-dd" :picker-options="pickerEndOptions"
                                placeholder="Đến ngày">
                            </el-date-picker>
                        </div>
                    </div>
                    <div class="col-md-3 search-btn-vehicle-rev mt-8">
                        <button style="
                                        width: 100%;
                                    " class="btn btn-primary" :class="{
                                        'spinner spinner-white spinner-right':
                                            is_loading_search,
                                    }" @click="search">
                            Tìm kiếm
                        </button>

                    </div>
                </div>

                <div class="example mb-10">
                    <div class="example-preview table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>

                                    <th scope="col">Tên</th>
                                    <th scope="col">Biển số</th>
                                    <th @click="changeSort('count_order')" scope="col">Tổng order
                                        <span class="ml-1 text-success">
                                            {{ this.query.sort_by == 'count_order' ? (this.query.sort_type == 'asc' ? 'Tăng' : 'Giảm') : '' }}
                                        </span>
                                    </th>
                                    <th @click="changeSort('revenue')" scope="col">Doanh thu
                                        <span class="text-success ml-1">
                                            {{ this.query.sort_by == 'revenue' ? (this.query.sort_type == 'asc' ? 'Tăng' : 'Giảm') : '' }}
                                        </span>
                                    </th>
                                    <th scope="col" class="min-w-120px">
                                        Loại xe
                                    </th>
                                    <th scope="col" class="min-w-120px">
                                        Đời xe
                                    </th>


                                    <th scope="col" class="min-w-150px">
                                        Cửa hàng
                                    </th>
                                    <th scope="col">Trạng thái</th>
                                    <th scope="col">Hành động</th>

                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in vehicles.data" :key="index">
                                    <th scope="row">{{ parseInt(index) + 1 }}</th>

                                    <td>
                                        <span>{{ item.name }}<br /></span>
                                    </td>
                                    <td>{{ item.license }}</td>
                                    <td>{{ item.count_order }} </td>
                                    <td>{{ item.revenue | formatPrice }}</td>
                                    <td>{{ type_define[item.type] }}</td>
                                    <td>{{ item.year }}</td>


                                    <td>
                                        <span v-if="item.store" class="label label-info label-inline mr-2">{{
                                            item.store.store_name }}</span>
                                    </td>
                                    <td>
                                        <span :class="status_define_css[item.status]
                                            ">
                                            {{ status_define[item.status] }}
                                        </span>
                                    </td>
                                    <td>
                                        <button v-b-modal.modal-show-car-rental
                                            class="btn btn-xs btn-outline-info font-weight-bold" title="Xem chi tiết"
                                            @click="showPopup(item)">
                                            Xem
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <ModalShowVehicleRevenue :showOrder="showOrder" :vehicle="vehicle_show"></ModalShowVehicleRevenue>
                <div class="edu-paginate vehicle-revenue mx-auto text-center">
                    <paginate v-model="page" :page-count="last_page" :page-range="3" :margin-pages="1"
                        :click-handler="clickCallback" :prev-text="'Trước'" :next-text="'Sau'"
                        :container-class="'pagination b-pagination'" :pageLinkClass="'page-link'"
                        :next-link-class="'next-link-item'" :prev-link-class="'prev-link-item'"
                        :prev-class="'page-link'" :next-class="'page-link'" :page-class="'page-item'">
                    </paginate>
                </div>

            </div>

        </div>
    </div>
</template>

<script>
import { SET_BREADCRUMB } from "@/core/services/store/breadcrumbs.module";
import { EXPORT_VEHICLE_REVENUE } from "@/core/services/store/exports.module";
import { mapGetters } from "vuex";
import {
    VEHICLE_DELETE,
    VEHICLE_WITH_REVENUE,
    VEHICLE_GET_ALL_REPORT,
} from "../../../core/services/store/vehicle.module";
import queryMixin from '@/utils/queryMixin.js';
import moment from "moment-timezone";
import {
    types,
    typeOfService,
    typeOfServiceDefineCss,
    status,
    brands,
    type_define,
    status_define,
    status_define_css,
    typeOfServices,
    sortBys,
    sortTypes
} from "../../../option/vehicle";
import { STORE_GET_ALL } from "../../../core/services/store/store.module";
import ModalShowVehicleRevenue from "./ModalShowVehicleRevenue";
import Swal from "sweetalert2";

const _ = require("lodash");
export default {
    mixins: [queryMixin],
    name: "ReportVehicleRevenue",

    data() {
        const { page, store_id, type_of_service_id, ...restQuery } =
            this.$route?.query || {};

        return {
            showOrder: false,
            vehicle_show: null,
            query: {
                name: "",
                status: "",
                start_date: "",
                end_date: "",
                store_id: store_id ? +store_id : "",
                created_at: "",
                type: "",
                type_of_service_id: type_of_service_id
                    ? +type_of_service_id
                    : "",
                sort_by: 'revenue',
                sort_type: 'desc',
                ...(restQuery || {}),
            },
            pickerStartOptions: {},
            pickerEndOptions: {},
            status: status,
            stores: [],
            brands: brands,
            vehicles: [],
            reports: [],
            page: +page || 1,
            last_page: 1,
            types: types,
            type_define: type_define,
            typeOfServiceDefineCss: typeOfServiceDefineCss,
            type_of_service: typeOfService,
            typeOfServices: typeOfServices,
            sortBys: sortBys,
            sortTypes: sortTypes,
            status_define_css: status_define_css,
            status_define: status_define,
            is_loading_search: false,
            item_current: null,
        };
    },
    computed: {
        ...mapGetters(["currentUser"]),
    },
    watch: {
        vehicle_show(newVal, oldVal) {
            this.showOrder = false;
        },
      
    },

    components: {
        ModalShowVehicleRevenue
    },
    mounted() {
        this.getStore();
        this.$store.dispatch(SET_BREADCRUMB, [{ title: "Doanh thu xe" }]);
        this.getList();
        this.getReport();
    },
    methods: {
        showPopup(item) {
            this.vehicle_show = item;

        },
        changeSort(sort_by) {
            if (this.query.sort_by !== sort_by) {
                this.query.sort_by = sort_by;
            } else {

                this.query.sort_type = this.query.sort_type == 'asc' ? 'desc' : 'asc'
            }

            this.search();
        },

        search() {
            // this.pushParamsUrl();
            this.getList();
            this.getReport();
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
        getList() {
            this.is_loading_search = true;
            this.$store
                .dispatch(VEHICLE_WITH_REVENUE, {
                    page: this.page,
                    ...this.query,
                })
                .then((data) => {
                    this.vehicles = data.data;
                    this.last_page = data.data.last_page;
                })
                .finally(() => {
                    this.is_loading_search = false;
                });
        },
        getReport() {
            this.is_loading_search = true;
            this.$store
                .dispatch(VEHICLE_GET_ALL_REPORT, this.query)
                .then((data) => {
                    this.reports = data.data;
                });
        },
        clickCallback(obj) {
            this.page = obj;
            this.$router.push({ path: "", query: { page: this.page } });
            this.getList();
        },
        getStore() {
            this.$store.dispatch(STORE_GET_ALL, {}).then((data) => {
                this.stores = data.data;
            });
        },
        deleteVehicle(id) {
            Swal.fire({
                title: "Bạn chắc chắn muốn huỷ?",
                showDenyButton: true,
                showCancelButton: true,
                confirmButtonText: "Đồng ý",
                cancelButtonText: "Không",
            }).then((result) => {
                if (result.isConfirmed) {
                    this.$store
                        .dispatch(VEHICLE_DELETE, id)
                        .then((data) => {
                            this.$message.success(data.message);
                            this.getList();
                        })
                        .catch((err) => {
                            this.$message.error(err.data.message);
                        });
                }
            });
        },
        exportFile() {
            this.is_loading_search = true;
            this.$store.dispatch(EXPORT_VEHICLE_REVENUE, this.query).then().catch((error) => {
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

.vehicle-revenue ul {
    width: fit-content;
    margin: auto;
}
</style>
