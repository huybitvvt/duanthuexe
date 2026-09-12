<template>
    <div class="mr-2">
        <b-button v-b-modal.modal-1 class="btn btn-sm btn-primary">Deposit
        </b-button>
        <b-modal
            id="modal-1"
            size="lg"
            title="Deposit"
            hide-footer
        >
            <p class="text-center" id="barcode">
                <vue-q-r-code-component
                    :text="`${user.wallet_public_key}`"
                    error-level="L"
                ></vue-q-r-code-component>
            </p>
            <p>BSC Network (BEP20)</p>
            <div role="group" class="mt-5">
                <label for="refferal_code"
                >Wallet address</label
                >
                <b-input-group>
                    <b-form-input
                        type="text"
                        placeholder="Link"
                        v-model="user.wallet_public_key"
                        disabled
                    ></b-form-input>
                    <b-input-group-append>
                        <b-button variant="outline-secondary"
                                  v-clipboard:copy="user.wallet_public_key"
                                  v-clipboard:success="onCopy"
                                  v-clipboard:error="onError"
                        >Copy
                        </b-button>
                        <b-button>USDT</b-button>
                    </b-input-group-append>
                </b-input-group>
            </div>
        </b-modal>
    </div>
</template>

<script>
    import VueQRCodeComponent from 'vue-qrcode-component'
    import Vue from "vue";
    import VueClipboard from "vue-clipboard2";
    import Swal from 'sweetalert2'

    Vue.use(VueClipboard)
    VueClipboard.config.autoSetContainer = true // add this line
    export default {
        name: "ModalDeposit",
        components: {
            VueQRCodeComponent,
            Swal
        },
        props: {
            user: null
        },
        data() {
            return {}
        },
        methods: {
            onCopy: function (e) {
                Swal.fire({
                    position: 'top',
                    title: 'copy successful!',
                    text: e.text,
                    icon: 'success',
                    timer: 2000
                })
            },
            onError: function () {
                Swal.fire({
                    position: 'top',
                    title: 'copy failed!',
                    text: 'Failed to copy texts',
                    icon: 'error',
                    timer: 2000
                })
            }
        }
    }
</script>

<style>
    #modal-1 img {
        margin: 0 auto;
    }
</style>
