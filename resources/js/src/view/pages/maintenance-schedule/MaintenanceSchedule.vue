<template>
    <div class="card card-custom gutter-b">
        <div class="card-header">
            <div class="card-title">
                <h3 class="card-label">Lịch hẹn bảo dưỡng</h3>
            </div>
            <div class="card-title">
                <button class="btn btn-success" @click="openModalCreate()">Thêm mới</button>
            </div>
        </div>
        <div>
            <div class="card card-custom gutter-b">
                <div class="card-body">

                    <div class="example mb-10">
                        <div class="row mb-10 search">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Xe</label>
                                    <search-suggest endpoint="/api/auth/maintenance-schedules" :params="query" query-key="keyword" fields="vehicle.name,vehicle.license" @select="search" @submit="search" clearable placeholder="Tên xe, biển số"
                                        v-model="query.keyword"></search-suggest>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label>Lịch hẹn</label>
                                <el-date-picker class="w-100" v-model="query.start_date" type="date" format="yyyy-MM-dd"
                                    value-format="yyyy-MM-dd" :picker-options="pickerStartOptions"
                                    placeholder="Từ ngày">
                                </el-date-picker>
                            </div>
                            <div class="col-md-3">
                                <label>Lịch hẹn</label>
                                <el-date-picker class="w-100" v-model="query.end_date" type="date" ref="picker"
                                    format="yyyy-MM-dd" value-format="yyyy-MM-dd" :picker-options="pickerEndOptions"
                                    placeholder="Đến ngày">
                                </el-date-picker>
                            </div>
                            <div class="col-md-3 text-right">
                                <el-button :loading="loading"
                                    class="btn btn-primary font-weight-bold" @click="search">
                                    Tìm kiếm
                                </el-button>

                            </div>
                        </div>
                        <HimotoErrorState v-if="errorMessage" title="Không thể tải lịch hẹn bảo dưỡng" :message="errorMessage" @retry="getList" />
                        <HimotoTableSkeleton v-else-if="loading" :rows="5" :columns="7" />
                        <div v-else-if="schedules.length" class="example-preview table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th scope="col">Mã</th>
                                        <th scope="col">Xe</th>
                                        <th scope="col">Loại bảo dưỡng</th>
                                        <th scope="col">Lịch hẹn (thủ công)</th>
                                        <th scope="col">Lịch hẹn (tự động)</th>
                                        <th scope="col">Ngày tạo</th>
                                        <th scope="col">Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(item, index) in schedules" :key="`log-${item.id || index}`">
                                        <td>{{ item.id }}</td>
                                        <td>{{ item.vehicle ? item.vehicle.name : '' }} {{ item.vehicle ?
                                            item.vehicle.license : '' }}</td>
                                        <td>{{ item.maintenance_type ? item.maintenance_type.name : '' }}</td>
                                        <td>
                                            {{
                                                item.next_time_manual ? convertToGMTPlus7(item.next_time_manual
                                                ) : '' | formatDateTime
                                            }}
                                        </td>
                                        <td>
                                            {{
                                                item.next_time_auto ? convertToGMTPlus7(item.next_time_auto
                                                ) : '' | formatDateTime
                                            }}
                                        </td>
                                        <td>
                                            {{
                                                convertToGMTPlus7(item.created_at) | formatDateTime
                                            }}
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <button type="button" class="btn btn-xs btn-outline-info mr-1" title="Xem chi tiết" @click="viewItem(item)">
                                                    Xem
                                                </button>
                                                <button type="button" class="btn btn-xs btn-outline-primary mr-1" title="Chỉnh sửa" @click="editItem(item)">
                                                    Sửa
                                                </button>
                                                <button v-if="currentUser.role_id === 1" type="button" class="btn btn-xs btn-outline-danger" title="Xóa" @click="deleteItem(item.id)">
                                                    Xóa
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <HimotoEmptyState v-else title="Không có lịch hẹn bảo dưỡng nào" description="Thử thay đổi bộ lọc tìm kiếm hoặc tạo lịch hẹn bảo dưỡng mới." actionText="Tạo mới lịch hẹn" @action="openModalCreate()" />
                    </div>
                </div>
                <!-- Modal Tạo mới -->
                <b-modal title="Tạo mới" size="xl" ref="modal-create" :centered="true" :scrollable="true" hide-footer>
                    <maintenance-schedule-create @createSuccess="createSuccess"></maintenance-schedule-create>
                </b-modal>

                <!-- Modal Xem chi tiết -->
                <b-modal title="Chi tiết lịch hẹn bảo dưỡng" size="lg" ref="modal-view" :centered="true" hide-footer>
                    <div v-if="viewItemData" class="p-2">
                        <div class="row mb-3">
                            <div class="col-sm-6 mb-2">
                                <span class="text-muted d-block font-size-sm">Mã lịch hẹn:</span>
                                <strong class="font-size-h6 text-dark">#{{ viewItemData.id }}</strong>
                            </div>
                            <div class="col-sm-6 mb-2">
                                <span class="text-muted d-block font-size-sm">Trạng thái hạn:</span>
                                <span :class="`badge badge-${getScheduleStatus(viewItemData).badge} font-weight-bold`">
                                    {{ getScheduleStatus(viewItemData).text }}
                                </span>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-6 mb-2">
                                <span class="text-muted d-block font-size-sm">Xe bảo dưỡng:</span>
                                <span class="font-weight-bold font-size-base text-dark">
                                    {{ viewItemData.vehicle ? viewItemData.vehicle.name : 'Xe #' + viewItemData.vehicle_id }}
                                </span>
                                <span v-if="viewItemData.vehicle && viewItemData.vehicle.license" class="badge badge-secondary ml-2 font-weight-bold">
                                    {{ viewItemData.vehicle.license }}
                                </span>
                            </div>
                            <div class="col-sm-6 mb-2">
                                <span class="text-muted d-block font-size-sm">Loại bảo dưỡng:</span>
                                <strong class="text-primary font-size-base">
                                    {{ viewItemData.maintenance_type ? viewItemData.maintenance_type.name : '---' }}
                                </strong>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-6 mb-2">
                                <span class="text-muted d-block font-size-sm">Lịch hẹn (thủ công):</span>
                                <strong class="text-dark">
                                    {{ viewItemData.next_time_manual ? convertToGMTPlus7(viewItemData.next_time_manual) : '---' | formatDateTime }}
                                </strong>
                            </div>
                            <div class="col-sm-6 mb-2">
                                <span class="text-muted d-block font-size-sm">Lịch hẹn (tự động):</span>
                                <span class="text-dark">
                                    {{ viewItemData.next_time_auto ? convertToGMTPlus7(viewItemData.next_time_auto) : 'Không có' | formatDateTime }}
                                </span>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-6 mb-2">
                                <span class="text-muted d-block font-size-sm">Ngày tạo:</span>
                                <span class="text-muted">
                                    {{ convertToGMTPlus7(viewItemData.created_at) | formatDateTime }}
                                </span>
                            </div>
                            <div class="col-sm-6 mb-2">
                                <span class="text-muted d-block font-size-sm">Ngày cập nhật:</span>
                                <span class="text-muted">
                                    {{ convertToGMTPlus7(viewItemData.updated_at) | formatDateTime }}
                                </span>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end mt-4 pt-3 border-top">
                            <button type="button" class="btn btn-secondary mr-2" @click="$refs['modal-view'].hide()">Đóng</button>
                            <button type="button" class="btn btn-primary" @click="switchToEdit(viewItemData)">
                                Sửa lịch hẹn này
                            </button>
                        </div>
                    </div>
                </b-modal>

                <!-- Modal Sửa -->
                <b-modal title="Sửa lịch hẹn bảo dưỡng" size="lg" ref="modal-edit" :centered="true" hide-footer>
                    <div v-if="editItemData" class="p-2">
                        <form @submit.prevent="submitEdit">
                            <div class="form-group mb-4">
                                <label class="font-weight-bold">Chọn xe <span class="text-danger">(*)</span></label>
                                <el-select v-model="editItemData.vehicle_id" clearable filterable class="w-100" placeholder="Chọn xe">
                                    <el-option v-for="v in vehicles" :key="v.id" :label="`${v.name} (${v.license})`" :value="v.id">
                                        <span style="float: left">{{ v.name }}</span>
                                        <span style="float: right; color: #8492a6; font-size: 13px;">{{ v.license }}</span>
                                    </el-option>
                                </el-select>
                            </div>
                            <div class="form-group mb-4">
                                <label class="font-weight-bold">Loại bảo dưỡng <span class="text-danger">(*)</span></label>
                                <el-select v-model="editItemData.maintenance_type_id" filterable class="w-100" placeholder="Chọn loại bảo dưỡng" clearable>
                                    <el-option v-for="t in maintenance_types" :key="t.id" :label="t.name" :value="t.id">
                                        <span>{{ t.name }}</span>
                                    </el-option>
                                </el-select>
                            </div>
                            <div class="form-group mb-4">
                                <label class="font-weight-bold">Ngày hẹn (thủ công) <span class="text-danger">(*)</span></label>
                                <el-date-picker class="w-100" v-model="editItemData.next_time_manual" type="datetime" format="dd-MM-yyyy HH:mm:ss" placeholder="Chọn thời gian hẹn">
                                </el-date-picker>
                            </div>
                            <div class="d-flex justify-content-end mt-4 pt-3 border-top">
                                <button type="button" class="btn btn-secondary mr-2" @click="$refs['modal-edit'].hide()">Hủy</button>
                                <el-button type="primary" :loading="editLoading" native-type="submit">
                                    Lưu cập nhật
                                </el-button>
                            </div>
                        </form>
                    </div>
                </b-modal>

                <div class="edu-paginate mx-auto text-center" v-if="!loading && schedules.length">
                    <paginate v-model="page" :page-count="last_page" :page-range="3" :margin-pages="1"
                        :click-handler="clickCallback" :prev-text="'Trước'" :next-text="'Sau'"
                        :container-class="'pagination b-pagination'" :pageLinkClass="'page-link'"
                        :next-link-class="'next-link-order'" :prev-link-class="'prev-link-order'"
                        :prev-class="'page-link'" :next-class="'page-link'" :page-class="'page-order'">
                    </paginate>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { mapGetters } from "vuex";
import {
    MAINTENANCE_LOG_DELETE,
    MAINTENANCE_LOG_INDEX,
    MAINTENANCE_SCHEDULE_DELETE,
    MAINTENANCE_SCHEDULE_INDEX,
    MAINTENANCE_SCHEDULE_UPDATE,
    VEHICLE_GET_ALL,
    MAINTENANCE_TYPE_GET_ALL
} from "../../../core/services/store/vehicle.module";
import MaintenanceScheduleCreate from "../maintenance-schedule/MaintenanceScheduleCreate";
import { SET_BREADCRUMB } from "@/core/services/store/breadcrumbs.module";
import { getTextShort } from "../../../utils";
import Swal from "sweetalert2";
import moment from "moment-timezone";
import queryMixin from '@/utils/queryMixin.js';
import HimotoTableSkeleton from "@/view/components/himoto/HimotoTableSkeleton.vue";
import HimotoEmptyState from "@/view/components/himoto/HimotoEmptyState.vue";
import HimotoErrorState from "@/view/components/himoto/HimotoErrorState.vue";
import { normalizePaginator } from "@/utils/paginatorAdapter";
import { getApiMessage } from "@/utils/apiErrorHandler";

export default {
    name: "MaintenanceSchedule",
    mixins: [queryMixin],
    components: {
        MaintenanceScheduleCreate,
        HimotoTableSkeleton,
        HimotoEmptyState,
        HimotoErrorState
    },
    data() {
        const { page, store_id, ...restQuery } = this.$route?.query || {};
        return {
            showModalCreate: false,
            moment: moment,

            viewItemData: null,
            editItemData: null,
            editLoading: false,
            vehicles: [],
            maintenance_types: [],

            page: +page || +restQuery?.page || 1,
            last_page: 1,
            schedules: [],
            stats: null,
            loading: false,
            errorMessage: null,
            lastFetchedAt: 0,
            showDetail: "",

            query: {
                keyword: "",
                start_date: "",
                end_date: "",

                ...(restQuery || {}),
            },
            pickerStartOptions: {},
            pickerEndOptions: {},
            isFirstActivated: true,
        };
    },
    created() {
        this.getList();
    },
    computed: {
        ...mapGetters(["currentUser"]),
    },
    mounted() {
        this.$store.dispatch(SET_BREADCRUMB, [{ title: "Lịch hẹn bảo dưỡng" }]);
        this.fetchVehiclesAndTypes();
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
    },
    methods: {
        openModalCreate() {
            this.showModalCreate = true;
            this.$refs['modal-create'].show();

        },

        search() {
            this.page = 1;
            this.pushParamsUrl();
            this.getList();

        },
        pushParamsUrl() {
            this.$router.push({
                path: "",
                query: {
                    page: this.page,
                    ...this.query,
                },
            }).catch(() => {});
        },
        formatValue(...values) {
            let res = values.reduce((acc, item) => {
                acc += item || 0;
                return acc;
            }, 0);

            return res;
        },

        getNote(str) {
            return getTextShort(str);
        },
        getList() {
            this.loading = true;
            this.errorMessage = null;
            this.$store
                .dispatch(MAINTENANCE_SCHEDULE_INDEX, {
                    page: this.page,
                    ...this.query,
                })
                .then((data) => {
                    const paginated = normalizePaginator(data);
                    this.schedules = paginated.items || [];
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
        clickCallback(obj) {
            this.page = obj;
            this.pushParamsUrl();
            this.getList();
        },

        createSuccess() {
            this.showModalCreate = false;
            this.$refs['modal-create'].hide();
            this.getList();

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
        convertToUTC(dateString) {
            let date = moment(dateString);
            date.tz('Asia/Bangkok');
            return date.utc().format('YYYY-MM-DD HH:mm:ss');
        },
        async fetchVehiclesAndTypes() {
            try {
                const [vRes, tRes] = await Promise.all([
                    this.$store.dispatch(VEHICLE_GET_ALL, { is_all: true, compact: 1 }),
                    this.$store.dispatch(MAINTENANCE_TYPE_GET_ALL, { is_all: true })
                ]);
                this.vehicles = vRes?.data || [];
                this.maintenance_types = tRes?.data || [];
            } catch (e) {
                console.warn("Failed to fetch vehicles or maintenance types:", e);
            }
        },
        viewItem(item) {
            this.viewItemData = item;
            this.$refs['modal-view'].show();
        },
        switchToEdit(item) {
            this.$refs['modal-view'].hide();
            this.editItem(item);
        },
        editItem(item) {
            this.editItemData = {
                id: item.id,
                vehicle_id: item.vehicle_id,
                maintenance_type_id: item.maintenance_type_id,
                next_time_manual: item.next_time_manual ? new Date(item.next_time_manual) : new Date()
            };
            this.$refs['modal-edit'].show();
        },
        submitEdit() {
            if (!this.editItemData.vehicle_id) {
                Swal.fire("Lỗi", "Vui lòng chọn xe bảo dưỡng", "error");
                return;
            }
            if (!this.editItemData.maintenance_type_id) {
                Swal.fire("Lỗi", "Vui lòng chọn loại bảo dưỡng", "error");
                return;
            }
            if (!this.editItemData.next_time_manual) {
                Swal.fire("Lỗi", "Vui lòng chọn ngày hẹn", "error");
                return;
            }
            this.editLoading = true;
            const params = {
                id: this.editItemData.id,
                vehicle_id: this.editItemData.vehicle_id,
                maintenance_type_id: this.editItemData.maintenance_type_id,
                next_time_manual: this.convertToUTC(this.editItemData.next_time_manual)
            };
            this.$store.dispatch(MAINTENANCE_SCHEDULE_UPDATE, params)
                .then((res) => {
                    Swal.fire("Thành công", res?.message || "Cập nhật lịch hẹn bảo dưỡng thành công", "success");
                    this.$refs['modal-edit'].hide();
                    this.getList();
                })
                .catch((e) => {
                    Swal.fire("Thất bại", getApiMessage(e) || "Cập nhật thất bại", "error");
                })
                .finally(() => {
                    this.editLoading = false;
                });
        },
        getScheduleStatus(item) {
            if (!item) return { text: '---', badge: 'secondary' };
            const dueDateStr = item.next_time_manual || item.next_time_auto;
            if (!dueDateStr) return { text: 'Chưa có lịch', badge: 'secondary' };
            const now = moment().tz('Asia/Bangkok').startOf('day');
            const due = moment(dueDateStr).tz('Asia/Bangkok').startOf('day');
            const diffDays = due.diff(now, 'days');
            if (diffDays < 0) {
                return { text: `Quá hạn ${Math.abs(diffDays)} ngày`, badge: 'danger' };
            } else if (diffDays === 0) {
                return { text: 'Đến hạn hôm nay', badge: 'warning' };
            } else if (diffDays <= 7) {
                return { text: `Còn ${diffDays} ngày`, badge: 'info' };
            } else {
                return { text: `Còn ${diffDays} ngày`, badge: 'success' };
            }
        },

        deleteItem(id) {
            Swal.fire({
                title: "Bạn chắc chắn muốn huỷ?",
                showDenyButton: true,
                showCancelButton: true,
                confirmButtonText: "Đồng ý",
                cancelButtonText: "Không",
            }).then((result) => {
                if (result.isConfirmed) {
                    this.$store.dispatch(MAINTENANCE_SCHEDULE_DELETE, id).then(() => {
                        Swal.fire("Xóa thành công", "", "success");
                        this.getList();
                    }).catch(() => {
                        this.noticeMessage('error', 'Thất bại', 'Xóa thất bại');
                    });
                }
            });

        },
    }

};
</script>

<style>
.search>* {
    align-self: flex-end;
}

.search .form-group {
    margin-bottom: 0;
}

.font-weight-bold {
    font-weight: 700 !important;
}

.el-collapse-item__header .el-collapse-item__arrow::before {
    content: "";
}

.el-collapse-item__header,
.el-collapse-item__wrap {
    border-bottom: none;
}

.el-collapse-item__header {
    font-weight: 700;
    cursor: pointer;
}

.table-content {
    max-height: 500px;
    overflow-y: scroll;
}
</style>
