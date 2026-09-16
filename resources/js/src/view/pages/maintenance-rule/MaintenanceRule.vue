<template>
    <div>
        <div class="card card-custom gutter-b">
            <div class="card-header">
                <div class="card-title">
                    <h3 class="card-label">Tần suất chung</h3>
                </div>
            </div>

            <div class="card-body">
                <div class="example">
                    <div class="table-responsive">
                        <table class="table table-vertical-center table-hover table-bordered">
                            <thead>
                                <tr class="text-center">
                                    <th scope="col">Loại bảo dưỡng</th>
                                    <th scope="col">Tần suất bảo dưỡng (ngày)</th>
                                
                                    <th scope="col">Hành động</th>
                                </tr>
                            </thead>
                            <tbody v-if="maintenanceRules.length">

                                <tr v-for="(item, index) in maintenanceRules" :key="index">
                                    <td>
                                        <ValidationProvider vid="type" name="Loại bảo dưỡng"
                                            rules="required" v-slot="{ errors, classes }">
                                            <el-select filterable class="w-100" placeholder="Loại bảo dưỡng"
                                                v-model="item.maintenance_type_id" clearable :class="classes">
                                                <el-option v-for="item2 in maintenance_types" :key="item2.id"
                                                    :label="item2.name" :value="item2.id">
                                                    <span style="float: left">{{ item2.name }}</span>
                                                </el-option>
                                            </el-select>
                                            <div class="fv-plugins-message-container">
                                                <div data-field="type" data-validator="notEmpty"
                                                    class="fv-help-block">{{
                                                        errors[0]
                                                    }}
                                                </div>
                                            </div>
                                        </ValidationProvider>

                                    </td>

                                    <td>
                                        <ValidationProvider vid="value" name="Tần suất bảo dưỡng (ngày)"
                                            rules="required|numeric" v-slot="{ errors, classes }">
                                            <el-input placeholder="Tần suất bảo dưỡng (ngày)" v-model="item.value"
                                                :class="classes"></el-input>
                                            <div class="fv-plugins-message-container">
                                                <div data-field="value" data-validator="notEmpty" class="fv-help-block">
                                                    {{
                                                        errors[0]
                                                    }}
                                                </div>
                                            </div>
                                        </ValidationProvider>

                                    </td>
                                  
                                    <td class="text-center">
                                        <button class="btn btn-xs btn-outline-danger font-weight-bold" title="Xóa cài đặt"
                                            @click="deleteRule(item.id, index)">Xóa</button>
                                    </td>
                                </tr>
                            </tbody>
                            <tbody v-else>
                                <tr class="text-center">
                                    <td scope="row" colspan="6">Chưa có tần suất bảo dưỡng chung</td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="card-toolbar mt-3 d-flex justify-content-center" v-if="currentUser.role_id === 1">
                            <el-button native-type="button" class="btn btn-sm btn-info mr-2" @click="addNewRule"
                                style="color: #fff">
                                Thêm mới
                            </el-button>
                            <el-button native-type="submit" class="btn btn-sm btn-success mr-2" style="color: #fff"
                                @click="updateRule" :loading="loadingComplete1">
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
import { Money } from 'v-money';
import { mapGetters } from "vuex";
import { MAINTENANCE_RULE_CREATE, MAINTENANCE_RULE_DELETE, MAINTENANCE_RULE_UPDATE, MAINTENANCE_RULE_INDEX } from "@/core/services/store/vehicle.module";
import { SET_BREADCRUMB } from "@/core/services/store/breadcrumbs.module";
import { TYPE_VEHICLE } from "@/option/vehicle";
import { MAINTENANCE_TYPE_GET_ALL, MAINTENANCE_TYPE_CREATE, MAINTENANCE_TYPE_DELETE, MAINTENANCE_TYPE_UPDATE, MAINTENANCE_TYPE_INDEX } from "@/core/services/store/vehicle.module";

export default {
    name: "MaintenanceRule",
    data() {
        return {
            maintenance_types: [],

            moment: moment,
            maintenanceRules: [],
            loading: false,
            loadingComplete1: false,
            loadingComplete2: false,

            query: {},


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
    components: { Money },
    computed: {
        ...mapGetters(["currentUser"])
    },
    created() {

        this.getMaintenanceRules();
    },
    mounted() {
        this.$store.dispatch(SET_BREADCRUMB, [{ title: "Quản lý xe" }]);
        this.getMaintenanceTypes();
    },
    methods: {
        getMaintenanceTypes() {
            this.$store.dispatch(MAINTENANCE_TYPE_GET_ALL, { 'is_all': true }).then((data) => {
                this.maintenance_types = data.data;
            });
        },
        getMaintenanceRules() {
            this.$store.dispatch(MAINTENANCE_RULE_INDEX, {}).then(data => {
                this.maintenanceRules = data.data;
            });
        },
      
        addNewRule() {
            this.maintenanceRules.push({

                maintenance_type_id: '',
                value: 90,
               
            })
        },
        updateRule() {
            this.loadingComplete1 = true;
            let params = this.maintenanceRules;
            this.$store.dispatch(MAINTENANCE_RULE_UPDATE, params).then(() => {
                this.getMaintenanceRules();
                this.noticeMessage('success', 'Thành công', 'Cập nhật cài đặt thành công');
            }).catch((err) => {
                this.noticeMessage('error', 'Thất bại', err.data?.message);
            }).finally(() => this.loadingComplete1 = false);
        },
        deleteRule(ruleId, index) {
            this.$swal.fire({
                title: 'Bạn có chắc chắn muốn xóa cài đặt này?',
                showCancelButton: true,
                cancelButtonText: "Hủy",
                confirmButtonText: 'Xóa',
            }).then((result) => {
                if (result.isConfirmed) {
                    if (!ruleId) return this.maintenanceRules.splice(index, 1);
                    this.$store.dispatch(MAINTENANCE_RULE_DELETE, ruleId).then(() => {
                        this.getMaintenanceRules();
                    });
                    this.noticeMessage('success', 'Thành công', 'Xóa cài đặt thành công');
                }
            })
        },
        addNewType() {
            this.maintenance_types.push({

                name: '',
                note: '',

            })
        },
        updateType() {
            this.loadingComplete2 = true;
            let params = this.maintenance_types;
            this.$store.dispatch(MAINTENANCE_TYPE_UPDATE, params).then(() => {
                this.getMaintenanceRules();
                this.noticeMessage('success', 'Thành công', 'Cập nhật hình thức bảo dưỡng thành công');
            }).catch((err) => {
                this.noticeMessage('error', 'Thất bại', err.data?.message);
            }).finally(() => this.loadingComplete2 = false);
        },
        deleteType(ruleId, index) {
            this.$swal.fire({
                title: 'Bạn có chắc chắn muốn xóa loại bảo dưỡng này?',
                showCancelButton: true,
                cancelButtonText: "Hủy",
                confirmButtonText: 'Xóa',
            }).then((result) => {
                if (result.isConfirmed) {
                    if (!ruleId) return this.maintenance_types.splice(index, 1);
                    this.$store.dispatch(MAINTENANCE_TYPE_DELETE, ruleId).then(() => {
                        this.getMaintenanceTypes();
                    });
                    this.noticeMessage('success', 'Thành công', 'Xóa hình thức bảo dưỡng thành công');
                }
            })
        },
    }
}
</script>

<style scoped></style>
