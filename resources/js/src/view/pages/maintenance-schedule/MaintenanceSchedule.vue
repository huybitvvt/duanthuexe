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
                                    <el-input clearable placeholder="Tên xe, biển số"
                                        v-model="query.keyword"></el-input>
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
                                <el-button :loading="loading" icon="fa fa-search"
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
                                            <a v-if="currentUser.role_id === 1" title="Xóa" @click="deleteItem(item.id)"
                                                href="javascript:" class="btn btn-xs btn-icon btn-outline-danger"><i
                                                class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <HimotoEmptyState v-else icon="fas fa-tools" title="Không có lịch hẹn bảo dưỡng nào" description="Thử thay đổi bộ lọc tìm kiếm hoặc tạo lịch hẹn bảo dưỡng mới." actionText="Tạo mới lịch hẹn" @action="openModalCreate()" />
                    </div>
                </div>
                <b-modal title="Tạo mới" size="xl" ref="modal-create" :centered="true" :scrollable="true" hide-footer>
                    <maintenance-schedule-create @createSuccess="createSuccess"></maintenance-schedule-create>
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
import { MAINTENANCE_LOG_DELETE, MAINTENANCE_LOG_INDEX, MAINTENANCE_SCHEDULE_DELETE, MAINTENANCE_SCHEDULE_INDEX } from "../../../core/services/store/vehicle.module";
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
    },
    activated() {
        const queryPage = +this.$route?.query?.page || 1;
        const queryKeyword = this.$route?.query?.keyword || '';
        const paramsChanged = queryPage !== this.page || queryKeyword !== (this.query.keyword || '');
        const isTtlExpired = !this.lastFetchedAt || (Date.now() - this.lastFetchedAt > 60000);

        if (paramsChanged) {
            this.page = queryPage;
            this.query.keyword = queryKeyword;
            this.getList();
        } else if (isTtlExpired) {
            this.getList();
        }
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
