<template>
    <div class="d-flex flex-column flex-root">
        <div
            class="login login-1 d-flex flex-column flex-lg-row flex-column-fluid bg-white"
            :class="{
                'login-signin-on': this.state == 'signin',
                'login-signup-on': this.state == 'signup',
                'login-forgot-on': this.state == 'forgot',
                'login-reset-on': this.state == 'reset',
            }"
            id="kt_login"
            :style="{
                backgroundImage: `url(${backgroundImage})`,
                backgroundSize: 'cover',
            }"
        >
            <!--begin::Content-->
            <div
                class="login-content flex-row-fluid d-flex flex-column justify-content-center position-relative overflow-hidden p-7 mx-auto"
            >
                <div class="d-flex flex-column-fluid flex-center">
                    <!--begin::Signin-->
                    <div class="login-form login-signin">
                        <form
                            class="form"
                            novalidate="novalidate"
                            id="kt_login_signin_form"
                            style="background: white; border-radius: 10px"
                        >
                            <div class="ml-8 mr-8 pt-8 pb-8">
                                <div class="pb-13 pt-lg-0 pt-5">
                                    <h3
                                        class="font-weight-bolder text-dark font-size-h4 font-size-h1-lg"
                                    >
                                        Đăng nhập
                                    </h3>
                                </div>
                                <div class="form-group">
                                    <label
                                        class="font-size-h6 font-weight-bolder text-dark"
                                        >Email</label
                                    >
                                    <div
                                        id="example-input-group-1"
                                        label=""
                                        label-for="example-input-1"
                                    >
                                        <input
                                            class="form-control form-control-solid h-auto py-7 px-6 rounded-lg"
                                            type="text"
                                            name="email"
                                            ref="email"
                                            v-model="form.email"
                                        />
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div
                                        class="d-flex justify-content-between mt-n5"
                                    >
                                        <label
                                            class="font-size-h6 font-weight-bolder text-dark pt-5"
                                            >Password</label
                                        >
                                        <!--                                        <a-->
                                        <!--                                            class="text-primary font-size-h6 font-weight-bolder text-hover-primary pt-5"-->
                                        <!--                                            id="kt_login_forgot"-->
                                        <!--                                            @click="showForm('forgot')"-->
                                        <!--                                        >Forgot Password ?</a-->
                                        <!--                                        >-->
                                    </div>
                                    <div
                                        id="example-input-group-2"
                                        label=""
                                        label-for="example-input-2"
                                    >
                                        <input
                                            class="form-control form-control-solid h-auto py-7 px-6 rounded-lg"
                                            type="password"
                                            name="password"
                                            ref="password"
                                            v-model="form.password"
                                            autocomplete="off"
                                        />
                                    </div>
                                </div>
                                <div class="pb-lg-0 pb-5">
                                    <button
                                        ref="kt_login_signin_submit"
                                        class="btn btn-primary font-weight-bolder font-size-h6 px-15 py-4 my-3 mr-3"
                                    >
                                        Đăng nhập
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <!--end::Signin-->
                    <!--begin::Signup-->
                    <div class="login-form login-signup">
                        <form
                            class="form"
                            novalidate="novalidate"
                            id="kt_login_signup_form"
                            style="background: white; border-radius: 10px"
                        >
                            <div class="ml-8 mr-8 pt-8 pb-8">
                                <div class="pb-13 pt-lg-0 pt-5">
                                    <h3
                                        class="font-weight-bolder text-dark font-size-h4 font-size-h1-lg"
                                    >
                                        Sign Up
                                    </h3>
                                    <p
                                        class="text-muted font-weight-bold font-size-h4"
                                    >
                                        Enter your details to create your
                                        account
                                    </p>
                                </div>
                                <div class="form-group">
                                    <input
                                        class="form-control form-control-solid h-auto py-7 px-6 rounded-lg font-size-h6"
                                        type="text"
                                        placeholder="Fullname"
                                        name="fullname"
                                        ref="fullname"
                                        autocomplete="off"
                                        v-model="register.name"
                                    />
                                </div>
                                <div class="form-group">
                                    <input
                                        class="form-control form-control-solid h-auto py-7 px-6 rounded-lg font-size-h6"
                                        id="email"
                                        placeholder="Email"
                                        name="email"
                                        ref="remail"
                                        autocomplete="off"
                                        v-model="register.email"
                                    />
                                </div>
                                <div class="form-group">
                                    <input
                                        class="form-control form-control-solid h-auto py-7 px-6 rounded-lg font-size-h6"
                                        type="password"
                                        placeholder="Password"
                                        name="password"
                                        ref="rpassword"
                                        autocomplete="off"
                                        v-model="register.password"
                                    />
                                </div>
                                <div class="form-group">
                                    <input
                                        class="form-control form-control-solid h-auto py-7 px-6 rounded-lg font-size-h6"
                                        type="password"
                                        placeholder="Confirm password"
                                        name="cpassword"
                                        ref="cpassword"
                                        autocomplete="off"
                                        v-model="register.password_confirmation"
                                    />
                                </div>
                                <div class="form-group">
                                    <input
                                        :disabled="true"
                                        class="form-control form-control-solid h-auto py-7 px-6 rounded-lg font-size-h6"
                                        type="text"
                                        placeholder="Referral code"
                                        autocomplete="off"
                                        v-model="register.referral_code"
                                    />
                                </div>
                                <div
                                    class="form-group d-flex flex-wrap pb-lg-0 pb-3"
                                >
                                    <button
                                        ref="kt_login_signup_submit"
                                        class="btn btn-primary font-weight-bolder font-size-h6 px-8 py-4 my-3 mr-4"
                                        style="width: 150px"
                                    >
                                        Submit
                                    </button>
                                    <button
                                        type="button"
                                        id="kt_login_signup_cancel"
                                        class="btn btn-light-primary font-weight-bolder font-size-h6 px-8 py-4 my-3"
                                        @click="showForm('signin')"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <!--end::Signup-->
                    <!--begin::Forgot-->
                    <div class="login-form login-forgot">
                        <!--begin::Form-->
                        <form
                            class="form"
                            novalidate="novalidate"
                            id="kt_login_forgot_form"
                            ref="kt_login_forgot_form"
                            style="background: white; border-radius: 10px"
                        >
                            <div class="ml-8 mr-8 pt-8 pb-8">
                                <div class="pb-13 pt-lg-0 pt-5">
                                    <h3
                                        class="font-weight-bolder text-dark font-size-h4 font-size-h1-lg"
                                    >
                                        Forgotten Password ?
                                    </h3>
                                    <p
                                        class="text-muted font-weight-bold font-size-h4"
                                    >
                                        Enter your email to reset your password
                                    </p>
                                </div>
                                <div class="form-group">
                                    <input
                                        class="form-control form-control-solid h-auto py-7 px-6 rounded-lg font-size-h6"
                                        type="email"
                                        placeholder="Email"
                                        name="email"
                                        autocomplete="off"
                                        v-model="formForgotPassword.email"
                                    />
                                </div>
                                <div
                                    class="form-group d-flex flex-wrap pb-lg-0"
                                >
                                    <button
                                        :disabled="is_submit_forgot_password"
                                        :class="{
                                            'spinner spinner-light spinner-right':
                                                is_submit_forgot_password,
                                        }"
                                        @click="forgotPassword"
                                        type="button"
                                        id="kt_login_forgot_submit"
                                        class="btn btn-primary font-weight-bolder font-size-h6 px-8 py-4 my-3 mr-4"
                                    >
                                        Submit
                                    </button>
                                    <button
                                        type="button"
                                        id="kt_login_forgot_cancel"
                                        class="btn btn-light-primary font-weight-bolder font-size-h6 px-8 py-4 my-3"
                                        @click="showForm('signin')"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <!--end::Forgot-->
                    <!--begin::Reset-->
                    <div class="login-form login-reset">
                        <!--begin::Form-->
                        <form
                            class="form"
                            novalidate="novalidate"
                            id="kt_login_reset_form"
                            ref="kt_login_reset_form"
                        >
                            <div class="pb-13 pt-lg-0 pt-5">
                                <h3
                                    class="font-weight-bolder text-dark font-size-h4 font-size-h1-lg"
                                >
                                    Reset Password?
                                </h3>
                            </div>
                            <div class="form-group">
                                <div class="form-group">
                                    <input
                                        class="form-control form-control-solid h-auto py-7 px-6 rounded-lg font-size-h6"
                                        placeholder="Email"
                                        name="email"
                                        ref="remail"
                                        autocomplete="off"
                                        v-model="reset_password.email"
                                    />
                                </div>
                                <div class="form-group">
                                    <input
                                        class="form-control form-control-solid h-auto py-7 px-6 rounded-lg font-size-h6"
                                        type="password"
                                        placeholder="Password"
                                        name="password"
                                        ref="rpassword"
                                        autocomplete="off"
                                        v-model="reset_password.password"
                                    />
                                </div>
                                <div class="form-group">
                                    <input
                                        class="form-control form-control-solid h-auto py-7 px-6 rounded-lg font-size-h6"
                                        type="text"
                                        placeholder="Token verify"
                                        name="token"
                                        ref="token"
                                        autocomplete="off"
                                        v-model="reset_password.token"
                                    />
                                </div>
                            </div>
                            <div class="form-group d-flex flex-wrap pb-lg-0">
                                <button
                                    :disabled="is_submit_forgot_password"
                                    :class="{
                                        'spinner spinner-light spinner-right':
                                            is_submit_forgot_password,
                                    }"
                                    @click="resetPassword"
                                    type="button"
                                    class="btn btn-primary font-weight-bolder font-size-h6 px-8 py-4 my-3 mr-4"
                                >
                                    Submit
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-light-primary font-weight-bolder font-size-h6 px-8 py-4 my-3"
                                    @click="showForm('signin')"
                                >
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                    <!--end::Reset-->
                </div>
            </div>
            <!--end::Content-->
        </div>
    </div>
</template>

<!-- Load login custom page styles -->
<style lang="scss">
@import "@/assets/sass/pages/login/login-1.scss";
</style>

<script>
import formValidation from "@/assets/plugins/formvalidation/dist/es6/core/Core";

// FormValidation plugins
import Trigger from "@/assets/plugins/formvalidation/dist/es6/plugins/Trigger";
import Bootstrap from "@/assets/plugins/formvalidation/dist/es6/plugins/Bootstrap";
import SubmitButton from "@/assets/plugins/formvalidation/dist/es6/plugins/SubmitButton";

import KTUtil from "@/assets/js/components/util";
import { mapGetters, mapState } from "vuex";
import { LOGIN, LOGOUT, REGISTER } from "@/core/services/store/auth.module";
import Swal from "sweetalert2";
import {
    FORGOT_PASSWORD,
    RESET_PASSWORD,
} from "../../../core/services/store/auth.module";

export default {
    name: "Auth",
    data() {
        return {
            is_submit_forgot_password: false,
            state: "signin",
            // Remove this dummy login info
            form: {
                email: "",
                password: "",
            },
            formForgotPassword: {
                email: "",
            },
            register: {
                name: "",
                email: "",
                password: "",
                password_confirmation: "",
                referral_code: "1",
            },
            reset_password: {
                email: "",
                password: "",
                token: "",
            },

            is_disable_input_ref_code: false,
        };
    },
    computed: {
        ...mapState({
            errors: (state) => state.auth.errors,
        }),
        ...mapGetters(["currentUser"]),
        backgroundImage() {
            return 'none';
            // return process.env.BASE_URL + "media/svg/illustrations/login-2.jpg";
        },
    },
    created() {
        this.checkLinkRefferal();
    },
    mounted() {
        const signin_form = KTUtil.getById("kt_login_signin_form");
        const signup_form = KTUtil.getById("kt_login_signup_form");
        const forgot_form = KTUtil.getById("kt_login_forgot_form");
        const reset_form = KTUtil.getById("kt_login_reset_form");
        this.state = "signin";
        this.fv = formValidation(signin_form, {
            fields: {
                email: {
                    validators: {
                        notEmpty: {
                            message: "Email không được để trống",
                        },
                        emailAddress: {
                            message: "Địa chỉ email không hợp lệ",
                        },
                    },
                },
                password: {
                    validators: {
                        notEmpty: {
                            message: "Mật khẩu không được để trống",
                        },
                    },
                },
            },
            plugins: {
                trigger: new Trigger(),
                submitButton: new SubmitButton(),
                bootstrap: new Bootstrap(),
            },
        });

        this.fv1 = formValidation(signup_form, {
            fields: {
                fullname: {
                    validators: {
                        notEmpty: {
                            message: "Full name is required",
                        },
                    },
                },
                email: {
                    validators: {
                        notEmpty: {
                            message: "Email is required",
                        },
                        emailAddress: {
                            message: "The value is not a valid email address",
                        },
                    },
                },
                password: {
                    validators: {
                        notEmpty: {
                            message: "Password is required",
                        },
                    },
                },
                cpassword: {
                    validators: {
                        notEmpty: {
                            message: "Confirm password is required",
                        },
                        identical: {
                            compare: function () {
                                return signup_form.querySelector(
                                    '[name="password"]',
                                ).value;
                            },
                            message:
                                "The password and its confirm are not the same",
                        },
                    },
                },
                agree: {
                    validators: {
                        notEmpty: {
                            message: "You should agree terms and conditions",
                        },
                    },
                },
            },
            plugins: {
                trigger: new Trigger(),
                submitButton: new SubmitButton(),
                bootstrap: new Bootstrap(),
            },
        });
        this.fv4 = formValidation(reset_form, {
            fields: {
                email: {
                    validators: {
                        notEmpty: {
                            message: "Email is required",
                        },
                        emailAddress: {
                            message: "The value is not a valid email address",
                        },
                    },
                },
                password: {
                    validators: {
                        notEmpty: {
                            message: "Password is required",
                        },
                    },
                },
                cpassword: {
                    validators: {
                        notEmpty: {
                            message: "Confirm password is required",
                        },
                        identical: {
                            compare: function () {
                                return signup_form.querySelector(
                                    '[name="password"]',
                                ).value;
                            },
                            message:
                                "The password and its confirm are not the same",
                        },
                    },
                },
                token: {
                    validators: {
                        notEmpty: {
                            message: "Token is required",
                        },
                    },
                },
            },
            plugins: {
                trigger: new Trigger(),
                submitButton: new SubmitButton(),
                bootstrap: new Bootstrap(),
            },
        });

        this.fv2 = formValidation(forgot_form, {
            fields: {
                email: {
                    validators: {
                        notEmpty: {
                            message: "Email is required",
                        },
                        emailAddress: {
                            message: "The value is not a valid email address",
                        },
                    },
                },
            },
            plugins: {
                trigger: new Trigger(),
                submitButton: new SubmitButton(),
                bootstrap: new Bootstrap(),
            },
        });

        this.fv.on("core.form.valid", () => {
            var email = this.form.email;
            var password = this.form.password;

            // clear existing errors
            this.$store.dispatch(LOGOUT);

            // set spinner to submit button
            const submitButton = this.$refs["kt_login_signin_submit"];
            submitButton.classList.add(
                "spinner",
                "spinner-light",
                "spinner-right",
            );

            // dummy delay
            setTimeout(() => {
                // send login request
                this.$store
                    .dispatch(LOGIN, { email, password })
                    .then((res) => {
                        this.$router.push({ name: "dashboard" });
                    })
                    .catch((res) => {
                        let text = "Information is not correct";
                        if (res?.data) {
                            if (
                                res.data.error &&
                                typeof res.data.error === "string"
                            ) {
                                text = res.data.error;
                            } else if (Object.keys(res.data).length > 0) {
                                let tmp = "";
                                for (let k of Object.keys(res.data)) {
                                    const val = res.data[k];
                                    if (typeof val === "string") {
                                        tmp = val;
                                        break;
                                    } else if (
                                        Array.isArray(val) &&
                                        val.length > 0
                                    ) {
                                        tmp = val[0];
                                        break;
                                    }
                                }

                                if (tmp?.length > 0) {
                                    text = tmp;
                                }
                            }
                        }
                        Swal.fire({
                            title: "",
                            text: text,
                            icon: "error",
                            confirmButtonClass: "btn btn-secondary",
                            heightAuto: false,
                        });
                    });

                submitButton.classList.remove(
                    "spinner",
                    "spinner-light",
                    "spinner-right",
                );
            }, 2000);
        });

        this.fv.on("core.form.invalid", () => {
            Swal.fire({
                title: "",
                text: "Please, provide correct data!",
                icon: "error",
                confirmButtonClass: "btn btn-secondary",
                heightAuto: false,
            });
        });

        this.fv1.on("core.form.valid", () => {
            const email = this.$refs.remail.value;
            const password = this.$refs.rpassword.value;

            // clear existing errors
            this.$store.dispatch(LOGOUT);

            // set spinner to submit button
            const submitButton = this.$refs["kt_login_signup_submit"];
            submitButton.classList.add(
                "spinner",
                "spinner-light",
                "spinner-right",
            );
            // this.createWallet();
            // dummy delay
            setTimeout(() => {
                this.$store
                    .dispatch(REGISTER, this.register)
                    .then(() => this.$router.push({ name: "dashboard" }))
                    .catch((error) => {
                        Swal.fire({
                            title: "",
                            text: "An error occurred during registration",
                            icon: "error",
                            confirmButtonClass: "btn btn-secondary",
                            heightAuto: false,
                        });
                    });
                submitButton.classList.remove(
                    "spinner",
                    "spinner-light",
                    "spinner-right",
                );
            }, 2000);
        });

        this.fv1.on("core.form.invalid", () => {
            Swal.fire({
                title: "",
                text: "Please, provide correct data!",
                icon: "error",
                confirmButtonClass: "btn btn-secondary",
                heightAuto: false,
            });
        });
    },
    methods: {
        checkLinkRefferal() {
            let urlCurrent = window.location.href;
            let data = urlCurrent.match(/(\d+)/g) || [];
            if (data.length) {
                localStorage.setItem("referral_code", data[0]);
                this.register.referral_code = data[0];
                this.showForm("signup");
            }
            let referral_code = localStorage.getItem("referral_code");
            if (referral_code) {
                this.register.referral_code = referral_code;
                this.is_disable_input_ref_code = true;
            }
        },
        showForm(form) {
            this.state = form;
            const form_name = "kt_login_" + form + "_form";
            const formElem = KTUtil.getById(form_name);
            if (formElem) {
                KTUtil.animateClass(
                    KTUtil.getById(form_name),
                    "animate__animated animate__backInUp",
                );
            }
        },
        forgotPassword() {
            this.is_submit_forgot_password = true;
            this.$store
                .dispatch(FORGOT_PASSWORD, this.formForgotPassword)
                .then(() => {
                    Swal.fire({
                        title: "",
                        text: "Please check your email to proceed to get the password!",
                        icon: "success",
                        confirmButtonClass: "btn btn-secondary",
                        heightAuto: false,
                        timer: 9000,
                    });
                    this.is_submit_forgot_password = false;
                    this.showForm("reset");
                })
                .catch((error) => {
                    Swal.fire({
                        title: "",
                        text: error.data.message,
                        icon: "error",
                        confirmButtonClass: "btn btn-secondary",
                        heightAuto: false,
                        timer: 4000,
                    });
                });
        },
        resetPassword() {
            this.$store
                .dispatch(RESET_PASSWORD, this.reset_password)
                .then(() => {
                    Swal.fire({
                        title: "",
                        text: "Reset password success!",
                        icon: "success",
                        confirmButtonClass: "btn btn-secondary",
                        heightAuto: false,
                        timer: 5000,
                    });
                    this.is_submit_forgot_password = false;
                    this.showForm("signin");
                })
                .catch((error) => {
                    Swal.fire({
                        title: "",
                        text: error.data.message,
                        icon: "error",
                        confirmButtonClass: "btn btn-secondary",
                        heightAuto: false,
                        timer: 4000,
                    });
                });
        },
    },
};
</script>
