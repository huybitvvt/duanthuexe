<template>
    <div class="d-inline">
        <b-modal id="modal-show-car-rental" :centered="true" :scrollable="true" size="xl"
            title="Xem chi tiết khách hàng" hide-footer>


            <div class="card card-custom gutter-b">


                <div class=" ">
                    <div class="example  ">
                        <div class="example-preview table-responsive">
                            <h4 class="mb-6">Thông tin khách hàng</h4>


                            <table class="table" v-if=customer>
                                <thead>
                                    <tr>

                                        <th scope="col">Mã</th>

                                        <th scope="col"> Tên </th>
                                        <th scope="col"> SĐT</th>

                                        <th scope="col"> CCCD</th>
                                        <th scope="col"> Email</th>
                                        <th scope="col"> Địa chỉ</th>
                                        <th scope="col"> Cảnh báo</th>


                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>{{ customer.id }}</td>
                                        <td>{{ customer.name }}</td>
                                        <td>{{ customer.phone }}</td>
                                        <td>{{ customer.id_card }}</td>

                                        <td>{{ customer.email }} </td>
                                        <td>{{ customer.address }}</td>
                                        <td>{{ customer.warning }}</td>






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
                            <h4 class="mb-6">Lịch sử thuê xe</h4>
                            <table class="table edit-history" v-if="customer && customer.orders">
                                <thead>
                                    <tr>
                                        <th scope="col">Mã order </th>

                                        <th scope="col"> Đã thu </th>
                                        <th scope="col"> Tạm tính</th>
                                        <th scope="col"> Trạng thái </th>
                                        <th scope="col">Ngày thuê</th>



                                    </tr>
                                </thead>
                                <tbody>

                                    <tr v-for="(item, j) in customer.orders" :key="j">
                                        <td class="order-id"> {{
            item.id
        }}
                                        </td>


                                        <td>{{ item.pid | formatPrice }}</td>
                                        <td>{{ item.total | formatPrice }}</td>
                                        <td>{{ ORDER_STATUS_DEFINE[item.order_status] }}</td>
                                        <td>{{ item.created_at | formatDateTime }}</td>

                                    </tr>

                                </tbody>
                            </table>

                        </div>
                    </div>
                </div>

            </div>


        </b-modal>

    </div>
</template>

<script>

import { ORDER_STATUS_DEFINE } from '../../../option/orderOption';


export default {
    name: "ModalShowCustomer",

    data() {
        return {
            ORDER_STATUS_DEFINE: null // Initialize as null initially
        };
    }
    ,

    props: {
        customer: {
            type: Object,
            default: () => {
                return {};
            }
        },

    },
    mounted() {

        this.ORDER_STATUS_DEFINE = ORDER_STATUS_DEFINE;
    }




}
</script>

<style scoped></style>
