<template>
    <div id="lead-view" class="d-inline">
        <b-modal :centered="true" :scrollable="true" id="modal-lead-view" size="xl"
            title="Xem chi tiết thông tin đăng ký thuê xe" hide-footer>
            <section id="section-lead-view">
                <div class="card card-custom gutter-b">
                    <div class=" ">
                        <div class="example">
                            <div class="example-preview table-responsive">
                                <h4 class="mb-6">Thông tin đăng ký thuê xe</h4>

                                <table class="table" v-if="lead">
                                    <thead>
                                        <tr>
                                            <th scope="col">Mã</th>
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
                                            <th scope="col" class="min-w-120px max-w-300px">
                                                Ghi chú
                                            </th>

                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <th scope="row">{{ lead.id }}</th>
                                            <td>
                                                <span>{{
                                                    lead.customer_name
                                                }}</span>
                                            </td>
                                            <td>
                                                <span>{{
                                                    lead.customer_phone
                                                }}</span>
                                            </td>
                                            <td>
                                                <span>{{
                                                    mapBrand(lead.vehicle_name)
                                                }}</span>
                                            </td>
                                            <td>
                                                <span>{{
                                                    location(lead)

                                                }}</span>
                                            </td>
                                            <td>
                                                <span>{{ lead.rent_at }}</span>
                                            </td>
                                            <td>
                                                <span>{{
                                                    lead.return_at
                                                }}</span>
                                            </td>
                                            <td>
                                                <span :class="status_define_css[
                                                    lead.status
                                                ]
                                                    ">{{ lead.status }}</span>
                                            </td>
                                            <td>
                                                <span>{{ lead.note }}</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card card-custom gutter-b">
                    <div class="">
                        <div class="example">
                            <div class="example-preview table-responsive">
                                <h4 class="mb-6">
                                    Lịch sử cập nhật thông tin đăng ký thuê xe
                                </h4>
                                <table class="table edit-history" v-if="lead && lead.lead_logs.length > 0">
                                    <thead>
                                        <tr>
                                            <th scope="col">Mã</th>
                                            <th scope="col" class="min-w-130px">
                                                Ngày
                                            </th>
                                            <th scope="col">Người thực hiện</th>
                                            <th scope="col">Loại chỉnh sửa</th>

                                            <th scope="col" v-if="lead.metadata">
                                                Nội dung chỉnh sửa
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(item, j) in lead.lead_logs" :key="j">
                                            <td>{{ item.id }}</td>
                                            <td>
                                                {{
                                                    item.created_at
                                                    | formatDateTime
                                                }}
                                            </td>
                                            <td>{{ item.user && item.user.name || "" }}</td>
                                            <td>{{ item.content || "" }}</td>
                                            <td v-if="lead.metadata">
                                                <table v-if="leadOptions" style="width: 100%;">
                                                    <tr>
                                                        <td></td>
                                                        <td>Trước</td>
                                                        <td>Sau</td>
                                                    </tr>
                                                    <tr v-for="(
                                                            col, i
                                                        ) in formatMetadata(
                                                                item.metadata,
                                                            )" :key="i">
                                                        <td>
                                                            <strong>{{
                                                                leadOptions[
                                                                col
                                                                    .colName
                                                                ]
                                                            }}:</strong>
                                                        </td>

                                                        <td>{{ col.old }}</td>
                                                        <td>{{ col.new }}</td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </b-modal>
    </div>
</template>

<script>
import { brands, status_define_css } from "@/option/vehicle";
import { leadOptions } from "@/option/leadOption";

export default {
    name: "LeadView",
    props: {
        lead: {
            type: Object,
            default: () => { },
        },
        stores: {
            type: Array,
            default: () => [],
        }
    },
    data() {
        return {
            brands,
            status_define_css,

            leadOptions,
        };
    },
    methods: {
        location(item) {
            if (item.store_id) {

                return this.mapStore(item.store_id)
            }
            if (item.pickup_location) {

                return item.pickup_location
            }
            return '';
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
        formatMetadata(metadata) {
            try {
                const json = JSON.parse(metadata);
                const res = [];
                console.log('json: ', json);
                for (let [key, value] of Object.entries(json)) {
                    if (!value || typeof value !== "object") continue;
                    if (!Object.keys(leadOptions).includes(key)) continue;

                    if (key === "store_id") {
                        value.old = this.mapBrand(value.old);
                        value.new = this.mapBrand(value.new);
                    }

                    if (key === "vehicle_name") {
                        value.old = this.mapStore(value.old);
                        value.new = this.mapStore(value.new);
                    }
                    res.push({ ...value });
                }
                return res;
            } catch {
                return [{ colName: "", old: "", new: "" }];
            }
        },
    },
};
</script>
