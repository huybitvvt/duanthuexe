<template>
    <div id="car-rental" class="d-inline">
        <b-modal :centered="true" :scrollable="true" id="modal-show-car-rental" size="xl"
            title="Xem chi tiết tài khoản tiền mặt">


            <div class="card card-custom gutter-b">

             
                <div class=" ">
                    <div class="example  ">
                        <div class="example-preview table-responsive">
                            <h4 class="mb-6">Thông tin tài khoản</h4>
                            <table class="table table-bordered" v-if=cash>
                                <tbody>
                                    <tr>
                                      
                                        <td>Cửa hàng</td>
                                        <td>Số dư ban đầu</td>
                                        <td>Số dư hiện tại</td>
                                    </tr>
                                    <tr>
                                     
                                        <td>{{ cash.store_name }}</td>
                                        <td>{{ cash.opening_balance | formatPrice }}</td>
                                        <td>{{ cash.current_balance | formatPrice }}</td>
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
                            <h4 class="mb-6">Lịch sử giao dịch</h4>
                            <table class="table" v-if="cash && cash.transactions">
                                <thead>
                                    <tr>
                                        <th scope="col">Mã giao dịch</th>
                                        <th scope="col" class="min-w-130px">Ngày giao dịch</th>
                                        <th scope="col">Loại giao dịch</th>

                                        <th scope="col">Số tiền</th>
                                        <th scope="col">Ghi chú</th>
                                        <th scope="col">Trạng thái</th>


                                    </tr>
                                </thead>
                                <tbody>

                                    <tr v-for="item in cash.transactions">
                                        <td>{{ item.id }}</td>
                                        <td>{{ item.created_at | formatDateTime }}</td>
                                        <td>{{ item.type == 'out' ?  'Chi' : 'Thu' }}</td>



                                        <td><span :class="{ 'text-danger': item.type == 'out' }">{{ item.value |
                                            formatPrice }}</span></td>
                                        <td>{{ item.note }}</td>
                                        <td>{{ item.status }}</td>

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


export default {
    name: "ModalShowCash",
    props: {
        cash: {
            type: Object,
            default: () => {
                return {};
            }
        },

    },
 




}
</script>

<style scoped></style>
