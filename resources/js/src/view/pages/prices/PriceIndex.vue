<template>
    <div>
        <div class="card card-custom gutter-b">
            <div class="card-header">
                <div class="card-title">
                    <h3 class="card-label">Cài đặt giá</h3>
                </div>
            </div>
            <div class="card-body">
                <div class="example">
                    <div class="table-responsive">
                        <table class="table table-vertical-center table-hover table-bordered">
                            <thead>
                            <tr class="text-center">
                                <th scope="col">Loại xe</th>
                                <th scope="col">Từ năm</th>
                                <th scope="col">Đến năm</th>
                                <th scope="col">Từ ngày</th>
                                <th scope="col">Đến ngày</th>
                                <th scope="col">Giá</th>
                                <th scope="col">Loại</th>
                                <th scope="col">Hành động</th>
                            </tr>
                            </thead>
                            <tbody v-if="priceVehicles.length">
                            <tr v-for="(item, index) in priceVehicles" :key="index">
                                <td>
                                    <el-select v-model="item.type" filterable placeholder="Chọn loại xe"
                                               class="w-100">
                                        <el-option
                                            v-for="vl in TYPE_VEHICLE"
                                            :key="vl.id"
                                            :label="vl.label"
                                            :value="vl.value"
                                        >
                                        </el-option>
                                    </el-select>
                                </td>
                                <td>
                                    <el-select v-model="item.from_year" filterable placeholder="Từ năm"
                                               class="w-100">
                                        <el-option
                                            v-for="vl in years"
                                            :key="vl.id"
                                            :label="vl.label"
                                            :value="vl.value"
                                        >
                                        </el-option>
                                    </el-select>
                                </td>
                                <td>
                                    <el-select v-model="item.to_year" filterable placeholder="Đến năm"
                                               class="w-100">
                                        <el-option
                                            v-for="vl in years"
                                            :key="vl.id"
                                            :label="vl.label"
                                            :value="vl.value"
                                        >
                                        </el-option>
                                    </el-select>
                                </td>
                                <td>
                                    <el-select v-model="item.from_date" filterable placeholder="Từ ngày"
                                               class="w-100">
                                        <el-option
                                            v-for="vl in dates"
                                            :key="vl.id"
                                            :label="vl.label"
                                            :value="vl.value"
                                        >
                                        </el-option>
                                    </el-select>
                                </td>
                                <td>
                                    <el-select v-model="item.to_date" filterable placeholder="Đến ngày"
                                               class="w-100">
                                        <el-option
                                            v-for="vl in dates"
                                            :key="vl.id"
                                            :label="vl.label"
                                            :value="vl.value"
                                        >
                                        </el-option>
                                    </el-select>
                                </td>
                                <td>
                                    <money id="account" v-model="item.price" v-bind="money"
                                           class="form-control"></money>
                                </td>
                                <td>
                                    <el-select v-model="item.price_type" filterable placeholder="Chọn loại thuê"
                                               class="w-100">
                                        <el-option
                                            v-for="vl in TYPE_PRICING_HIRE"
                                            :key="vl.id"
                                            :label="vl.label"
                                            :value="vl.value"
                                        >
                                        </el-option>
                                    </el-select>
                                </td>
                                <td class="text-center">
                                    <button v-if="currentUser.role_id === 1" class="btn btn-xs btn-outline-danger font-weight-bold" title="Xóa cài đặt" @click="deletePriceVehicle(item.id, index)">Xóa</button>
                                </td>
                            </tr>
                            </tbody>
                            <tbody v-else>
                            <tr class="text-center">
                                <td scope="row" colspan="6">Không tìm thấy cài đặt giá phù hợp</td>
                            </tr>
                            </tbody>
                        </table>
                        <div class="card-toolbar mt-3 d-flex justify-content-center" v-if="currentUser.role_id === 1">
                            <el-button native-type="button" class="btn btn-sm btn-info mr-2" @click="addNewPriceVehicle"
                                       style="color: #fff">
                                Thêm mới
                            </el-button>
                            <el-button native-type="submit" class="btn btn-sm btn-success mr-2" style="color: #fff"
                                       @click="updatePriceVehicle"
                                       :loading="loadingComplete">
                                Lưu cài đặt
                            </el-button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import moment from "moment-timezone";
import {Money} from 'v-money';
import {mapGetters} from "vuex";
import {PRICE_VEHICLES_INDEX, PRICE_VEHICLES_UPDATE, DELETE_PRICE_VEHICLES} from "@/core/services/store/vehicle.module";
import {SET_BREADCRUMB} from "@/core/services/store/breadcrumbs.module";
import {TYPE_VEHICLE, TYPE_PRICING_HIRE} from "@/option/vehicle";

export default {
    name: "PriceIndex",
    data() {
        return {
            moment: moment,
            priceVehicles: [],
            loading: false,
            loadingComplete: false,
            reports: [],
            years: [],
            dates: [],
            query: {},
            TYPE_VEHICLE,
            TYPE_PRICING_HIRE,
            /* v-money */
            money: {
                decimal: ',',
                thousands: ',',
                prefix: '',
                suffix: ' VNĐ',
                precision: 0,
                masked: false,
            },
        }
    },
    components: {Money},
    computed: {
        ...mapGetters(["currentUser"])
    },
    created() {
        this.getRangeOfDate();
        this.getRangeOfYears();
        this.getListVehiclesPrice();
    },
    mounted() {
        this.$store.dispatch(SET_BREADCRUMB, [{title: "Cài đặt"}]);
    },
    methods: {
        getListVehiclesPrice() {
            this.$store.dispatch(PRICE_VEHICLES_INDEX, {}).then(data => {
                this.priceVehicles = data.data;
            });
        },
        clickCallback(obj) {
            this.page = obj;
            this.getListVehiclesPrice();
        },
        addNewPriceVehicle() {
            this.priceVehicles.push({
                type: 'xeso',
                from_year: 2000,
                to_year: new Date().getFullYear(),
                from_date: 1,
                to_date: 30,
                price: 0,
                price_type: 'day',
            })
        },
        updatePriceVehicle() {
            this.loadingComplete = true;
            let params = {priceVehicles : this.priceVehicles};
            this.$store.dispatch(PRICE_VEHICLES_UPDATE, params).then(() => {
                this.getListVehiclesPrice();
                this.noticeMessage('success', 'Thành công', 'Cập nhật cài đặt giá thành công');
            }).catch((err) => {
                this.noticeMessage('error', 'Thất bại', err.data?.message);
            }).finally(() => this.loadingComplete = false);
        },
        deletePriceVehicle(pricingId, index) {
            this.$swal.fire({
                title: 'Bạn có chắc chắn muốn xóa cài đặt này?',
                showCancelButton: true,
                cancelButtonText: "Hủy",
                confirmButtonText: 'Xóa',
            }).then((result) => {
                if (result.isConfirmed) {
                    if(!pricingId) return this.priceVehicles.splice(index, 1);
                    this.$store.dispatch(DELETE_PRICE_VEHICLES, pricingId).then(() => {
                        this.getListVehiclesPrice();
                    });
                    this.noticeMessage('success', 'Thành công', 'Xóa cài đặt giá thành công');
                }
            })
        },
        getRangeOfYears() {
            let currentYear = new Date('2099-12-31').getFullYear();
            let startYear = 2000;
            while (startYear <= currentYear) {
                this.years.push({label: startYear, value: startYear});
                startYear++;
            }
        },
        getRangeOfDate() {
            let endDate = 60;
            let startDate = 1;
            while (startDate <= endDate) {
                this.dates.push({label: startDate + ' ngày', value: startDate});
                startDate++;
            }
        }
    }
}
</script>

<style scoped>
</style>
