<template>
    <div>
        <div class="card card-custom gutter-b">
            <div class="card-header align-items-center">
                <div class="card-title">
                    <h3 class="card-label">Danh sách xe</h3>
                </div>
                <div class="card-toolbar d-flex align-items-center">
                    <div class="btn-group btn-group-sm mr-3" role="group" aria-label="Chế độ hiển thị">
                        <button type="button" class="btn" :class="viewMode === 'table' ? 'btn-primary' : 'btn-secondary'" @click="switchView('table')">
                            <i class="fa fa-list mr-1"></i> Bảng
                        </button>
                        <button type="button" class="btn" :class="viewMode === 'grid' ? 'btn-primary' : 'btn-secondary'" @click="switchView('grid')">
                            <i class="fa fa-th-large mr-1"></i> Lưới thẻ
                        </button>
                    </div>
                    <ModalVehicleCreate @storeSuccess="getList"></ModalVehicleCreate>
                    <button class="btn btn-success ml-2" @click="exportFile">Export</button>
                </div>

            </div>
            <div class="alert alert-custom alert-white alert-shadow fade show gutter-b" role="alert">
                <div class="alert-icon">
                    <span class="svg-icon svg-icon-primary svg-icon-xl">
                        <!--begin::Svg Icon | path:/metronic/theme/html/demo1/dist/assets/media/svg/icons/Tools/Compass.svg-->
                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px"
                            height="24px" viewBox="0 0 24 24" version="1.1">
                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                <rect x="0" y="0" width="24" height="24"></rect>
                                <path
                                    d="M7.07744993,12.3040451 C7.72444571,13.0716094 8.54044565,13.6920474 9.46808594,14.1079953 L5,23 L4.5,18 L7.07744993,12.3040451 Z M14.5865511,14.2597864 C15.5319561,13.9019016 16.375416,13.3366121 17.0614026,12.6194459 L19.5,18 L19,23 L14.5865511,14.2597864 Z M12,3.55271368e-14 C12.8284271,3.53749572e-14 13.5,0.671572875 13.5,1.5 L13.5,4 L10.5,4 L10.5,1.5 C10.5,0.671572875 11.1715729,3.56793164e-14 12,3.55271368e-14 Z"
                                    fill="#000000" opacity="0.3"></path>
                                <path
                                    d="M12,10 C13.1045695,10 14,9.1045695 14,8 C14,6.8954305 13.1045695,6 12,6 C10.8954305,6 10,6.8954305 10,8 C10,9.1045695 10.8954305,10 12,10 Z M12,13 C9.23857625,13 7,10.7614237 7,8 C7,5.23857625 9.23857625,3 12,3 C14.7614237,3 17,5.23857625 17,8 C17,10.7614237 14.7614237,13 12,13 Z"
                                    fill="#000000" fill-rule="nonzero"></path>
                            </g>
                        </svg>
                        <!--end::Svg Icon-->
                    </span>
                </div>
                <div class="alert-text">
                    <div class="row">
                        <div class="col-md-3">
                            <p>
                                Số lượng xe:
                                <span class="font-weight-bold">{{
                                    reports.total_vehicle
                                    }}</span>
                            </p>
                            <p>
                                Phí đầu tư:
                                <span class="font-weight-bold">{{
                                    reports.total_price | formatPrice
                                    }}</span>
                            </p>
                        </div>
                        <div class="col-md-3">
                            <p>
                                Sẵn sàng:
                                <span class="font-weight-bold">{{
                                    reports.total_vehicle_ready
                                    }}</span>
                            </p>
                            <p>
                                Đang sử dụng:
                                <span class="font-weight-bold">{{
                                    reports.total_vehicle_using
                                    }}</span>
                            </p>
                        </div>
                        <div class="col-md-3">
                            <p>
                                Xe ga:
                                <span class="font-weight-bold">{{
                                    reports.total_vehicle_ga
                                    }}</span>
                            </p>
                            <p>
                                Xe số:
                                <span class="font-weight-bold">{{
                                    reports.total_vehicle_so
                                    }}</span>
                            </p>
                        </div>
                        <div class="col-md-3">
                            <p>
                                Xe côn:
                                <span class="font-weight-bold">{{
                                    reports.total_vehicle_con
                                    }}</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>


            <div class="card-body">

                <div class="row col-md-12 filter-row">
                    <div class="">


                        <el-input clearable placeholder="Tên xe, biển số" v-model="query.name"></el-input>

                    </div>
                    <div class=" ">


                        <el-select filterable class="w-100" placeholder="Trạng thái" v-model="query.status" clearable>
                            <el-option v-for="item in status" :key="item.id" :label="item.name" :value="item.id">
                                <span style="float: left">{{
                                    item.name
                                }}</span>
                            </el-option>
                        </el-select>

                    </div>
                    <div class=" ">


                        <el-select filterable class="w-100" placeholder="Cửa hàng" v-model="query.store_id" clearable>
                            <el-option v-for="item in stores" :key="item.id" :label="item.store_name" :value="item.id">
                                <span style="float: left">{{
                                    item.store_name
                                }}</span>
                            </el-option>
                        </el-select>

                    </div>

                    <div class=" ">


                        <el-select filterable class="w-100" placeholder="Loại xe" v-model="query.type" clearable>
                            <el-option v-for="item in types" :key="item.id" :label="item.name" :value="item.id">
                                <span style="float: left">{{
                                    item.name
                                }}</span>
                            </el-option>
                        </el-select>

                    </div>

                    <div class=" ">


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
                    <div class=" ">


                        <el-select filterable class="w-100" placeholder="Tình trạng bảo dưỡng"
                            v-model="query.maintenance_status" clearable>
                            <el-option v-for="item in maintenance_status" :key="item.id" :label="item.name"
                                :value="item.id">
                                <span style="float: left">{{
                                    item.name
                                }}</span>
                            </el-option>
                        </el-select>

                    </div>

                    <div>


                        <el-button :loading="loading" icon="fa fa-search" class=" btn btn-primary font-weight-bold mr-2"
                            @click="search">
                            Tìm kiếm
                        </el-button>
                    </div>
                </div>

                <!-- Skeleton Loading -->
                <div v-if="loading && (!vehicles.data || vehicles.data.length === 0)" class="mt-4">
                    <HimotoTableSkeleton v-if="viewMode === 'table'" :rows="6" :cols="10" />
                    <HimotoCardSkeleton v-else :count="6" />
                </div>

                <!-- Empty State -->
                <div v-else-if="!loading && (!vehicles.data || vehicles.data.length === 0)">
                    <HimotoEmptyState
                        title="Không tìm thấy phương tiện"
                        description="Không có xe nào phù hợp với điều kiện tìm kiếm và bộ lọc hiện tại."
                    />
                </div>

                <!-- Content Area -->
                <div v-else class="example mb-10 mt-4">
                    <!-- Table View -->
                    <div v-if="viewMode === 'table'" class="example-preview table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
									<th scope="col" class="min-w-130px">
                                        Hình ảnh
                                    </th>
                                    <th scope="col" class="min-w-130px">
                                        Ngày tạo
                                    </th>
                                    <th scope="col">Tên</th>
                                    <th scope="col">Biển số</th>
                                    <th scope="col" class="min-w-120px">
                                        Loại xe - Đời xe
                                    </th>
                                    <th scope="col">Giá mua</th>
                                    <th scope="col" class="min-w-120px">
                                        Giá bán
                                    </th>
                                    <th scope="col" class="min-w-120px">
                                        Mục đích sử dụng
                                    </th>
									<th scope="col" class="min-w-120px">
                                        Số km hiện tại
                                    </th>
                                    <th scope="col" class="min-w-150px">
                                        Cửa hàng
                                    </th>
                                    <th scope="col">Trạng thái</th>

                                    <th scope="col">Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in vehicles.data" :key="item.id || index">
                                    <th scope="row">{{ index + 1 }}</th>
                                    <th scope="row">
										<el-image
											v-if="getFirstImage(item)"
											style="width: 80px; height: 80px; border-radius: 8px;"
											:src="getFirstImage(item)" 
											:preview-src-list="getItemImages(item)">
										</el-image>
									</th>
                                    <td>{{ item.created_at | formatDate }}</td>
                                    <td>
                                        <span class="font-weight-bold">{{ item.name }}<br /></span>
                                    </td>
                                    <td><span class="badge badge-primary font-weight-bold">{{ item.license || item.license_plate }}</span></td>
                                    <td>{{ type_define[item.type] || item.type }} - {{ item.year }}</td>
                                    <td>{{ item.cost_price | formatPrice }}</td>
                                    <td>{{ item.sale_price | formatPrice }}</td>
                                    <td>
                                        <span :class="typeOfServiceDefineCss[
                                            item.type_of_service_id
                                        ]
                                            ">{{
                                                type_of_service[
                                                item.type_of_service_id
                                                ]
                                            }}</span>
                                    </td>
									<td>
                                        <span class="font-weight-bold">{{ item.odometer != null ? item.odometer + ' km' : '-' }}</span>
                                    </td>
                                    <td>
                                        <span v-if="item.store || item.store_name" class="label label-info label-inline mr-2">{{
                                            item.store ? item.store.store_name : item.store_name }}</span>
                                    </td>
                                    <td>
                                        <span class="status-badge" :class="item.status_css || item.status">
                                            {{ item.status_label || status_define[item.status] || item.status }}
                                        </span>
                                    </td>

                                    <td>
                                        <div class="d-flex align-items-center gap-1">
                                            <button v-b-modal.modal-show-car-rental
                                                class="btn btn-xs btn-icon btn-outline-info" title="Xem chi tiết"
                                                @click="showPopup(item)">
                                                <i class="far fa-eye"></i>
                                            </button>
                                            <button v-b-modal.modal-vehicle-edit @click="item_current = item"
                                                class="btn btn-xs btn-icon btn-outline-info" title="Sửa">
                                                <i class="far fa-edit"> </i>
                                            </button>
                                            <button class="btn btn-xs btn-icon btn-outline-primary" title="Tạo đơn thuê"
                                                @click="$router.push('/car-rental?vehicle_id=' + item.id)">
                                                <i class="fa fa-file-contract"></i>
                                            </button>
                                            <a v-if="currentUser.role_id === 1" title="Xóa" @click="deleteVehicle(item.id)" href="javascript:"
                                                class="btn btn-xs btn-icon btn-outline-danger"><i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Grid View -->
                    <div v-else class="himoto-vehicle-grid">
                        <div v-for="(item, index) in vehicles.data" :key="item.id || index" class="vehicle-card-item">
                            <div class="vehicle-card-thumb">
                                <img :src="getFirstImage(item) || '/media/vehicles/default-moto.png'" alt="Vehicle Image" class="vehicle-img" />
                                <span class="status-badge" :class="item.status_css || item.status">
                                    {{ item.status_label || status_define[item.status] || item.status }}
                                </span>
                            </div>
                            <div class="vehicle-card-content">
                                <div class="vehicle-license-pill">{{ item.license || item.license_plate }}</div>
                                <h4 class="vehicle-card-name">{{ item.name }}</h4>
                                <div class="vehicle-meta-row">
                                    <span class="meta-label">Chi nhánh:</span>
                                    <span class="meta-val font-weight-bold">{{ item.store ? item.store.store_name : (item.store_name || '-') }}</span>
                                </div>
                                <div class="vehicle-meta-row" v-if="item.odometer != null">
                                    <span class="meta-label">Số km (ODO):</span>
                                    <span class="meta-val font-weight-bold text-primary">{{ item.odometer }} km</span>
                                </div>
                                <div class="vehicle-card-actions">
                                    <button class="btn btn-sm btn-secondary" @click="showPopup(item)" v-b-modal.modal-show-car-rental>
                                        <i class="far fa-eye mr-1"></i> Chi tiết
                                    </button>
                                    <button class="btn btn-sm btn-secondary" @click="item_current = item" v-b-modal.modal-vehicle-edit>
                                        <i class="far fa-edit mr-1"></i> Sửa
                                    </button>
                                    <button class="btn btn-sm btn-primary ml-auto" @click="$router.push('/car-rental?vehicle_id=' + item.id)">
                                        <i class="fa fa-file-contract mr-1"></i> Thuê
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <ModalView :vehicle="vehicle_show"></ModalView>
            <ModalVehicleEdit :item="item_current" @storeSuccess="getList"></ModalVehicleEdit>
            <div class="edu-paginate mx-auto text-center">
                <paginate v-model="page" :page-count="last_page" :page-range="3" :margin-pages="1"
                    :click-handler="clickCallback" :prev-text="'Trước'" :next-text="'Sau'"
                    :container-class="'pagination b-pagination'" :pageLinkClass="'page-link'"
                    :next-link-class="'next-link-item'" :prev-link-class="'prev-link-item'" :prev-class="'page-link'"
                    :next-class="'page-link'" :page-class="'page-item'">
                </paginate>
            </div>
        </div>
    </div>
</template>

<script>
import { SET_BREADCRUMB } from "@/core/services/store/breadcrumbs.module";
import { EXPORT_VEHICLES } from "@/core/services/store/exports.module";
import { mapGetters } from "vuex";
import {
    VEHICLE_DELETE,
    VEHICLE_GET_ALL,
    VEHICLE_GET_ALL_REPORT,
} from "../../../core/services/store/vehicle.module";
import ModalVehicleCreate from "./ModalVehicleCreate";
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
} from "../../../option/vehicle";
import { STORE_GET_ALL } from "../../../core/services/store/store.module";
import ModalVehicleEdit from "./ModalVehicleEdit";
import queryMixin from '@/utils/queryMixin.js';
import ModalView from "./ModalView";
import Swal from "sweetalert2";
import HimotoTableSkeleton from "@/view/components/himoto/HimotoTableSkeleton.vue";
import HimotoCardSkeleton from "@/view/components/himoto/HimotoCardSkeleton.vue";
import HimotoEmptyState from "@/view/components/himoto/HimotoEmptyState.vue";
import { adaptVehicle, normalizePaginator } from "@/utils/paginatorAdapter";
import { getApiMessage } from "@/utils/apiErrorHandler";

export default {
    mixins: [queryMixin],
    name: "VehicleIndex",
    components: {
        ModalVehicleEdit,
        ModalVehicleCreate,
        ModalView,
        HimotoTableSkeleton,
        HimotoCardSkeleton,
        HimotoEmptyState
    },
    data() {
        const { page, store_id, type_of_service_id, ...restQuery } =
            this.$route?.query || {};

        return {

            vehicle_show: null,
            query: {
                name: "",
                status: "",
                store_id: store_id ? +store_id : "",
                created_at: "",
                type: "",
                type_of_service_id: type_of_service_id
                    ? +type_of_service_id
                    : "",
                ...(restQuery || {}),
            },
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
            status_define_css: status_define_css,
            status_define: status_define,
            loading: false,
            viewMode: this.$route?.query?.view || 'table',
            errorMessage: null,
            item_current: null,
            maintenance_status: [
                {
                    id: 1,
                    name: "Đã quá hạn"
                },
                {
                    id: 2,
                    name: "Còn 3 ngày"
                },
                {
                    id: 3,
                    name: "Còn 1 tuần"
                }
            ]
        };
    },
    computed: {
        ...mapGetters(["currentUser"]),
    },
  
    mounted() {
        this.getStore();
        this.$store.dispatch(SET_BREADCRUMB, [{ title: "Quản lý xe" }]);
        this.getList();
        this.getReport();
    },
    methods: {
		getFirstImage(item) {
			return item?.images && item?.images.length > 0 ? item?.images[0]?.url : null;
		},
		getItemImages(item) {
			let urls = item?.images.map(img => img?.url );
			return urls;
		},

        showPopup(item) {
            this.vehicle_show = item;

        },
        switchView(mode) {
            this.viewMode = mode;
            this.pushParamsUrl();
        },
        search() {
            this.pushParamsUrl();
            this.getList();
            this.getReport();
        },
        pushParamsUrl() {
            this.$router.push({
                path: "",
                query: {
                    page: this.page,
                    view: this.viewMode,
                    ...this.query,
                },
            }).catch(() => {});
        },
        getList() {
            this.loading = true;
            this.$store
                .dispatch(VEHICLE_GET_ALL, {
                    page: this.page,
                    ...this.query,
                })
                .then((data) => {
                    const paginated = normalizePaginator(data);
                    this.vehicles = {
                        ...paginated,
                        data: (paginated.items || []).map(adaptVehicle)
                    };
                    this.last_page = paginated.lastPage || 1;
                })
                .catch((err) => {
                    this.errorMessage = getApiMessage(err);
                })
                .finally(() => {
                    this.loading = false;
                });
        },
        getReport() {
            this.loading = true;
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
                title: "Bạn chắc chắn muốn xóa? Dữ liệu bị xóa sẽ không thể khôi phục lại.",
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
            this.loading = true;
            this.$store.dispatch(EXPORT_VEHICLES, this.query).then().catch((error) => {
                this.noticeMessage('error', 'Thất bại', error.message);
            }).finally(() => {
                this.loading = false;
            })
        },
        convertDaysToWeeksAndMonths(days) {
            if (!days) {
                return '';
            }

            const absoluteDays = Math.abs(days);
            const months = Math.floor(absoluteDays / 30);
            const weeks = Math.floor((absoluteDays % 30) / 7);
            const remainingDays = absoluteDays % 7;

            let result = '';

            if (months > 0) {
                result += `${months} tháng `;
            }

            if (weeks > 0) {
                result += `${weeks} tuần `;
            }

            if (remainingDays > 0) {
                result += `${remainingDays} ngày`;
            }

            if (!result) {
                result = 'ngày';
            }

            if (days < 0) {
                result = `Quá hạn: ${result}`;
            }

            return result.trim();
        },
    },
};
</script>

<style scoped>
.mx-datepicker {
    width: 100% !important;
}
.vehicle-image {
	width: 130px;
	height: 130px;
	object-fit: cover;
	border: 1px solid #E9EDF3;
}
.himoto-vehicle-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    margin-top: 16px;
}
.vehicle-card-item {
    background: #ffffff;
    border: 1px solid #eaedf1;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 18px rgba(23, 32, 42, 0.05);
    display: flex;
    flex-direction: column;
    transition: transform 150ms ease, box-shadow 150ms ease;
}
.vehicle-card-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(23, 32, 42, 0.09);
}
.vehicle-card-thumb {
    position: relative;
    width: 100%;
    height: 170px;
    background: #f1f3f6;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
.vehicle-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.vehicle-card-thumb .status-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 2;
}
.vehicle-card-content {
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    flex: 1;
}
.vehicle-license-pill {
    display: inline-block;
    align-self: flex-start;
    padding: 3px 8px;
    background: #e2e8f0;
    color: #1a202c;
    font-weight: 700;
    font-size: 0.85rem;
    border-radius: 6px;
    letter-spacing: 0.5px;
}
.vehicle-card-name {
    font-size: 1.05rem;
    font-weight: 700;
    color: #17202a;
    margin: 0;
}
.vehicle-meta-row {
    display: flex;
    justify-content: space-between;
    font-size: 0.85rem;
    color: #687386;
}
.vehicle-card-actions {
    display: flex;
    gap: 8px;
    margin-top: auto;
    padding-top: 12px;
    border-top: 1px solid #eaedf1;
}
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 9999px;
    font-size: 11.5px;
    font-weight: 700;
    line-height: 1;
}
.status-badge.ready, .status-badge.success {
    background: rgba(24, 166, 107, 0.12);
    color: #18a66b;
}
.status-badge.repairing, .status-badge.renting, .status-badge.warning {
    background: rgba(245, 158, 11, 0.14);
    color: #d97706;
}
.status-badge.broken, .status-badge.danger {
    background: rgba(237, 28, 36, 0.12);
    color: #ed1c24;
}
</style>
