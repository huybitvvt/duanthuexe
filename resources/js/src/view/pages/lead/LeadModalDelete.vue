<template>
    <div>

        <b-modal id="lead-modal-delete" centered title="Xóa lead" hide-footer>
            <ValidationObserver v-slot="{ handleSubmit }" ref="form">
                <form class="form" @submit.prevent="handleSubmit(deleteLead)">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Lý do xóa<span class="text-danger">(*)</span></label>
                                <ValidationProvider vid="note" name="Lý do xóa" rules="required" v-slot="{ errors }">
                                    <div>
                                        <textarea v-model="note"></textarea>
                                    </div>
                                    <error-message :errors="errors" field="note"></error-message>
                                </ValidationProvider>
                            </div>
                        </div>
                    </div>

                    <div class="row d-flex justify-content-end">

                        <el-button native-type="submit" class="btn-hoan-thanh-order"
                            style="color: #fff; background: #8950FC">
                            Xóa
                        </el-button>
                    </div>
                </form>
            </ValidationObserver>
        </b-modal>
    </div>
</template>

<script>
import moment from "moment";
import { LEAD_DELETE } from "@/core/services/store/lead.module";
import Swal from "sweetalert2";
import ErrorMessage from "../common/ErrorMessage";

export default {
    name: "LeadModalDelete",
    components: { ErrorMessage },
    props: {
        id: {
            type: Number,
            default: () => {
                return null;
            },
        },

    },
    data() {
        return {
            note: "",

        };
    },

    methods: {
        deleteLead() {
            this.$store
                .dispatch(LEAD_DELETE, { id: this.id, note: this.note })
                .then((data) => {

                    this.$message.success(data.message);
                    this.$emit("delete-success");
                    this.$bvModal.hide("lead-modal-delete");

                })
                .catch((err) => {

                    this.$message.error(err.message);
                });

        },


    },
};
</script>

<style scoped>
textarea {
    border: 1px solid gray;
    width: 100%;
    height: 100px;
    padding: 10px;
    font: inherit;
}

textarea:focus {
    outline: none;
}
</style>
