<template>
    <div>
        <!-- <button v-b-modal.modal-vehicle-create class="btn btn-success btn-sm">Thêm mới</button> -->
        <button @click="showModal" class="btn btn-success mr-2">
            Thêm mới
        </button>
        <b-modal
            id="modal-vehicle-create"
            title="Thêm mới xe"
            size="xl"
            ok-title="Tạo mới"
            ok-only
            @show="resetModal"
            @hidden="resetModal"
            @ok="handleOk"
        >
            <div class="row">
                <div class="col-md-12">
                    <h5 class="text-primary">Thông tin xe</h5>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Hãng xe</label>
                        <ValidationProvider
                            vid="store_id"
                            name="Hãng xe"
                            rules="required"
                            v-slot="{ errors, classes }"
                        >
                            <el-select
                                filterable
                                class="w-100"
                                placeholder="Hãng xe"
                                v-model="vehicle.brand"
                                clearable
                                :class="classes"
                            >
                                <el-option
                                    v-for="item in brands"
                                    :key="item.id"
                                    :label="item.name"
                                    :value="item.id"
                                >
                                    <span style="float: left">{{
                                        item.name
                                    }}</span>
                                </el-option>
                            </el-select>
                            <div class="fv-plugins-message-container">
                                <div
                                    data-field="name"
                                    data-validator="notEmpty"
                                    class="fv-help-block"
                                >
                                    {{ errors[0] }}
                                </div>
                            </div>
                        </ValidationProvider>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Tên xe</label>
                        <ValidationProvider
                            vid="store_id"
                            name="Tên xe"
                            rules="required"
                            v-slot="{ errors, classes }"
                        >
                            <el-input
                                clearable
                                placeholder="Tên xe"
                                v-model="vehicle.name"
                                :class="classes"
                            ></el-input>
                            <div class="fv-plugins-message-container">
                                <div
                                    data-field="name"
                                    data-validator="notEmpty"
                                    class="fv-help-block"
                                >
                                    {{ errors[0] }}
                                </div>
                            </div>
                        </ValidationProvider>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Cửa hàng</label>
                        <ValidationProvider
                            vid="store_id"
                            name="Cừa hàng"
                            rules="required"
                            v-slot="{ errors, classes }"
                        >
                            <el-select
                                filterable
                                class="w-100"
                                placeholder="Cửa hàng"
                                v-model="vehicle.store_id"
                                clearable
                                :class="classes"
                            >
                                <el-option
                                    v-for="item in stores"
                                    :key="item.id"
                                    :label="item.store_name"
                                    :value="item.id"
                                >
                                    <span style="float: left">{{
                                        item.store_name
                                    }}</span>
                                </el-option>
                            </el-select>
                            <div class="fv-plugins-message-container">
                                <div
                                    data-field="name"
                                    data-validator="notEmpty"
                                    class="fv-help-block"
                                >
                                    {{ errors[0] }}
                                </div>
                            </div>
                        </ValidationProvider>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label>Trạng thái</label>
                        <ValidationProvider
                            vid="status"
                            name="Trạng thái"
                            rules="required"
                            v-slot="{ errors, classes }"
                        >
                            <el-select
                                filterable
                                class="w-100"
                                placeholder="Trạng thái"
                                v-model="vehicle.status"
                                clearable
                                :class="classes"
                            >
                                <el-option
                                    v-for="item in status"
                                    :key="item.id"
                                    :label="item.name"
                                    :value="item.id"
                                >
                                    <span style="float: left">{{
                                        item.name
                                    }}</span>
                                </el-option>
                            </el-select>
                            <div class="fv-plugins-message-container">
                                <div
                                    data-field="name"
                                    data-validator="notEmpty"
                                    class="fv-help-block"
                                >
                                    {{ errors[0] }}
                                </div>
                            </div>
                        </ValidationProvider>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Năm sản xuất</label>
                        <ValidationProvider
                            vid="year"
                            name="Năm sản xuất"
                            rules="required"
                            v-slot="{ errors, classes }"
                        >
                            <el-select
                                filterable
                                class="w-100"
                                placeholder="Năm sản xuất"
                                v-model="vehicle.year"
                                clearable
                                :class="classes"
                            >
                                <el-option
                                    v-for="item in years"
                                    :key="'created-year-' + item.id"
                                    :label="item.name"
                                    :value="item.id"
                                >
                                    <span style="float: left">{{
                                        item.name
                                    }}</span>
                                </el-option>
                            </el-select>
                            <div class="fv-plugins-message-container">
                                <div
                                    data-field="name"
                                    data-validator="notEmpty"
                                    class="fv-help-block"
                                >
                                    {{ errors[0] }}
                                </div>
                            </div>
                        </ValidationProvider>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Loại xe</label>
                        <ValidationProvider
                            vid="type"
                            name="Loại xe"
                            rules="required"
                            v-slot="{ errors, classes }"
                        >
                            <el-select
                                filterable
                                class="w-100"
                                placeholder="Loại xe"
                                v-model="vehicle.type"
                                clearable
                                :class="classes"
                            >
                                <el-option
                                    v-for="item in types"
                                    :key="item.id"
                                    :label="item.name"
                                    :value="item.id"
                                >
                                    <span style="float: left">{{
                                        item.name
                                    }}</span>
                                </el-option>
                            </el-select>
                            <div class="fv-plugins-message-container">
                                <div
                                    data-field="name"
                                    data-validator="notEmpty"
                                    class="fv-help-block"
                                >
                                    {{ errors[0] }}
                                </div>
                            </div>
                        </ValidationProvider>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Biển số</label>
                        <ValidationProvider
                            vid="license"
                            name="Biển số"
                            rules="required"
                            v-slot="{ errors, classes }"
                        >
                            <el-input
                                clearable
                                placeholder="Biển số"
                                v-model="vehicle.license"
                                :class="classes"
                            ></el-input>
                            <div class="fv-plugins-message-container">
                                <div
                                    data-field="name"
                                    data-validator="notEmpty"
                                    class="fv-help-block"
                                >
                                    {{ errors[0] }}
                                </div>
                            </div>
                        </ValidationProvider>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Số khung</label>
                        <ValidationProvider
                            vid="chassis"
                            name="Số khung"
                            rules="required"
                            v-slot="{ errors }"
                        >
                            <el-input
                                clearable
                                placeholder="Số khung"
                                v-model="vehicle.chassis"
                            ></el-input>
                            <div class="fv-plugins-message-container">
                                <div
                                    data-field="name"
                                    data-validator="notEmpty"
                                    class="fv-help-block"
                                >
                                    {{ errors[0] }}
                                </div>
                            </div>
                        </ValidationProvider>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Số máy</label>
                        <ValidationProvider
                            vid="engine"
                            name="Số máy"
                            :rules="{ required: true }"
                            v-slot="{ errors }"
                        >
                            <el-input
                                clearable
                                placeholder="Số máy"
                                v-model="vehicle.engine"
                            ></el-input>
                            <div class="fv-plugins-message-container">
                                <div
                                    data-field="name"
                                    data-validator="notEmpty"
                                    class="fv-help-block"
                                >
                                    {{ errors[0] }}
                                </div>
                            </div>
                        </ValidationProvider>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Màu</label>
                        <ValidationProvider
                            vid="color"
                            name="Màu"
                            :rules="{ required: true }"
                            v-slot="{ errors }"
                        >
                            <el-input
                                clearable
                                placeholder="Màu"
                                v-model="vehicle.color"
                            ></el-input>
                            <div class="fv-plugins-message-container">
                                <div
                                    data-field="name"
                                    data-validator="notEmpty"
                                    class="fv-help-block"
                                >
                                    {{ errors[0] }}
                                </div>
                            </div>
                        </ValidationProvider>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Phân loại</label>
                        <ValidationProvider
                            vid="type_of_service_id"
                            name="Phân loại"
                            :rules="{ required: true }"
                            v-slot="{ errors }"
                        >
                            <el-select
                                filterable
                                class="w-100"
                                placeholder="Phân loại"
                                v-model="vehicle.type_of_service_id"
                                clearable
                                @change="changeTypeOfService"
                            >
                                <el-option
                                    v-for="item in typeOfServices"
                                    :key="item.id"
                                    :label="item.name"
                                    :value="item.id"
                                >
                                    <span style="float: left">{{
                                        item.name
                                    }}</span>
                                </el-option>
                            </el-select>
                            <div class="fv-plugins-message-container">
                                <div
                                    data-field="name"
                                    data-validator="notEmpty"
                                    class="fv-help-block"
                                >
                                    {{ errors[0] }}
                                </div>
                            </div>
                        </ValidationProvider>
                    </div>
                </div>
				<div class="col-md-6">
					<div class="form-group">
						<label for="debt"><strong>Số km hiện tại</strong></label>
						<ValidationProvider vid="odometer" name="Số km hiện tại" rules="numeric" v-slot="{ errors }">
							<el-input id="odometer" placeholder="Số km hiện tại" v-model="vehicle.odometer" clearable></el-input>
							<error-message :errors="errors" field="odometer"></error-message>
						</ValidationProvider>
					</div>
				</div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <h5 class="text-primary">Thông tin giá</h5>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Giá mua</label>
                        <ValidationProvider
                            vid="cost_price"
                            name="Giá mua"
                            :rules="{ required: true }"
                            v-slot="{ errors, classes }"
                        >
                            <money
                                v-model="vehicle.cost_price"
                                class="form-control"
                                :class="classes"
                                v-bind="money"
                            ></money>

                            <div class="fv-plugins-message-container">
                                <div
                                    data-field="name"
                                    data-validator="notEmpty"
                                    class="fv-help-block"
                                >
                                    {{ errors[0] }}
                                </div>
                            </div>
                        </ValidationProvider>
                    </div>
                </div>
                <div class="col-md-4" v-if="is_show_price_sell">
                    <div class="form-group">
                        <label>Giá bán tối thiểu</label>
                        <ValidationProvider
                            vid="price_min"
                            name="Giá bán tối thiểu"
                            :rules="{ required: true }"
                            v-slot="{ errors, classes }"
                        >
                            <money
                                v-model="vehicle.price_min"
                                class="form-control"
                                :class="classes"
                                v-bind="money"
                            ></money>
                            <div class="fv-plugins-message-container">
                                <div
                                    data-field="name"
                                    data-validator="notEmpty"
                                    class="fv-help-block"
                                >
                                    {{ errors[0] }}
                                </div>
                            </div>
                        </ValidationProvider>
                    </div>
                </div>
                <div class="col-md-4" v-if="is_show_price_sell">
                    <div class="form-group">
                        <label>Giá bán tối đa</label>
                        <ValidationProvider
                            vid="price_max"
                            name="Giá bán tối đa"
                            :rules="{ required: true }"
                            v-slot="{ errors, classes }"
                        >
                            <money
                                v-model="vehicle.price_max"
                                class="form-control"
                                :class="classes"
                                v-bind="money"
                            >
                            </money>
                            <div class="fv-plugins-message-container">
                                <div
                                    data-field="name"
                                    data-validator="notEmpty"
                                    class="fv-help-block"
                                >
                                    {{ errors[0] }}
                                </div>
                            </div>
                        </ValidationProvider>
                    </div>
                </div>
            </div>
			<div class="row">
                <div class="col-md-12 mb-5">
                    <h5 class="text-primary">Hình ảnh xe</h5>
                </div>
				<div class="col-md-12">
					<el-upload
						:action="uploadUrl"
						list-type="picture-card"
						accept="image/jpeg,image/gif,image/png"
						:on-preview="handlePreview"
						:on-success="handleSuccess"
						:before-upload="beforeUpload"
						:on-remove="onRemove"
						:on-change="onFileChanges"
						:on-error="handleError"
						:file-list="vehicle.images"
						:auto-upload="true"
						:headers="headerInfo"
						multiple>
							<span class="font-weight-bold">Thêm ảnh</span>
							<div slot="tip" class="el-upload__tip">Chọn nhiều file để upload</div>
					</el-upload>

					<el-dialog :visible.sync="dialogVisible">
						<img width="100%" :src="dialogImageUrl" alt="">
					</el-dialog>
				</div>
			</div>
        </b-modal>
    </div>
</template>

<script>
import JwtService from "@/core/services/jwt.service";
import { apiUrl } from "@/core/services/api.service";
import { STORE_GET_ALL } from "../../../core/services/store/store.module";
import { FILE_DELETE } from "../../../core/services/store/file.module";
import {
    brands,
    status,
    types,
    typeOfServices,
    XE_BAN,
} from "../../../option/vehicle";
import { VEHICLE_CREATE } from "../../../core/services/store/vehicle.module";
import ErrorMessage from "../common/ErrorMessage";

export default {
    name: "ModalVehicleCreate",
	components: {
        ErrorMessage,
    },
    data() {
        return {
            vehicle: {
                name: "",
                brand: "",
                type: "",
                year: "",
                store_id: "",
                license: "",
                chassis: "",
                engine: "",
                status: "pending",
                cost_price: "",
                sale_price: "",
                price_range: "",
                price_min: "",
                price_max: "",
                created_by: "",
                color: "",
                type_of_service_id: "",
				images: [],
				image_ids: [],
            },
            stores: [],
            typeOfServices: typeOfServices,
            years: [],
            types: types,
            brands: brands,
            status: status,
            is_show_price_sell: false,
            money: {
                decimal: ",",
                thousands: ",",
                prefix: "",
                suffix: " VNĐ",
                precision: 0,
                masked: false,
            },
			dialogImageUrl: '',
			dialogVisible: false,
			uploadUrl: apiUrl('/api/auth/file/upload-images'),

			headerInfo: {
				"Authorization": `Bearer ${JwtService.getToken()}`,
			},
        };
    },
    mounted() {
        // this.getStore();
        // this.handYear();
    },
    methods: {
        showModal() {
            this.$bvModal.show("modal-vehicle-create");
            this.getStore();
            this.handYear();
        },
        resetModal() {
            this.vehicle = {
                name: "",
                brand: "",
                type: "",
                year: "",
                store_id: "",
                license: "",
                chassis: "",
                engine: "",
                status: "pending",
                cost_price: "",
                sale_price: "",
                price_range: "",
                price_min: "",
                price_max: "",
                created_by: "",
                color: "",
                type_of_service_id: "",
            };
        },
        getStore() {
            this.$store.dispatch(STORE_GET_ALL, {}).then((data) => {
                this.stores = data.data;
            });
        },
        handYear() {
			let years = [];
            const currentYear = new Date().getFullYear();
            for (let i = 2000; i <= currentYear; i++) {
                years.push({
                    id: i,
                    name: i,
                });
            }
			this.years = years;
        },
        changeTypeOfService() {
            if (this.vehicle.type_of_service_id === XE_BAN) {
                this.is_show_price_sell = true;
            } else {
                this.is_show_price_sell = false;
                this.vehicle.price_min = "";
                this.vehicle.price_max = "";
            }
        },
        handleOk(bvModalEvent) {
            bvModalEvent.preventDefault();
            this.storeVehicle();
        },
        storeVehicle() {
            this.$store
			.dispatch(VEHICLE_CREATE, this.vehicle)
			.then(() => {
				this.$emit("storeSuccess");
				this.$notify({
					title: "Tạo mới thành công",
					type: "success",
				});
				this.$bvModal.hide("modal-vehicle-create");
			})
			.catch((e) => {
				this.$notify({
					title: e.data.message,
					type: "error",
				});
				if (e.response.data.data.message_validate_form) {
					this.$refs.form.setErrors(
						e.response.data.data.message_validate_form,
					);
				}
			});
        },
		onFileChanges(file, fileList) {
			this.vehicle.images = fileList;
			let file_ids = [];
			if (fileList && fileList.length > 0) {
				fileList.forEach(file => {
					if (file.id) {
						file_ids.push(file.id);
					} else {
						var file_id = file?.response?.data?.id;
						if (file_id) {
							file_ids.push(file_id);
						}
					}
				});
			}
			this.vehicle.image_ids = file_ids;
		},
		onRemove(file, fileList) {
			this.onFileChanges(file, fileList);

			// Delete this file.
			let file_id = file?.id;
			if ( ! file_id ) {
				file_id = file?.response?.data?.id;
			}
			if (file_id) {
				this.$store.dispatch(FILE_DELETE, file_id).then((data) => {
					console.log(`delete file id:  ${file_id}`, data);
				});
			}
		},
		handlePreview(file) {
			this.dialogImageUrl = file.url || file.raw;
			this.dialogVisible = true;
		},
		handleSuccess(response, file, fileList) {
			this.$message.success(`${file.name} đã upload thành công.`);
		},
		handleError(err, file, fileList) {
			this.$message.error(`${file.name} upload thất bại.`);
		},
		beforeUpload(file) {
			const isImage = file.type.startsWith('image/');
			const isLt2M = file.size / 1024 / 1024 < 10;

			if (!isImage) {
				this.$message.error('Chỉ có thể upload hình ảnh!');
			}
			if (!isLt2M) {
				this.$message.error('Kích thước hình ảnh không thể lớn hơn 10MB!');
			}
			return isImage && isLt2M;
		},
    },
};
</script>

<style scoped></style>
