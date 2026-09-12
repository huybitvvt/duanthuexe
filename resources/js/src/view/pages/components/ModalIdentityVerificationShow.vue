<template>
    <div class="mr-2">
        <i v-b-modal="`modal-show-kyc-${index}`" class="btn btn-sm btn-primary far fa-eye"
           style="  cursor: pointer;"></i>
        <b-modal :id="`modal-show-kyc-${index}`" size="lg" title="Verify" hide-footer>
            <div class="row front_image">
                <div class="col-sm-12">
                    <el-upload
                        action=""
                        accept="image/jpeg,image/gif,image/png"
                        list-type="picture-card"
                        :file-list="fileList"
                        :on-preview="handlePictureCardPreview"
                        :auto-upload="false"
                        ref="upload"
                        :disabled="true"
                    >
                        <i class="el-icon-upload"></i>
                        Upload
                    </el-upload>
                    <el-dialog :visible.sync="dialogVisible">
                        <img width="100%" :src="dialogImageUrl" alt="">
                    </el-dialog>
                </div>
            </div>
        </b-modal>
    </div>
</template>

<script>
    import {mapGetters} from "vuex";

    export default {
        name: "ModalIdentityVerificationShow",
        props: ['index', 'item'],
        data() {
            return {
                dialogImageUrl: '',
                dialogVisible: false,
                fileList: [],
            };
        },
        watch: {
            item() {
                this.setFileList();
            }
        },
        mounted() {
            this.setFileList();
        },
        methods: {
            setFileList() {
                this.fileList = this.item.file ? this.item.file : []
            },
            handlePictureCardPreview(file) {
                this.dialogImageUrl = file.url;
                this.dialogVisible = true;
            },
        },
        computed: {
            ...mapGetters(["currentUser"])
        }
    }
</script>
<style scoped>
    .el-upload {
        display: none !important;
    }
</style>
