<template>
    <div class="mr-2">
        <b-button v-b-modal.modal-2 class="btn btn-sm btn-primary">Withdraw
        </b-button>
        <b-modal id="modal-2" size="lg" title="Withdraw" ok-only ok-title="Send" @ok="withdrawPost">
            <div class="d-flex mb-10">
                <button type="button" class="btn btn-lg btn-success">BSC NETWORK (BEP20)
                </button>
            </div>
            <div id="input-group-1" role="group" class="form-group">
                <label id="input-group-1__BV_label_" for="input-1" class="d-block">Wallet address:</label>
                <div>
                    <input id="input-1" type="text" placeholder="Enter address" required="required"
                           v-model="withdraw.wallet_public_key"
                           aria-required="true"
                           class="form-control">
                </div>
            </div>
            <div>
                <label for="input-1"
                       class="d-block">Amount</label>
                <b-input-group>
                    <b-form-input v-model="withdraw.amount" placeholder="Enter amount" required
                                  type="number"></b-form-input>
                    <b-input-group-append>
                        <b-button disabled>USDT</b-button>
                    </b-input-group-append>
                </b-input-group>
                <label for="input-1"
                       class="d-block mt-4">Token</label>
                <b-input-group>
                    <b-form-input v-model="withdraw.token" placeholder="Enter token" required
                                  type="text"></b-form-input>
                    <b-input-group-append>
                        <b-button @click="sendToken">Gửi token về mail</b-button>
                    </b-input-group-append>
                </b-input-group>
            </div>
        </b-modal>
    </div>
</template>

<script>

    import {WITHDRAW, WITHDRAW_SEND_TOKEN} from "../../../core/services/store/auth.module";
    import Swal from 'sweetalert2'

    export default {
        name: "ModalWithdraw",
        components: {Swal},
        data() {
            return {
                withdraw: {
                    wallet_public_key: '',
                    amount: '',
                    token: ''
                },
            }
        },
        methods: {
            withdrawPost() {
                this.$bvModal.show("modal-2");
                this.$store.dispatch(WITHDRAW, this.withdraw).then((data) => {
                    this.$bvModal.hide("modal-2");
                    Swal.fire(
                        'Update!',
                        data.data.message,
                        'success'
                    )
                }).catch((error) => {
                    this.$bvModal.show("modal-2");
                    Swal.fire(
                        'Update!',
                        error.data.message,
                        'error'
                    )
                })
            },
            sendToken() {
                this.$store.dispatch(WITHDRAW_SEND_TOKEN).then((data) => {
                    Swal.fire(
                        'Send token!',
                        data.message,
                        'success'
                    )
                })
            }
        }
    }
</script>

<style scoped>

</style>
