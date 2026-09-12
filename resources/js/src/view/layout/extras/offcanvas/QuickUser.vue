<template>
    <div class="topbar-item">
		<div v-if="showModalChangePassword" class="modal-overlay">
            <div class="modal">
                <div class="modal-heading">
					<h3 class="heading">Đổi mật khẩu</h3>
					<div class="close-icon" @click="showModalChangePassword = false">
						<span>×</span>
					</div>
				</div>
               
				<div class="modal-body">
					<UserChangeMyPassword :user="currentUser" @updatePasswordSuccess="showModalChangePassword = false" @updatePasswordFail="showModalChangePassword = false"></UserChangeMyPassword>
				</div>
            </div>
        </div>

        <div class="btn btn-icon w-auto btn-clean d-flex align-items-center btn-lg px-2" id="kt_quick_user_toggle">
            <span class="text-muted font-weight-bold font-size-base d-none d-md-inline mr-1">
                Hi,
            </span>
            <span class="text-dark-50 font-weight-bolder font-size-base d-none d-md-inline mr-3">
                {{ currentUser.name }}
            </span>
            <span class="symbol symbol-35 symbol-light-success">
                <img v-if="false" alt="Pic" :src="currentUserPersonalInfo.photo" />
                <span v-if="true" class="symbol-label font-size-h5 font-weight-bold">
                    {{ shortName }}
                </span>
            </span>
        </div>

        <div id="kt_quick_user" ref="kt_quick_user" class="offcanvas offcanvas-right p-10">
            <!--begin::Header-->
            <div class="offcanvas-header d-flex align-items-center justify-content-between pb-5">
                <h3 class="font-weight-bold m-0">
                    User Profile
                    <!--          <small class="text-muted font-size-sm ml-2">12 messages</small>-->
                </h3>
                <a href="#" class="btn btn-xs btn-icon btn-light btn-hover-primary" id="kt_quick_user_close">
                    <i class="ki ki-close icon-xs text-muted"></i>
                </a>
            </div>
            <!--end::Header-->

            <!--begin::Content-->
            <perfect-scrollbar class="offcanvas-content pr-5 mr-n5 scroll"
                style="max-height: 90vh; position: relative;">
                <!--begin::Header-->
                <div class="d-flex align-items-center mt-5">
                    <div class="symbol symbol-100 mr-5">
                        <img class="symbol-label" :src="currentUserPersonalInfo.photo" alt="" />
                        <i class="symbol-badge bg-success"></i>
                    </div>
                    <div class="d-flex flex-column">
                      
                        <span class="font-size-h4 font-weight-bolder">
                            {{ currentUser.name }}
                        </span>
                    
                        <span class="">
                            {{ currentUser.email }}
                        </span>

						<a class="btn-change-password mt-4" @click="showModalChangePassword = true">
                            Đổi mật khẩu
                        </a>

						<div class="separator separator-dashed mt-4 mb-4"></div>

                        <button class="btn btn-light-primary btn-bold" @click="onLogout">
                            Sign out
                        </button>
                    </div>
                </div>
                <!--end::Header-->
                <div class="separator separator-dashed mt-8 mb-5"></div>
               
            </perfect-scrollbar>
            <!--end::Content-->
        </div>
    </div>
</template>

<style lang="scss" scoped>
#kt_quick_user {
    overflow: hidden;
}

.modal-heading {
	border-bottom: 1px solid #ddd;
	margin-bottom: 20px;
	display: flex;
	justify-content: space-between;
}

.modal-heading h3 {
	font-size: 18px;
}

.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
	z-index: 2000;
}
.modal {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
	width: 600px;
    height: 240px;
    position: fixed;
    top: 50%;
    left: 50%;
    display: block;
    transform: translatex(-50%) translatey(-50%);
}
.modal-buttons {
    margin-top: 10px;
    display: flex;
    justify-content: space-between;
}
.modal-body {
	padding: 0;
}

.close-icon {
	font-size: 25px;
	cursor: pointer;
	position: relative;
	min-width: 40px;
}

.close-icon span {
	position: absolute;
	top: -20px;
	right: -10px;
}
.btn-change-password {
	color: #3699ff!important;
	cursor: pointer;
}
</style>

<script>
import { mapGetters } from "vuex";
import { LOGOUT } from "@/core/services/store/auth.module";
import KTLayoutQuickUser from "@/assets/js/layout/extended/quick-user.js";
import KTOffcanvas from "@/assets/js/components/offcanvas.js";
import UserChangeMyPassword from "../../../pages/users/components/UserChangeMyPassword";

export default {
    name: "KTQuickUser",
	components: { UserChangeMyPassword },
	data() {
		return {
			showModalChangePassword: false,
		};
	},
    mounted() {
        // Init Quick User Panel
        KTLayoutQuickUser.init(this.$refs["kt_quick_user"]);
    },
    methods: {
        onLogout() {
            this.$store
                .dispatch(LOGOUT)
                .then(() => this.$router.push({ name: "login" }));
        },
        closeOffcanvas() {
            new KTOffcanvas(KTLayoutQuickUser.getElement()).hide();
        }
    },
    computed: {
        ...mapGetters(["currentUserPersonalInfo", "currentUser"]),
        getFullName() {
            return (
                this.currentUserPersonalInfo.name +
                " " +
                this.currentUserPersonalInfo.surname
            );
        },
        shortName() {
            return String(this.currentUser.name).slice(0, 1);

        }
    }
};
</script>
