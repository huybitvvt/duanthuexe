<template>
    <div>
        <b-modal id="modal-lead-update" :title="item ? 'Update Lead' : 'Tạo lead'" size="xl" hide-footer>
            <div class="d-flex justify-content-center mb-6">
                <h2 class="font-weight-bold">{{ item ? "Update Lead" : "Tạo lead" }}</h2>
            </div>
            <ValidationObserver v-slot="{ handleSubmit }" ref="form">
                <form class="form" @submit.prevent="handleSubmit(handleFormSubmit)">
                    <div class="row">

                        <div class="col-md-4">
                            <div class="form-group">
                                <label>
                                    SĐT
                                    <span class="text-danger">(*)</span>
                                </label>
                                <ValidationProvider vid="phone" name="Số điện thoại khách hàng" rules="required|numeric"
                                    v-slot="{ errors }">
                                    <el-input clearable placeholder="SĐT khách hàng" v-model="lead.customer_phone"
                                        name="phone"></el-input>
                                    <error-message :errors="errors" field="phone"></error-message>
                                </ValidationProvider>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Tên khách hàng</label>
                                <el-input placeholder="Tên khách hàng" v-model="lead.customer_name">
                                </el-input>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Hãng xe

                                </label>
                                <ValidationProvider vid="store_id" name="Hãng xe" v-slot="{ errors, classes }">
                                    <el-select filterable class="w-100" placeholder="Hãng xe"
                                        v-model="lead.vehicle_name" clearable :class="classes">
                                        <el-option v-for="item in brands" :key="item.id" :label="item.name"
                                            :value="item.id">
                                            <span style="float: left">{{
                                                item.name
                                                }}</span>
                                        </el-option>
                                    </el-select>
                                    <div class="fv-plugins-message-container">
                                        <div data-field="name" data-validator="notEmpty" class="fv-help-block">
                                            {{ errors[0] }}
                                        </div>
                                    </div>
                                </ValidationProvider>
                            </div>
                        </div>

                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Kênh nguồn</label>
                                <el-input clearable placeholder="Facebook, Website, Giới thiệu..." v-model="lead.source_channel" />
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Tên chiến dịch</label>
                                <el-input clearable placeholder="Tên chiến dịch" v-model="lead.campaign_name" />
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>UTM source</label>
                                <el-input clearable placeholder="utm_source" v-model="lead.utm_source" />
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>UTM campaign</label>
                                <el-input clearable placeholder="utm_campaign" v-model="lead.utm_campaign" />
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Địa điểm nhận xe</label>

                                <ValidationProvider name="Địa điểm nhận xe" v-slot="{ errors }">
                                    <el-select v-model="location_type" clearable filterable class="w-100"
                                        placeholder="Chọn địa điểm nhận xe">
                                        <el-option value="store" label="Cửa hàng">
                                        </el-option>
                                        <el-option value="custom" label="Địa điểm tại Hà Nội">
                                        </el-option>
                                    </el-select>
                                    <error-message :errors="errors" field="store_id"></error-message>
                                </ValidationProvider>
                            </div>
                            <div v-if="location_type == 'store'" class="form-group">
                                <label>Cửa hàng</label>
                                <ValidationProvider vid="store_id" name="Cửa hàng xe" v-slot="{ errors }">
                                    <el-select v-model="lead.store_id" clearable filterable class="w-100"
                                        placeholder="Chọn cửa hàng" @change="onStoreChange($event)">
                                        <el-option v-for="item in stores" :key="item.name" :label="item.store_name"
                                            :value="item.id">
                                        </el-option>
                                    </el-select>
                                    <error-message :errors="errors" field="store_id"></error-message>
                                </ValidationProvider>
                            </div>
                            <div v-if="location_type == 'custom'" class="form-group">
                                <label>Địa chỉ nhận xe tại Hà Nội</label>
                                <el-input clearable placeholder="Địa chỉ nhận xe tại Hà Nội"
                                    v-model="lead.pickup_location" name="pickup_location" type="textarea"
                                    rows="4"></el-input>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Ngày nhận xe </label>
                                <ValidationProvider vid="rent_at" name="Ngày nhận xe" v-slot="{ errors }">
                                    <el-date-picker class="w-100" v-model="lead.rent_at" type="datetime"
                                        format="dd-MM-yyyy HH:mm:ss" placeholder="Chọn thời gian">
                                    </el-date-picker>
                                    <error-message :errors="errors" field="rent_at"></error-message>
                                </ValidationProvider>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Ngày trả xe </label>
                                <ValidationProvider vid="return_at" name="Ngày trả xe" v-slot="{ errors }">
                                    <el-date-picker class="w-100" v-model="lead.return_at" type="datetime"
                                        format="dd-MM-yyyy HH:mm:ss" placeholder="Chọn thời gian">
                                    </el-date-picker>
                                    <error-message :errors="errors" field="return_at"></error-message>
                                </ValidationProvider>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Ghi chú</label>
                                <el-input clearable placeholder="Ghi chú" v-model="lead.note" name="note"
                                    type="textarea" rows="4"></el-input>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <button class="btn btn-success" type="submit">
                                {{ item ? "Cập nhật" : "Tạo" }}
                            </button>
                        </div>
                    </div>
                </form>
            </ValidationObserver>
        </b-modal>
    </div>
</template>

<script>
import { LEAD_UPDATE, LEAD_CREATE } from "@/core/services/store/lead.module";
import { STORE_GET_ALL } from "@/core/services/store/store.module";
import { VEHICLE_GET_ALL } from "@/core/services/store/vehicle.module";
import { brands } from "@/option/vehicle";
import ErrorMessage from "@/view/pages/common/ErrorMessage";
import { mapGetters } from "vuex";
import { getApiMessage, getApiValidationErrors } from "@/utils/apiErrorHandler";


export default {
    name: "LeadModalUpdate",
    props: {
        item: {
            types: Object,
            default: () => null,
        },
    },
    data() {
        return {
            lead: {
                ...this.initialState,
            },
            location_type: '',
            stores: [],
            vehicles: [],
            brands: brands,
            initialState: {
                pickup_location: "",
                store_id: "",
                vehicle_type: "",
                rent_at: "",
                return_at: "",
                customer_name: "",
                customer_phone: "",
                vehicle_name: "",
                source_channel: "",
                campaign_name: "",
                utm_source: "",
                utm_campaign: "",

                note: "",
            }
        };
    },
    components: {
        ErrorMessage,
    },
    computed: {
        ...mapGetters(["currentUser"]),
    },
    watch: {
        item() {
            this.lead = { ...this.item };
        },
    },
    mounted() {
        // get stores & year when modal shown
        this.$root.$on("bv::modal::show", (_, modalId) => {
            if (modalId === "modal-lead-update") {
                this.getStore();

                this.getVehicles();
                if (!this.lead.id) {
                    this.lead = { ...this.initialState };
                }
            }
        });
    },
    methods: {
        getStore() {
            this.$store.dispatch(STORE_GET_ALL, {}).then((data) => {
                this.stores = data?.data || [];
            });
        },
        getVehicles() {
            let params = {
                is_all: true,
                status: "ready",
            };

            this.$store.dispatch(VEHICLE_GET_ALL, params).then((data) => {
                const merged = [
                    ...(this.vehicles || []),
                    ...(data?.data || []),
                ];

                let idMap = new Map();
                let uniqueArray = merged.filter((item) => {
                    if (!idMap.has(item.id)) {
                        idMap.set(item.id, true);
                        return true;
                    }
                    return false;
                });

                this.vehicles = uniqueArray;
            });
        },
        storeLead(payload) {

            this.$store
                .dispatch(payload.id ? LEAD_UPDATE : LEAD_CREATE, payload)
                .then(() => {
                    this.$emit("storeLeadSuccess");
                    this.$notify({
                        title: payload.id ? "Cập nhật thành công" : "Tạo mới thành công",
                        type: "success",
                    });

                    this.$bvModal.hide("modal-lead-update");

                })
                .catch((e) => {
                    this.$notify({
                        title: getApiMessage(e),
                        type: "error",
                    });

                    const errors = getApiValidationErrors(e);
                    if (errors) {
                        this.$refs.form.setErrors(errors);
                    }
                });


        },

        onStoreChange(storeId) {
            this.lead.store_id = storeId;
        },
        handleFormSubmit() {
            const payload = {
                customer_name: this.lead.customer_name,
                customer_phone: this.lead.customer_phone,
                vehicle_name: this.lead.vehicle_name,
                store_id: this.lead.store_id,
                pickup_location: this.lead.pickup_location,
                rent_at: this.lead.rent_at,
                return_at: this.lead.return_at,
                status: this.lead.status,
                note: this.lead.note,
                source_channel: this.lead.source_channel,
                campaign_name: this.lead.campaign_name,
                utm_source: this.lead.utm_source,
                utm_campaign: this.lead.utm_campaign,
                id: this.lead.id,
            };

            if (this.currentUser?.id != null) {
                payload.user_id = this.currentUser.id;
            }

            this.storeLead(payload);
        },
    },
};
</script>
