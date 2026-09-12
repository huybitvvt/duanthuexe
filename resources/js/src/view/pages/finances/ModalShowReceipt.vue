<template>
    <div id="car-rental" class="d-inline">
        <b-modal :centered="true" :scrollable="true" id="modal-show-car-rental" size="xl"
            title="Xem chi tiết phiếu thu chi">


            <div class="card card-custom gutter-b">


                <div class=" ">
                    <div class="example  ">
                        <div class="example-preview table-responsive">
                            <h4 class="mb-6">Thông tin tài khoản</h4>


                            <table class="table" v-if=receipt>
                                <thead>
                                    <tr>

                                        <th scope="col">Mã</th>
                                        <th scope="col" class="min-w-130px">Ngày</th>
                                        <th scope="col">Cửa hàng</th>
                                        <th scope="col">Người thực hiện</th>
                                        <th scope="col">Loại phiếu</th>
                                        <th scope="col">Phương thức</th>
                                        <th scope="col">Số tiền</th>
                                        <th scope="col">Ghi chú</th>


                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>{{ receipt.id }}</td>
                                        <td>
                                            {{
            receipt.created_at | formatDateTime
        }}
                                        </td>
                                        <td>
                                            <span class="label label-info label-inline mr-2">{{ receipt.store.store_name
                                                }}</span>
                                        </td>
                                        <td>{{ receipt.user.name }}</td>
                                        <td>
                                            <span :class="{
            badge: true,
            'px-4': true,
            'badge-primary': [
                'in',
                'addon',
            ].includes(receipt.type),
            'badge-danger':
                receipt.type === 'out',
        }">{{ types[receipt.type] }}</span>
                                        </td>
                                        <td v-html="receipt.payment_method === 'cash'
            ? payment_methods['cash']
            : getBankInfo(receipt)
            "></td>
                                        <td>
                                            <span :class="{
            'text-danger':
                receipt.type == 'out',
        }">{{
            receipt.value | formatPrice
        }}</span>
                                        </td>
                                        <td>
                                            <el-tooltip :content="receipt.note">
                                                <span>{{
            getNote(receipt.note)
        }}</span>
                                            </el-tooltip>
                                        </td>



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
                            <h4 class="mb-6">Lịch sử chỉnh sửa</h4>
                            <table   class="table edit-history" v-if="receipt && receipt.activity_logs">
                                <thead>
                                    <tr>
                                        <th scope="col">Mã </th>
                                        <th scope="col" class="min-w-130px">Ngày</th>
                                        <th scope="col">Người thực hiện</th>
                                        <th scope="col">Nội dung chỉnh sửa </th>


                                    </tr>
                                </thead>
                                <tbody>

                                    <tr v-for="(item, j) in receipt.activity_logs" :key="j">
                                        <td>{{ item.id }}</td>
                                        <td>{{ item.created_at | formatDateTime }}</td>
                                        <td> {{ item.user.name }}</td>
                                        <td>
                                            <table>
                                                <tr>
                                                    <td></td>
                                                    <td>Trước</td>
                                                    <td>Sau</td>
                                                </tr>
                                                <tr v-if="receiptOptions"
                                                    v-for=" (col, i) in  formatMetadata(item.metadata)" :key="i">
                                                    <td><strong>{{ receiptOptions[col.colName]}}:</strong> </td>




                                                    <td> {{ col.old }} </td>
                                                    <td> {{ col.new }} </td>
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

        </b-modal>
    </div>
</template>

<script>
import { types, payment_methods } from "../../../option/transactionOption";
import { receiptOptions } from "../../../option/receiptOptions";
import { getTextShort } from "../../../utils";
import moment from "moment-timezone";
import { BANK_GET_ALL } from "@/core/services/store/banks.module";
import { STORE_GET_ALL } from "@/core/services/store/store.module";


export default {
    name: "ModalShowReceipt",
    data() {
        return {

            moment: moment,
            types: types,
            stores: [],
            banks: []
        }
    },
    computed: {
        receiptOptions() {
            return receiptOptions
        }
    },
    props: {
        receipt: {
            type: Object,
            default: () => {
                return {};
            }
        },

    },
    mounted() {
        this.getStoreAndBank()
    },

    methods: {
        getStoreAndBank() {
            this.$store.dispatch(STORE_GET_ALL, {}).then((data) => {
                this.stores = data.data;
            });
            this.$store.dispatch(BANK_GET_ALL, {}).then((data) => {
                this.banks = data.data;
            });
        },
        formatMetadata(metadata) {

            let json = JSON.parse(metadata);
            const formatPaymentMethod = (bank_id) => {
                if (bank_id == null) {
                    return 'Tiền mặt';
                } else {


                    if (this.banks) {

                        const bankInfo = this.banks.find(bank => bank.id === bank_id) || null;
                        return bankInfo.bank_name + ' - ' + bankInfo.account_number + ' - ' + bankInfo.owner_name;
                    } else {
                        return ''
                    }



                }
            }
            const formatStore = (store_id) => {
                const storeInfo = this.stores.find(store => store.id === store_id) || null;
                return storeInfo.store_name

            }


            if (json.bank_id || json.cash_id) { // có thay đổi tài khoản ngân hàng
                json.payment_method = { colName: 'payment_method' }
                json.payment_method.old = formatPaymentMethod(json.bank_id?.old || null);
                json.payment_method.new = formatPaymentMethod(json.bank_id?.new || null);


            }


            if (json.store_id) {
                json.store_id.old = formatStore(json.store_id.old)
                json.store_id.new = formatStore(json.store_id.new)
            }

            if (json.type) {
                json.type.old = this.receiptOptions[json.type.old]
                json.type.new = this.receiptOptions[json.type.new]
            }
            let arr = Object.keys(json).map(key => json[key])

            const filtered = arr.filter(item => item.colName !== "bank_id" && item.colName !== "cash_id");

            return filtered;


        },

        getNote(str) {
            return getTextShort(str);
        },
        getBankInfo(item) {
            if (item.payment_method === 2) {
                return `${item.bank.bank_name}</br>${item.bank.owner_name}</br>${item.bank.account_number}`;
            } else if (item.payment_method === 1) {
                return payment_methods[1];
            } else {
                // null => Tiền Mặt
                return payment_methods[1];
            }
        }

    }

}
</script>

<style scoped></style>
