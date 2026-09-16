<template>
    <div>
        <b-button class="btn btn-primary" @click="is_dialog_visible = !is_dialog_visible" native-type="button">Thanh lý</b-button>
        <b-modal centered v-model="is_dialog_visible" title="Thanh lý hợp đồng cọc" hide-footer>
            <ValidationObserver v-slot="{ handleSubmit }" ref="form">
                <form class="form form-destroy-contract" @submit.prevent="handleSubmit(closeDepositOrder)">
                    <div class="row">
						<div class="col-md-12 form-group">Trong trường hợp khách đặt cọc giữ xe nhưng không tới thuê xe thì sẽ cần thanh lý để đóng hợp đồng cọc và đưa xe về trạng thái <strong>Sẵn sàng</strong></div>

                        <div class="col-md-12 form-group">
							<label for="debt"><strong>Tùy chọn</strong></label>
							<div id="complete-transaction-via">
								<el-radio v-model="option" label="1">Thanh lý hợp đồng</el-radio>
								<el-radio v-model="option" label="2">Thanh lý hợp đồng và chuyển cọc thành phí thuê xe</el-radio>
							</div>
                        </div>

						<div class="col-md-12 form-group" v-if="option == 2">
							<label><strong>Thời điểm ghi nhận phí thuê xe<span class="text-danger">(*)</span></strong></label>
							<ValidationProvider vid="completed_at" name="Thời điểm ghi nhận phí thuê xe" rules="required" v-slot="{ errors }">
								<el-date-picker class="w-100" v-model="rental_fee_created_at" format="dd-MM-yyyy HH:mm:ss" type="datetime" placeholder="Chọn thời gian"></el-date-picker>
								<error-message :errors="errors" field="completed_at"></error-message>
							</ValidationProvider>
                        </div>
                    </div>

                    <div class="row d-flex justify-content-end">
                        <el-button native-type="button" class="btn-hoan-thanh-order" style="color: #fff; background: #8950FC" @click="closeDepositOrder" :loading="is_loading">
                            Thanh lý ngay
                        </el-button>
                    </div>

                </form>
            </ValidationObserver>
        </b-modal>
    </div>
</template>

<script>
import { CLOSE_DEPOSIT_ORDER } from "../../../../core/services/store/order.module";
import ErrorMessage from "../../common/ErrorMessage";
import moment from "moment";

export default {
    name: "ModalCloseDeposit",
    props: {
		order_id: {
            type: Number,
            default: () => {
                return 0;
            },
        },
    },
	components: {
        ErrorMessage,
    },
    data() {
        return {
			is_loading: false,
			is_dialog_visible: false,
			option: "1",
			rental_fee_created_at: new Date(),
        };
    },
    methods: {
        closeDepositOrder() { // Kích hoạt hợp đồng từ dạng đặt cọc sang dạng thuê xe.
            this.is_loading = true;

			let payload =  {
                    order_id: this.order_id,
                    option: this.option,
                };
			if ( 2 == this.option ) {
				payload['rental_fee_created_at'] = moment(this.rental_fee_created_at).format('DD-MM-YYYY HH:mm:ss');
			}

            this.$store
                .dispatch(CLOSE_DEPOSIT_ORDER, payload).then((res) => {
                    this.$emit("onSuccess");
                    this.noticeMessage(
                        "success",
                        "Đóng hợp đồng thành công",
                        res.data?.message,
                    );
                    this.is_dialog_visible = false;
                })
                .catch((err) => {
                    this.noticeMessage("error", "Thất bại", err.data?.message);
                })
                .finally(() => (this.is_loading = false));
        },
    },
};
</script>

<style scoped>

</style>
