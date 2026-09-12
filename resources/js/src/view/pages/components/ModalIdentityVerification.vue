<template>
    <div class="mr-2">
        <b-button v-b-modal.modal-3 class="btn btn-sm btn-primary">KYC
        </b-button>
        <b-modal id="modal-3" size="lg" title="Know Your Customer - KYC" @ok="submitUpdate" @hidden="hiddenModal">
            <div class="row front_image">
                <div class="col-sm-12">
                    <el-upload
                        :limit="2"
                        action=""
                        accept="image/jpeg,image/gif,image/png"
                        list-type="picture-card"
                        :file-list="fileList"
                        :on-change="handleChange"
                        :on-preview="handlePictureCardPreview"
                        :on-remove="handleRemove"
                        :auto-upload="false"
                        ref="upload"
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
    import {UPDATE_CMT, VERIFY_AUTH} from "../../../core/services/store/auth.module";
    import Swal from 'sweetalert2'

    export default {
        name: "ModalIdentityVerification",

        data() {
            return {
                dialogImageUrl: '',
                dialogVisible: false,
                fileList: [],
                listOder: ['Front Card', 'Behind Card'],
                stt: 0,
                hideInputUpload: false,
                textGuideUpload: 'Front Card',
                verify_user: true,
            };
        },
        mounted() {
            this.setFileList();
        },
        methods: {
            setFileList() {
                this.fileList = this.currentUser.file ? this.currentUser.file : []
            },
            handleChange(file) {
                const isImage2M = file.size / 1024 / 1024 < 2;
                if (!isImage2M) {
                    alert('File upload > 2Mb');
                    this.fileList = [];
                    return false;
                }
                console.log(file)
                this.fileList.push(file);
                this.stt++;
                //
                if (this.stt >= 2) {
                    this.hideInputUpload = true
                }
            },
            handleRemove(file, fileList) {
                if (this.stt >= 2) {
                    this.hideInputUpload = true
                }
                this.fileList = fileList
            },
            handlePictureCardPreview(file) {
                this.dialogImageUrl = file.url;
                this.dialogVisible = true;
            },
            hiddenModal(){
                this.$store.dispatch(VERIFY_AUTH);
                // this.fileList = this.currentUser.file ? this.currentUser.file : []
            },
            submitUpdate() {
                let formData = new FormData();
                if (this.fileList.length < 2) {
                    Swal.fire({
                        position: 'top',
                        title: 'Update kyc!',
                        text: 'You need to upload your passport or identity card (required number: 2 photos)',
                        icon: 'error',
                        timer: 2000
                    })
                    this.fileList = []
                    return;
                }
                this.fileList.forEach((file, key) => {
                    formData.append(`file_${key}`, file.raw)
                });
                this.$store.dispatch(UPDATE_CMT, formData).then((data) => {
                    this.fileList = data.file;
                    Swal.fire({
                        position: 'top',
                        title: 'Update kyc!',
                        text: data.message,
                        icon: 'success',
                        timer: 2000
                    })
                }).catch((error) => {
                    Swal.fire({
                        position: 'top',
                        title: 'Update kyc!',
                        text: error.message,
                        icon: 'error',
                        timer: 2000
                    })
                })
            },
        },
        computed: {
            ...mapGetters(["currentUser"])
        }
    }
</script>
<style scoped>

</style>
