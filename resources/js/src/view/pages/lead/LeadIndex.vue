<template>
    <div>
        <div class="card card-custom gutter-b">
            <div class="card-header">
                <div class="card-title">
                    <h3 class="card-label">Danh sách Lead</h3>
                </div>
                <div class="card-title">
                    <button class="btn btn-success mr-2" v-b-modal.modal-lead-update @click="item_current = null">
                        Thêm mới
                    </button>

                </div>
            </div>

            <div>
                <div class="card card-custom gutter-b">
                    <div class="card-body">
                        <div class="example mb-10">

                            <div class="d-flex filter-row">
                                <div class=" ">


                                    <search-suggest endpoint="/api/auth/leads" :params="query" query-key="keyword" fields="customer_name,customer_phone,vehicle_name" @select="search" @submit="search" clearable placeholder="Tên khách hàng, SĐT, loại xe"
                                        v-model="query.keyword"></search-suggest>

                                </div>
                                <div class=" ">


                                    <el-select filterable placeholder="Trạng thái" v-model="query.status" clearable>
                                        <el-option label="Hoàn thành" value="completed">
                                            <span style="float: left">Hoàn thành</span>
                                        </el-option>
                                        <el-option label="Đang chờ" value="pending">
                                            <span style="float: left">Đang chờ</span>
                                        </el-option>
                                    </el-select>

                                </div>
                                <div class=" ">


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

                                <div class=" ">


                                    <el-select multiple filterable class="w-100" placeholder="Nguồn lead"
                                        v-model="query.source" clearable>
                                        <el-option label="Landing page Himoto" value="NULL">

                                        </el-option>
                                        <el-option v-for="item in sources" :key="item.user_id" :label="item?.user?.name"
                                            :value="item.user_id">
                                            <span style="float: left">{{
                                                item?.user?.name
                                            }}</span>
                                        </el-option>
                                    </el-select>

                                </div>

                                <div class=" ">

                                    <el-date-picker class="w-100" v-model="query.start_date" type="date"
                                        format="yyyy-MM-dd" value-format="yyyy-MM-dd" placeholder="(Ngày submit) Từ">
                                    </el-date-picker>
                                </div>
                                <div class=" ">

                                    <el-date-picker class="w-100" v-model="query.end_date" type="date"
                                        format="yyyy-MM-dd" value-format="yyyy-MM-dd" placeholder="(Ngày submit) Đến">
                                    </el-date-picker>
                                </div>
                                <div class=" ">
                                    <el-button :loading="loading"
                                        class=" btn btn-primary font-weight-bold" @click="search">
                                        Tìm kiếm
                                    </el-button>

                                </div>

                            </div>





                            <HimotoErrorState v-if="errorMessage" title="Không thể tải danh sách Lead" :message="errorMessage" @retry="getLeads" />
                            <HimotoTableSkeleton v-else-if="loading" :rows="6" :columns="11" />
                            <div
                                v-else-if="leads.length"
                                v-drag-scroll
                                class="example-preview table-responsive"
                                role="region"
                                aria-label="Danh sách Lead, có thể kéo ngang bằng chuột"
                            >
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col" class="min-w-120px">
                                                Khách hàng
                                            </th>
                                            <th scope="col" class="min-w-120px">
                                                SDT
                                            </th>
                                            <th scope="col" class="min-w-120px">
                                                Loại xe
                                            </th>
                                            <th scope="col" class="min-w-150px">
                                                Địa điểm nhận xe
                                            </th>
                                            <th scope="col" class="min-w-120px">
                                                Ngày nhận xe
                                            </th>
                                            <th scope="col" class="min-w-120px">
                                                Ngày trả xe
                                            </th>
                                            <th scope="col" class="min-w-120px">
                                                Trạng thái
                                            </th>
                                            <th scope="col" class="min-w-120px">
                                                Nguồn lead
                                            </th>
                                            <th scope="col" class="min-w-120px">
                                                Ngày submit
                                            </th>
                                            <th scope="col" class="min-w-120px">
                                                Hợp đồng
                                            </th>
                                            <th scope="col" class="min-w-120px max-w-300px">
                                                Ghi chú
                                            </th>
                                            <th scope="col" class="min-w-120px">
                                                Hành động
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(item, index) in leads" :key="item.id || index">
                                            <th scope="row">{{ item.id }}</th>
                                            <td>
                                                <span>{{
                                                    item.customer_name
                                                    }}</span>
                                            </td>
                                            <td>
                                                <span>{{
                                                    item.customer_phone
                                                    }}</span>
                                            </td>
                                            <td>
                                                <span>{{
                                                    mapBrand(item.vehicle_name)
                                                    }}</span>
                                            </td>
                                            <td>
                                                <span>{{
                                                    location(item)

                                                }}</span>
                                            </td>
                                            <td>
                                                <span>{{ item.rent_at | formatDate }}</span>
                                            </td>
                                            <td>
                                                <span>{{
                                                    item.return_at | formatDate
                                                }}</span>
                                            </td>
                                            <td>
                                                <span :class="status_define_css[
                                                    item.status
                                                ]
                                                    ">{{ item.status }}</span>
                                            </td>
                                            <td>
                                                <span>{{
                                                    item.user ? item.user.name : 'Landing page Himoto'
                                                    }}</span>
                                            </td>
                                            <td>
                                                <span>{{
                                                    item.created_at | formatDateTime
                                                }}</span>
                                            </td>
                                            <td>
                                                <a v-if="item.order_id" v-b-modal.order-show title="Xem order"
                                                    @click="passOrder(item)" class="view-order"><b>
                                                        {{ item.order_id
                                                        }} </b></a>

                                            </td>
                                            <td>
                                                <span>{{ item.note }}</span>
                                            </td>
                                            <td>
                                                <button v-b-modal.modal-lead-view
                                                    class="btn btn-xs btn-outline-info font-weight-bold mr-1" title="Xem chi tiết"
                                                    @click="showPopup(item)">
                                                    Xem
                                                </button>
                                                <button
                                                    v-if="(currentUser.role_id === 1 || currentUser.role_id === 4) && item.status !== 'deleted'"
                                                    v-b-modal.modal-lead-update @click="item_current = item"
                                                    class="btn btn-xs btn-outline-info font-weight-bold mr-1">
                                                    Sửa
                                                </button>
                                                <button v-if="item.status !== 'deleted'" v-b-modal.lead-modal-delete
                                                    @click="leadIdToDelete = item.id"
                                                    class="btn btn-xs btn-outline-danger font-weight-bold">
                                                    Xóa
                                                </button>


                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <HimotoEmptyState v-else title="Không tìm thấy Lead nào" description="Thử thay đổi bộ lọc hoặc thêm mới Lead vào hệ thống." actionText="Thêm mới Lead" @action="item_current = null" v-b-modal.modal-lead-update />
                        </div>
                    </div>
                </div>
            </div>

            <lead-modal-update :item="item_current" @storeLeadSuccess="getLeads"></lead-modal-update>

            <lead-modal-delete :id="leadIdToDelete" @delete-success="getLeads"></lead-modal-delete>
            <lead-view :lead="lead_show" :stores="stores" @storeLeadSuccess="getLeads"></lead-view>
            <b-modal :title='"Xem hợp đồng  "' id="order-show" size="xl" modal-class="contract-modal-wide" :centered="true" :scrollable="true">
                <order-show :order="order_show" :stores="stores"></order-show>
            </b-modal>

            <div class="edu-paginate mx-auto text-center" v-if="!loading && leads.length">
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
import { LEAD_INDEX, LEAD_UNIQUE_USERS } from "@/core/services/store/lead.module";
import { brands, status_define_css } from "@/option/vehicle";
import { STORE_GET_ALL } from "@/core/services/store/store.module";
import LeadModalUpdate from "./LeadModalUpdate";
import LeadModalDelete from "./LeadModalDelete";
import OrderShow from "@/view/pages/Order/components-order/OrderShow";
import LeadView from "./LeadView";
import { mapGetters } from "vuex";
import { SHOW_ORDER_CAR_RENTAL } from "@/core/services/store/order.module";
import queryMixin from '@/utils/queryMixin.js';
import HimotoTableSkeleton from "@/view/components/himoto/HimotoTableSkeleton.vue";
import HimotoEmptyState from "@/view/components/himoto/HimotoEmptyState.vue";
import HimotoErrorState from "@/view/components/himoto/HimotoErrorState.vue";
import { normalizePaginator } from "@/utils/paginatorAdapter";
import { getApiMessage } from "@/utils/apiErrorHandler";

export default {
    mixins: [queryMixin],
    name: "LeadIndex",
    data() {
        const { page, store_id, ...restQuery } = this.$route?.query || {};

        return {
            order_show: null,
            loading: false,
            errorMessage: null,
            lastFetchedAt: 0,
            query: {
                store_id: store_id ? +store_id : "",
                keyword: "",
                status: this.context === 'dashboard' ? 'pending' : '',
                ...(restQuery || {}),
            },
            page: +page || 1,
            last_page: 1,

            item_current: null,
            lead_show: null,
            leadIdToDelete: null,
            leads: [],
            sources: [],
            stores: [],
            brands: brands,
            status_define_css: status_define_css,
            pickerEndOptions: {},
            pickerStartOptions: {},
            isFirstActivated: true,
        };
    },
    props: {
        context: {
            type: String,
            required: false
        }
    },
    computed: {
        ...mapGetters(["currentUser"]),
    },
    components: {
        OrderShow,
        LeadModalUpdate,
        LeadView,
        LeadModalDelete,
        HimotoTableSkeleton,
        HimotoEmptyState,
        HimotoErrorState
    },
    mounted() {
        this.$store.dispatch(SET_BREADCRUMB, [{ title: "Lead" }]);
        this.getLeads();
        this.getStore();
        this.listSources();
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
        this.getLeads();
    },
    methods: {
        listSources() {
            this.$store.dispatch(LEAD_UNIQUE_USERS, {}).then((data) => {
                this.sources = data?.data || [];
            }).catch(() => {});
        },

        location(item) {
            if (item.store_id) {

                return this.mapStore(item.store_id)
            }
            if (item.pickup_location) {

                return item.pickup_location
            }
            return '';
        },
        search() {
            this.page = 1;
            this.pushParamsUrl();
            this.getLeads();
            this.listSources();
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

        getStore() {
            this.$store.dispatch(STORE_GET_ALL, {}).then((data) => {
                this.stores = data?.data || [];
            }).catch(() => {});
        },
        getLeads() {
            this.loading = true;
            this.errorMessage = null;
            this.$store
                .dispatch(LEAD_INDEX, {
                    page: this.page,
                    ...this.query,
                })
                .then((res) => {
                    const paginated = normalizePaginator(res);
                    this.leads = paginated.items || [];
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
            this.getLeads();
        },
        mapBrand(id) {
            const brands = this.brands || [];
            return this.mapVal(brands, id, "name");
        },
        mapStore(id) {
            const stores = this.stores || [];
            return this.mapVal(stores, id, "store_name");
        },
        mapVal(arr, id, key) {
            if (!arr || arr.length === 0) return id;

            const b = arr.filter((i) => i?.id).find((item) => item.id === id);

            if (!b) return id;
            return b[key];
        },
        showPopup(item) {
            this.lead_show = item;
        },
        passOrder(item) {
            this.$store
                .dispatch(SHOW_ORDER_CAR_RENTAL, item.order_id)
                .then((res) => {
                    this.order_show = {
                        ...res.data,
                    };
                })
                .catch((err) => {
                    this.noticeMessage('error', 'Thất bại', getApiMessage(err));
                });
        },
        deleteLead(id) {
            this.leadIdToDelete = id;
        }

    },
};
</script>
<style scoped>
.view-order:hover {
    color: #1BC5BD !important
}
</style>
