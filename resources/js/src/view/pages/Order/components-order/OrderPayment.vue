<template>
    <div>
        <ValidationObserver v-slot="{ handleSubmit }" ref="form">
            <form class="form" @submit.prevent="handleSubmit(onSubmit)">
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label for="paid">Thu Khách</label>
                        <ValidationProvider name="Số tiền thu khách" rules="min_value:0" mode="lazy"
                                            v-slot="{ errors,classes }" vid="amount">
                            <money id="paid" v-model="amount" v-bind="money"
                                   class="form-control"
                                   :class="classes"></money>
                            <error-message :errors="errors" field="amount"></error-message>
                        </ValidationProvider>
                    </div>
                </div>
<!--                <div class="row">-->
<!--                    <div class="col-md-12 form-group">-->
<!--                        <label for="note">Ghi chú</label>-->
<!--                        <el-input class="w-100" placeholder="Ghi chú thanh toán" id="note" type="textarea"-->
<!--                                  v-model="order.note"></el-input>-->
<!--                    </div>-->
<!--                </div>-->
                <div class="card-toolbar mt-3 d-flex justify-content-center" v-if="orderStatus === 'renting'">
                    <el-button native-type="button" class="btn btn-sm btn-info mr-2" @click="completeOrder"
                               style="color: #fff" :loading="loadingComplete">
                        Hoàn thành toàn bộ
                    </el-button>
                    <el-button native-type="submit" class="btn btn-sm btn-success mr-2" style="color: #fff" :loading="loading">
                        Nạp tiền
                    </el-button>
                </div>
                <div class="card-toolbar mt-3 d-flex justify-content-center" v-else>
                    <h6>Hợp đồng đã hoàn thành</h6>
                </div>
            </form>
        </ValidationObserver>

    </div>
</template>

<script>
import {Money} from 'v-money';
import ErrorMessage from "../../common/ErrorMessage";
import {COMPLETE_ORDER, DEPOSIT_MONEY_ORDER} from "../../../../core/services/store/order.module";

export default {
    name: "OrderPayment",
    props: {
        id: {
            type: Number,
            default: () => { return 0 }
        },
        orderStatus: {
            type: String,
            default: () => { return '' }
        },
        suggestedAmount: {
            type: Number,
            default: 0,
        }
    },
    components: {
        ErrorMessage,
        Money,
    },
    data() {
        return {
            amount: 0,
            loading: false,
            loadingComplete: false,
            /* v-money */
            money: {
                decimal: ',',
                thousands: ',',
                prefix: '',
                suffix: ' VNĐ',
                precision: 0,
                masked: false,
            },
        }
    },
    created() {
        if(this.id){
            this.showOrder();
        }
        this.getStore();
        this.getListVehicles();
        this.getListVehiclesPrice();
    },
    watch: {
        suggestedAmount: {
            immediate: true,
            handler(value) {
                this.amount = Number(value || 0);
            },
        },
    },
    methods: {
        onSubmit() {
            this.loading = true;
            let payload = {
                id: this.id,
                params: {
                    amount: this.amount
                }
            };
            this.$store.dispatch(DEPOSIT_MONEY_ORDER, payload).then((res) => {
                this.$emit('updateSuccess');
                this.noticeMessage('success', 'Thành công', res.data?.message)
            }).catch((err) => {
                this.noticeMessage('error', 'Thất bại', err.data?.message)
            }).finally(() => this.loading = false)
        },
        completeOrder() {
            this.loadingComplete = true;
            this.$store.dispatch(COMPLETE_ORDER, this.id).then((res) => {
                this.$emit('paymentSuccess');
                this.noticeMessage('success', 'Thành công', res.data?.message)
            }).catch((err) => {
                this.noticeMessage('error', 'Thất bại', err.data?.message)
            }).finally(() => this.loadingComplete = false)
        },
    }
}
</script>

<style>
.delete-vehicle {
    position: absolute;
    left: 178px;
    top: 2px !important;
    font-size: 10px;
    cursor: pointer;
    z-index: 10;
}

.list-vehicles {
    position: relative;
}

.fa-minus-circle:hover {
    color: red;
}
</style>
