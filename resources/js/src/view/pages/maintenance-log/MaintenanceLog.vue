<template>
    <div class="card card-custom gutter-b">
        <div class="card-header">
            <div class="card-title">
                <h3 class="card-label">Lịch sử bảo dưỡng</h3>
            </div>
            <div class="card-title">
                <button class="btn btn-success" @click="openModalCreate()">Thêm mới</button>
            </div>
        </div>
        <div>
            <div class="card card-custom gutter-b">
                <div class="card-body">

                    <div class="example mb-10">
                        <div class="row mb-10">
                            <div class="col-md-2">
                                <div class="form-group">

                                    <el-input clearable placeholder="Tên xe, biển số" v-model="query.name"></el-input>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <el-date-picker class="w-100" v-model="query.start_date" type="date" format="yyyy-MM-dd"
                                    value-format="yyyy-MM-dd" :picker-options="pickerStartOptions"
                                    placeholder="Từ ngày">
                                </el-date-picker>
                            </div>
                            <div class="col-md-2">
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
                        <div class="example-preview table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th scope="col">Mã</th>
                                        <th scope="col">Xe</th>
                                        <th scope="col">Loại bảo dưỡng</th>
                                        <th scope="col">Ngày bảo dưỡng</th>
                                        <th scope="col">Ghi chú</th>


                                        <th scope="col">Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(item, index) in maintenanceLogs" :key="`log-${index}`">
                                        <td>{{ item.id }}</td>
                                        <td>{{ item.name }} {{ item.license }}</td>
                                        <td>{{ item.maintenance_type.name }}</td>
                                        <td>
                                            {{
                                                convertToGMTPlus7(item.maintenance_at) | formatDateTime
                                            }}
                                        </td>


                                        <td>
                                            <el-tooltip :content="item.note">
                                                <span>{{
                                                    getNote(item.note) || 'Không có ghi chú'
                                                }}</span>
                                            </el-tooltip>
                                        </td>
                                        <td>

                                            <a v-if="currentUser.role_id === 1" title="Xóa" @click="deleteLog(item.id)"
                                                href="javascript:" class="btn btn-xs btn-icon btn-outline-danger"><i
                                                    class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <b-modal title="Tạo mới" size="xl" ref="modal-create" :centered="true" :scrollable="true" hide-footer>
                    <log-create @createSuccess="createSuccess"></log-create>
                </b-modal>



                <div class="edu-paginate mx-auto text-center">
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
import { MAINTENANCE_LOG_DELETE, MAINTENANCE_LOG_INDEX } from "../../../core/services/store/vehicle.module";
import LogCreate from "./LogCreate";
import { SET_BREADCRUMB } from "@/core/services/store/breadcrumbs.module";
import { getTextShort } from "../../../utils";
import Swal from "sweetalert2";
import moment from "moment-timezone";
import queryMixin from '@/utils/queryMixin.js';


export default {
    mixins: [queryMixin],
    name: "MaintenanceLog",
    components: {
        LogCreate,
    },
    data() {
        const { page, store_id, ...restQuery } = this.$route?.query || {};
        return {
            showModalCreate: false,
            moment: moment,

            page: +restQuery?.page || 1,
            last_page: 1,
            maintenanceLogs: [],
            stats: null,
            loading: false,
            showDetail: "",

            loading: false,
            query: {

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
        this.$store.dispatch(SET_BREADCRUMB, [{ title: "Lịch sử bảo dưỡng" }]);
    },
    methods: {
        openModalCreate() {
            this.showModalCreate = true;
            this.$refs['modal-create'].show();

        },

        search() {
            // this.pushParamsUrl();
            this.getList();

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
            this.$store
                .dispatch(MAINTENANCE_LOG_INDEX, {
                    page: this.page,
                    ...this.query,
                })
                .then((data) => {
                    this.maintenanceLogs = data.data;
                    this.last_page = data.last_page;
                })
                .finally(() => {
                    this.loading = false;
                });
        },
        clickCallback(obj) {
            this.page = obj;
            this.$router.push({
                path: "",
                query: { page: this.page },
            });
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

        deleteLog(id) {
            Swal.fire({
                title: "Bạn chắc chắn muốn huỷ?",
                showDenyButton: true,
                showCancelButton: true,
                confirmButtonText: "Đồng ý",
                cancelButtonText: "Không",
            }).then((result) => {
                if (result.isConfirmed) {
                    this.$store.dispatch(MAINTENANCE_LOG_DELETE, id).then(() => {
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
