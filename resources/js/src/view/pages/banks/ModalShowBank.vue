<template>
    <div id="car-rental" class="d-inline">
        <b-modal :centered="true" :scrollable="true" id="modal-show-car-rental" size="xl"
            title="Xem chi tiết tài khoản ngân hàng">


            <div class="card card-custom gutter-b">

             
                <div class=" ">
                    <div class="example  ">
                        <div class="example-preview table-responsive">
                            <h4 class="mb-6">Thông tin tài khoản</h4>
                            <table class="table table-bordered" v-if=bank>
                                <tbody>
                                    <tr>
                                        <td>Tên ngân hàng</td>
                                        <td>STK</td>
                                        <td>Người thụ hưởng</td>
                                        <td>Cửa hàng</td>
                                        <td>Số dư ban đầu</td>
                                        <td>Số dư hiện tại</td>
                                    </tr>
                                    <tr>
                                        <td> {{ bank.bank_name }}</td> 
                                        <td>{{ bank.account_number }}</td>
                                        <td>{{ bank.owner_name }}</td>
                                        <td>{{ bank.store_name }}</td>
                                        <td>{{ bank.opening_balance | formatPrice }}</td>
                                        <td>{{ bank.current_balance | formatPrice }}</td>
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
                            <table class="table" v-if="bank && bank.transactions">
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

                                    <tr v-for="item in bank.transactions">
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
    name: "ModalShowBank",
    props: {
        bank: {
            type: Object,
            default: () => {
                return {};
            }
        },

    },
 




}
</script>

<style scoped></style>
