<template>
    <ValidationObserver>
        <div class="list-vehicles">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Loại bảo dưỡng</label>
                        <ValidationProvider vid="maintenance_types" name="Loại bảo dưỡng" rules="required"
                            v-slot="{ errors, classes }">
                            <el-select filterable class="w-100" placeholder="Loại bảo dưỡng" v-model="item.maintenance_type_id" clearable
                                :class="classes">
                                <el-option v-for="item2 in maintenance_types" :key="item2.id" :label="item2.name"
                                    :value="item2.id">
                                    <span style="float: left">{{ item2.name }}</span>
                                </el-option>
                            </el-select>
                            <div class="fv-plugins-message-container">
                                <div data-field="maintenance_types" data-validator="notEmpty" class="fv-help-block">{{
                                    errors[0]
                                }}
                                </div>
                            </div>
                        </ValidationProvider>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="form-group">
                        <label for="individual_interval"> Tần suất (ngày)</label>
                        <ValidationProvider vid="individual_interval" name="Tần suất" rules="required|numeric"
                            v-slot="{ errors }">
                            <el-input placeholder="Tần suất (ngày)" id="individual_interval" type="text"
                                v-model="item.individual_interval"></el-input>
                            <error-message :errors="errors" field="individual_interval"></error-message>
                        </ValidationProvider>
                    </div>
                </div>


         


                <div class="col-md-1" style="margin: auto">
                    <button type="button" class="btn btn-xs btn-outline-danger font-weight-bold" title="Xóa" @click="deleteSetting">
                        Xóa
                    </button>
                </div>
            </div>
        </div>
    </ValidationObserver>
</template>

<script>
import ErrorMessage from "../../common/ErrorMessage";
import { MAINTENANCE_TYPE_GET_ALL } from "@/core/services/store/vehicle.module";


export default {
    name: "MaintenanceSetting",
    props: {
        index: {
            type: Number,
            default: () => {
                return 0;
            },
        },
        item: {
            type: Object,
            default: () => {
                return {};
            },
        },

    },
    components: {
        ErrorMessage,

    },
    data() {
        return {
            maintenance_types: []
        };
    },
    async mounted() {
        await this.$store.dispatch(MAINTENANCE_TYPE_GET_ALL, { 'is_all': true }).then((data) => {
            this.maintenance_types = data.data;
        });

    },
    methods: {


        deleteSetting() {
            this.$emit("deleteSetting", this.index);
        },
    },
};
</script>

<style scoped></style>