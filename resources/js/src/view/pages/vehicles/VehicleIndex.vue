<template>
    <div>
        <div class="card card-custom gutter-b">
            <div class="card-header align-items-center">
                <div class="card-title">
                    <h3 class="card-label">Danh sách xe</h3>
                </div>
                <div class="card-toolbar d-flex align-items-center">
                    <div class="btn-group btn-group-sm mr-3" role="group" aria-label="Chế độ hiển thị">
                        <button type="button" class="btn font-weight-bold" :class="viewMode === 'table' ? 'btn-primary' : 'btn-secondary'" @click="switchView('table')">
                            Bảng
                        </button>
                        <button type="button" class="btn font-weight-bold" :class="viewMode === 'grid' ? 'btn-primary' : 'btn-secondary'" @click="switchView('grid')">
                            Lưới thẻ
                        </button>
                    </div>
                    <ModalVehicleCreate @storeSuccess="getList"></ModalVehicleCreate>
                    <button class="btn btn-success ml-2" @click="exportFile">Export</button>
                </div>

            </div>
            <div class="vehicle-summary-panel" aria-label="Tổng hợp đội xe">
                <div class="vehicle-summary-title">Tổng hợp</div>
                <div class="vehicle-summary-grid">
                    <div class="vehicle-summary-item">
                        <span>Số lượng xe</span><strong>{{ reports.total_vehicle || 0 }}</strong>
                    </div>
                    <div class="vehicle-summary-item wide">
                        <span>Phí đầu tư</span><strong>{{ reports.total_price | formatPrice }}</strong>
                    </div>
                    <div class="vehicle-summary-item success">
                        <span>Sẵn sàng</span><strong>{{ reports.total_vehicle_ready || 0 }}</strong>
                    </div>
                    <div class="vehicle-summary-item primary">
                        <span>Đang sử dụng</span><strong>{{ reports.total_vehicle_using || 0 }}</strong>
                    </div>
                    <div class="vehicle-summary-item"><span>Xe ga</span><strong>{{ reports.total_vehicle_ga || 0 }}</strong></div>
                    <div class="vehicle-summary-item"><span>Xe số</span><strong>{{ reports.total_vehicle_so || 0 }}</strong></div>
                    <div class="vehicle-summary-item"><span>Xe côn</span><strong>{{ reports.total_vehicle_con || 0 }}</strong></div>
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


                        <el-button :loading="loading" class=" btn btn-primary font-weight-bold mr-2"
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
                    <div
                        v-if="viewMode === 'table'"
                        v-drag-scroll
                        class="example-preview table-responsive"
                        role="region"
                        aria-label="Danh sách xe, có thể kéo ngang bằng chuột"
                    >
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
                                                class="btn btn-xs btn-outline-info font-weight-bold" title="Xem chi tiết"
                                                @click="showPopup(item)">
                                                Xem
                                            </button>
                                            <button v-b-modal.modal-vehicle-edit @click="item_current = item"
                                                class="btn btn-xs btn-outline-info font-weight-bold" title="Sửa">
                                                Sửa
                                            </button>
                                            <button class="btn btn-xs btn-outline-primary font-weight-bold" title="Tạo đơn thuê"
                                                @click="$router.push('/car-rental?vehicle_id=' + item.id)">
                                                Thuê
                                            </button>
                                            <a v-if="currentUser.role_id === 1" title="Xóa" @click="deleteVehicle(item.id)" href="javascript:"
                                                class="btn btn-xs btn-outline-danger font-weight-bold">
                                                Xóa
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
                                <img :src="getFirstImage(item) || '/media/vehicles/default-moto.svg'" alt="Ảnh xe" class="vehicle-img" />
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
                                    <button class="btn btn-sm btn-secondary font-weight-bold" @click="showPopup(item)" v-b-modal.modal-show-car-rental>
                                        Chi tiết
                                    </button>
                                    <button class="btn btn-sm btn-secondary font-weight-bold" @click="item_current = item" v-b-modal.modal-vehicle-edit>
                                        Sửa
                                    </button>
                                    <button class="btn btn-sm btn-primary font-weight-bold ml-auto" @click="$router.push('/car-rental?vehicle_id=' + item.id)">
                                        Thuê
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
            ],
            lastFetchedAt: 0,
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
    activated() {
        const queryPage = +this.$route?.query?.page || 1;
        const queryName = this.$route?.query?.name || this.$route?.query?.keyword || '';
        const currentName = this.query.name || this.query.keyword || '';
        const paramsChanged = queryPage !== this.page || queryName !== currentName;
        const isTtlExpired = !this.lastFetchedAt || (Date.now() - this.lastFetchedAt > 60000);

        if (paramsChanged) {
            this.page = queryPage;
            if (this.$route?.query?.name !== undefined) {
                this.query.name = this.$route.query.name;
            }
            this.getList();
            this.getReport();
        } else if (isTtlExpired) {
            this.getList();
            this.getReport();
        }
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
                    this.lastFetchedAt = Date.now();
                })
                .catch((err) => {
                    this.errorMessage = getApiMessage(err);
                })
                .finally(() => {
                    this.loading = false;
                });
        },
        getReport() {
            this.$store
                .dispatch(VEHICLE_GET_ALL_REPORT, this.query)
                .then((data) => {
                    this.reports = data?.data || {};
                })
                .catch(() => {});
        },
        clickCallback(obj) {
            this.page = obj;
            this.$router.push({ path: "", query: { page: this.page } });
            this.getList();
        },
        getStore() {
            this.$store.dispatch(STORE_GET_ALL, {}).then((data) => {
                this.stores = data?.data || [];
            }).catch(() => {});
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
.vehicle-summary-panel {
  margin: 0 24px;
  padding: 14px 16px;
  border: 1px solid #e2e7ee;
  border-radius: 10px;
  background: #f8fafc;
}

.vehicle-summary-title {
  margin-bottom: 10px;
  color: #8f1b21;
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

.vehicle-summary-grid {
  display: grid;
  grid-template-columns: repeat(7, minmax(100px, 1fr));
  gap: 10px;
}

.vehicle-summary-item {
  min-height: 58px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 3px;
  padding: 9px 12px;
  border: 1px solid #e5e9ef;
  border-radius: 8px;
  background: #fff;
}

.vehicle-summary-item span { color: #667085; font-size: 12px; }
.vehicle-summary-item strong { color: #243043; font-size: 16px; }
.vehicle-summary-item.success strong { color: #12805c; }
.vehicle-summary-item.primary strong { color: #1677c8; }

@media (max-width: 1200px) {
  .vehicle-summary-grid { grid-template-columns: repeat(4, minmax(120px, 1fr)); }
}

@media (max-width: 768px) {
  .vehicle-summary-panel { margin: 0 12px; }
  .vehicle-summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

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
