<template>
    <div class="w-100">
        <table class="table text-center table-vertical-center table-hover table-bordered">
            <thead>
                <tr>
                    <th scope="col">Mã giao dịch</th>
                    <th scope="col">Ngày giao dịch</th>
                    <th scope="col">Người thực hiện</th>
                    <th scope="col">Loại tiền</th>
                    <th scope="col">Số tiền</th>
                    <th scope="col">Phương thức</th>
                    <th scope="col">Ghi chú</th>
                    <th v-if=" skin != 'order-show' && order_status !== 'completed'"  scope="col">Hành động</th>
                </tr>
            </thead>
            <tbody v-if="transactionLogs.length">
                <tr v-for="(item, index) in transactionLogs" :key="index">
                    <th scope="row">{{ item.id }}</th>
                    <td>{{ item.created_at }}</td>
                    <td>{{ item.user && item.user.name }}</td>
                    <td>{{ TRANSACTION_TYPE[item.type] }}</td>
                    <td>{{ item.value | formatPrice }}</td>

                    <td>
                        <div v-if="(item.payment_method == 3 && item.cash_id && !item.bank_id) || item.payment_method === null || item.payment_method === 1">Tiền mặt</div>
                        <div v-else>
                            <p>{{ item.bank.bank_name }}</p>
                            <p>{{ item.bank.owner_name }}</p>
                            <p>{{ item.bank.account_number }}</p>
                        </div>
                    </td>
                    <td>{{ item.note }}</td>
                    <td v-if="skin != 'order-show' && order_status !== 'completed' ">
						<a v-if="item.type == GIA_HAN_THEM" title="Sửa giao dịch" @click="editTransaction(item)"
                            href="javascript:" class="btn btn-xs btn-icon btn-outline"><i class="fas fa-edit"></i>
                        </a>
                        <a v-if="item.type == GIA_HAN_THEM" title="Xóa" @click="deleteTransaction(item.id)"
                            href="javascript:" class="btn btn-xs btn-icon btn-outline-danger"><i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
            </tbody>
            <tbody v-else>
                <tr>
                    <td scope="row" colspan="6">Hợp đồng chưa có giao dịch</td>
                </tr>
            </tbody>
        </table>

		<b-modal centered v-model="dialogVisible" title="Sửa giao dịch" hide-footer>
            <ValidationObserver v-slot="{ handleSubmit }" ref="form">
                <form class="form" @submit.prevent="handleSubmit(handleOk)">
                    <div class="row">

						<div class="form-group col-md-12">
							<h5 class="mr-5"><strong>Mã giao dịch</strong></h5>
							<ValidationProvider vid="transaction_id" name="Mã giao dịch" rules="required|numeric" v-slot="{ errors }">
                                <el-input clearable placeholder="Mã giao dịch" :value="editing_item.id" name="transaction_id" disabled></el-input>
                                <error-message name="transaction_id" :errors="errors" field="transaction_id"></error-message>
                            </ValidationProvider>
						</div>

						<div class="col-md-12 form-group">
							<label><strong>Ngày giao dịch<span class="text-danger">(*)</span></strong></label>
							<ValidationProvider vid="created_at" name="Ngày giao dịch" rules="required" v-slot="{ errors }">
								<el-date-picker class="w-100" v-model="editing_item.created_at" format="dd-MM-yyyy HH:mm:ss" type="datetime" placeholder="Ngày giao dịch" name="created_at"></el-date-picker>
								<error-message name="created_at" :errors="errors" field="created_at"></error-message>
							</ValidationProvider>
                        </div>

					</div>

					<div class="row d-flex justify-content-end">
                        <button type="button" class="btn btn-primary" @click="handleOk">Cập nhật</button>
                    </div>

				</form>

			</ValidationObserver>
		</b-modal>
    </div>
</template>

<script>
import { GIA_HAN_THEM, TRANSACTION_TYPE } from "../../../../option/orderOption";
import Swal from "sweetalert2";
import { TRANSACTION_DELETE, TRANSACTION_UPDATE } from "../../../../core/services/store/transaction.module";
import ErrorMessage from "../../common/ErrorMessage";
import moment from "moment";

export default {
    name: "TransactionHistory",
    props: {
        transactionLogs: {
            type: Array,
            default: () => []
        }
        ,
        skin: {
            type: String,
            default: () => ''
        },
        order_status: {
            type: String,
            default: () => ''
        },
    },
    data() {
        return {
			dialogVisible: false,
			editing_item: {
				id: '',
				created_at: '',
			},
            TRANSACTION_TYPE: TRANSACTION_TYPE,
            GIA_HAN_THEM: GIA_HAN_THEM,
        }
    },
    methods: {
        deleteTransaction(id) {
            Swal.fire({
                title: "Bạn chắc chắn muốn xóa?",
                showDenyButton: true,
                showCancelButton: true,
                confirmButtonText: "Đồng ý",
                cancelButtonText: "Không",
            }).then((result) => {
                if (result.isConfirmed) {
                    this.$store.dispatch(TRANSACTION_DELETE, id).then((data) => {
                        this.$message.success(data.message);
                        
                        
                        this.$emit("addOnSuccess");
                    }).catch((err) => {
                        this.$message.error(err.data.message);
                    });
                }
            });

        },

		editTransaction(item) {
			this.dialogVisible = true;
			this.editing_item.id = item.id;
			this.editing_item.created_at = item.created_at;
		},

		handleOk() {
			Swal.fire({
                title: "Bạn chắc chắn muốn sửa?",
                showDenyButton: true,
                showCancelButton: true,
                confirmButtonText: "Đồng ý",
                cancelButtonText: "Hủy",
            }).then((result) => {
                if (result.isConfirmed) {
					let params = this.editing_item;
					if (params.created_at) {
						params.created_at = moment(this.editing_item.created_at).format('DD-MM-YYYY HH:mm:ss');
					}
					
                    this.$store.dispatch(TRANSACTION_UPDATE, {params}).then((data) => {
                        this.$message.success(data.message);
						this.dialogVisible = false;
						this.editing_item = {
							id: '',
							created_at: '',
						};

						this.$emit("transaction_updated");
                    }).catch((err) => {
                        this.$message.error(err.data.message);
                    });
                }
            });
        },
    },
	components: {
        ErrorMessage,
	}
}
</script>

<style scoped></style>
