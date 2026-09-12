<template>
    <div id="car-rental" class="d-inline">
        <b-modal :centered="true" :scrollable="true" id="modal-show-car-rental" size="xl"
            title="Xem chi tiết doanh thu xe" hide-footer>

            <section id="section-vehicle" v-if="!showOrder">
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
                                            <th scope="col"> Chi phí</th>
                                            <th scope="col"> Loại xe</th>
                                            <th scope="col"> Đời xe</th>
                                            <th scope="col"> Cửa hàng</th>
                                            <th scope="col">Tổng order</th>
                                            <th scope="col">Tổng lãi thuê</th>

                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>{{ vehicle.id }}</td>
                                            <td>{{ vehicle.name }}</td>
                                            <td>{{ vehicle.brand }}</td>

                                            <td>{{ vehicle.license }}</td>
                                            <td>{{ vehicle.cost_price }}</td>
                                            <td>{{ vehicle.type }}</td>

                                            <td>{{ vehicle.year }} </td>
                                            <td>{{ vehicle.store.store_name }}</td>
                                            <td class="count_order">{{ vehicle.count_order }}</td>
                                            <td class="revenue">{{ vehicle.revenue | formatPrice }}</td>


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
                            <div class="example-preview table-responsive">
                                <h4 class="mb-6">Doanh thu</h4>
                                <table class="table edit-history" v-if="vehicle && vehicle.order_vehicle_details">
                                    <thead>
                                        <tr>
                                            <th scope="col">Mã order </th>
                                            <th scope="col">Ngày thuê</th>

                                            <th scope="col">Lãi thuê </th>
                                            <th scope="col">Lãi thuê quá hạn</th>


                                        </tr>
                                    </thead>
                                    <tbody>

                                        <tr v-for="(item, j) in vehicle.order_vehicle_details" :key="j">
                                            <td class="order-id"><a href="#" @click="openModal(item.order_id)">{{
            item.order_id
        }}</a>
                                            </td>

                                            <td>{{ item.rent_at | formatDateTime }}</td>
                                            <td> {{ item.total_money | formatPrice }}</td>
                                            <td> {{ item.money_out_date | formatPrice }}</td>


                                        </tr>

                                    </tbody>
                                </table>

                            </div>
                        </div>
                    </div>

                </div>
            </section>
            <section id="section-order" v-if="showOrder">
                <OrderUpdate :id="orderId" parent="vehicle-revenue" />

                <button style="margin: auto;display: block;" id="btn-close-order" class="btn btn-primary "
                    @click="closeModal">Close View Order</button>

            </section>
        </b-modal>

    </div>
</template>

<script>


import OrderUpdate from '../Order/components-order/OrderUpdate.vue';

export default {
    name: "ModalShowVehicleRevenue",
    components: {
        OrderUpdate
    },

    data() {
        return {
            showOrder: false,
            orderId: null

        }
    },
    computed: {

    },
    props: {
        vehicle: {
            type: Object,
            default: () => {
                return {};
            }
        },
    },
    mounted() {

    },

    methods: {

        openModal(orderId) {
            this.showOrder = true;
            this.orderId = orderId;
        },
        closeModal() {
            this.showOrder = false;
        }


    }

}
</script>

<style scoped>
#section-order {
    position: relative;

}

#btn-close-order {
    position: fixed;
    top: 34px;
    right: 300px;
}

.order-id {
    font-weight: bold;
}

.count_order,
.revenue {
    color: #0c822e;
    font-weight: bold;
}
</style>
