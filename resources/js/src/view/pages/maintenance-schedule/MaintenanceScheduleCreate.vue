<template>
    <ValidationObserver v-slot="{ handleSubmit }" ref="form">

        <div class="card card-custom gutter-b">
            <div class="card-header">
                <div class="card-title">
                    <h3 class="card-label">Thêm mới lịch hẹn bảo dưỡng</h3>
                </div>
            </div>
            <div class="card-body">
                <form class="form" @submit.prevent="handleSubmit(onSubmit)">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Chọn xe <span class="text-danger">(*)</span></label>

                                <el-select v-model="schedule.vehicle_id" clearable filterable class="w-100"
                                    placeholder="Chọn xe">
                                    <el-option v-for="item in vehicles" :key="item.id"
                                        :label="`${item.name}(${item.license})`" :value="item.id">
                                        <span style="float: left">{{ item.name }}</span>
                                        <span style="
                                        float: right;
                                        color: #8492a6;
                                        font-size: 13px;
                                    ">{{ item.license }}</span>
                                    </el-option>
                                </el-select>

                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Loại bảo dưỡng<span class="text-danger">(*)</span></label>

                                <el-select filterable class="w-100" placeholder="Loại bảo dưỡng"
                                    v-model="schedule.maintenance_type_id" clearable>
                                    <el-option v-for="item2 in maintenance_types" :key="item2.id" :label="item2.name"
                                        :value="item2.id">
                                        <span style="float: left">{{ item2.name }}</span>
                                    </el-option>
                                </el-select>


                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Ngày hẹn<span class="text-danger">(*)</span></label>

                                <el-date-picker class="w-100" v-model="schedule.next_time_manual" type="datetime"
                                    format="dd-MM-yyyy HH:mm:ss" placeholder="Chọn thời gian">
                                </el-date-picker>


                            </div>
                        </div>

                    </div>
                    <div class="card-toolbar">
                        <el-button native-type="submit" type="btn btn-success mr-2" :loading="loading">Thêm mới
                        </el-button>
                    </div>
                </form>
            </div>
        </div>
    </ValidationObserver>
</template>

<script>
import { Money } from 'v-money';
import { SET_BREADCRUMB } from "@/core/services/store/breadcrumbs.module";
import { VEHICLE_GET_ALL, MAINTENANCE_TYPE_GET_ALL, MAINTENANCE_SCHEDULE_CREATE, MAINTENANCE_SCHEDULE_GET_ALL } from "@/core/services/store/vehicle.module";
import moment from "moment";
import ErrorMessage from "../../pages/common/ErrorMessage";
import { getApiMessage, getApiValidationErrors } from "@/utils/apiErrorHandler";



export default {
    name: "MaintenanceScheduleCreate",
    components: {
        ErrorMessage

    },
    data() {
        return {
            vehicles: [],
            money: {
                decimal: ',',
                thousands: ',',
                prefix: '',
                suffix: ' VNĐ',
                precision: 0,
                masked: false,

            },
            schedule: {
                vehicle_id: "",
                maintenance_type_id: "",
                next_time_manual: new Date(),

            },
            loading: false,
            maintenance_types: []
        }
    },
    mounted() {

        this.getListVehicles();
        this.getMaintenanceTypes();
   
    },
    components: { Money },
    methods: {
        convertToUTC(dateString) {
            // Parse the input date string
            let date = moment(dateString);

            // Explicitly set the timezone to GMT+7
            date.tz('Asia/Bangkok');

            // Convert to UTC
            let utcDate = date.utc().format('YYYY-MM-DD HH:mm:ss');

            return utcDate;
        },
        convertToGMTPlus7(utcDateString) {
            // Parse the input UTC date string
            let date = moment.utc(utcDateString);

            // Set the timezone to GMT+7 (Indochina Time)
            date.tz('Asia/Bangkok');

            // Format the date in GMT+7 timezone
            let gmtPlus7Date = date.format('YYYY-MM-DD HH:mm:ss');

            return gmtPlus7Date;
        },
        async getMaintenanceTypes() {
            this.$store.dispatch(MAINTENANCE_TYPE_GET_ALL, { 'is_all': true }).then((data) => {
                this.maintenance_types = data?.data || [];
            }).catch(() => {});
        },
        async getListVehicles() {
            let params = {
                is_all: true,
                compact: 1,
            };
            await this.$store.dispatch(VEHICLE_GET_ALL, params).then((data) => {
                this.vehicles = data?.data || [];
            }).catch(() => {});
        },
        prepareParams() {

            const next_time_manual = this.convertToUTC(this.schedule.next_time_manual);
            return { ...this.schedule, next_time_manual }
        },
        onSubmit: function () {
            this.loading = true;
            const params = this.prepareParams();
            this.$store.dispatch(MAINTENANCE_SCHEDULE_CREATE, params).then((res) => {

                this.noticeMessage('success', 'Thành công', res.message);
                this.$emit('createSuccess');

            }).catch((e) => {
                const errors = getApiValidationErrors(e);
                if (errors) {
                    this.$refs.form.setErrors(errors);
                } else {
                    this.noticeMessage('error', 'Thất bại', getApiMessage(e));
                }
            }).finally(() => this.loading = false);
        },
    }

}
</script>

<style scoped></style>
