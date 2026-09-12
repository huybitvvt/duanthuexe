<template>
    <div id="car-rental" class="d-inline">
        <b-modal :centered="true" :scrollable="true" id="modal-show-car-rental" size="xl" title="Xem chi tiết xe"
            hide-footer>
            <section id="section-vehicle">
                <div class="card card-custom gutter-b">
                    <div class=" ">
                        <div class="example  ">
                            <div class="example-preview table-responsive">
                                <h4 class="mb-6">Thông tin xe</h4>
                                <table class="table" v-if=vehicle>
                                    <thead>
                                        <tr>
                                            <th scope="col">Mã</th>
                                            <th scope="col"> Tên </th>
                                            <th scope="col"> Brand</th>
                                            <th scope="col"> Biển số</th>
                                            <th scope="col"> Giá mua</th>
                                            <th scope="col"> Loại xe - Đời xe</th>
											<th scope="col">Số km hiện tại</th>
                                            <th scope="col"> Cửa hàng</th>
                                            <th scope="col">Trạng thái</th>
                                            <th scope="col" class="min-w-130px">
                                                Ngày tạo
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>{{ vehicle.id }}</td>
                                            <td>{{ vehicle.name }}</td>
                                            <td>{{ vehicle.brand }}</td>

                                            <td><span class="badge badge-primary">{{ vehicle.license }}</span></td>
                                            <td>{{ vehicle.cost_price }}</td>
                                            <td>{{ type_define[vehicle.type] }} - {{ vehicle.year }}</td>
                                            <td>{{ vehicle.odometer }} </td>
                                            <td><span class="label label-info label-inline mr-2">{{ vehicle.store.store_name }}</span></td>

                                            <td>{{ vehicle.status }} </td>
                                            <td>{{ vehicle.created_at | formatDate }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="card card-custom gutter-b">
                    <div class=" ">
                        <div class="example  ">
                            <h4 class="mb-6">Lịch sử bảo dưỡng</h4>
                            <maintenance-log :vehicle="vehicle"></maintenance-log>
                        </div>
                    </div>
                </div>

                <div class="card card-custom gutter-b">
                    <div class=" ">
                        <div class="example  ">
                            <h4 class="mb-6">Lịch hẹn bảo dưỡng</h4>
                            <maintenance-schedule :vehicle="vehicle"></maintenance-schedule>
                        </div>
                    </div>
                </div>
            </section>
        </b-modal>
    </div>
</template>
<script>

import MaintenanceLog from "./single/MaintenanceLog";
import MaintenanceSchedule from "./single/MaintenanceSchedule";
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
export default {
    name: "ModalView",
    components: {
        MaintenanceLog, MaintenanceSchedule
    },
    props: {
        vehicle: {
            type: Object,
            default: () => {
                return {};
            }
        },
    },
	data() {
		return {
			type_define: type_define,
            typeOfServiceDefineCss: typeOfServiceDefineCss,
            type_of_service: typeOfService,
            typeOfServices: typeOfServices,
		};
	}
}
</script>

<style scoped></style>
