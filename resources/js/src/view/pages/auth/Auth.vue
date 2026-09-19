<template>
    <div class="himoto-auth">
        <aside class="himoto-auth-brand" aria-label="Thuê xe máy du lịch cùng HiMOTO">
            <picture v-if="heroAvailable">
                <source srcset="/images/branding/himoto-journey.webp" type="image/webp" />
                <img
                    class="himoto-hero-image"
                    src="/images/branding/himoto-journey.png"
                    alt="Hai xe máy trên cung đường ven biển Việt Nam"
                    loading="eager"
                    fetchpriority="high"
                    decoding="async"
                    width="1122"
                    height="1402"
                    @error="heroAvailable = false"
                />
            </picture>
            <div v-else class="himoto-hero-copy">
                <img src="/images/branding/logo-himoto-pdf.png" alt="HiMOTO" />
                <h1>Khởi đầu hành trình.<br />Khám phá tự do.</h1>
                <p>Thuê xe máy du lịch cùng HiMOTO.</p>
            </div>
        </aside>
        <!--begin::Main Form Panel-->
        <div class="himoto-auth-main">
            <div class="himoto-language" aria-label="Ngôn ngữ hiện tại: Tiếng Việt">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="9"/><ellipse cx="12" cy="12" rx="4" ry="9"/><path d="M3 12h18M5 7h14M5 17h14"/></svg>
                <span>Tiếng Việt</span>
            </div>
            <div class="himoto-mobile-header">
                <img
                    src="/images/branding/logo-himoto-pdf.png"
                    alt="HIMOTO Logo"
                    class="himoto-mobile-logo"
                />
            </div>

            <!--begin::Signin Form Card-->
            <div v-if="state === 'signin'" class="himoto-auth-card">
                <div class="himoto-form-header">
                    <span class="himoto-form-badge">QUẢN LÝ THUÊ XE HIMOTO</span>
                    <h2 class="himoto-form-title">Chào mừng trở lại</h2>
                    <p class="himoto-form-desc">Đăng nhập để quản lý xe và đơn thuê.</p>
                </div>

                <!-- Global Alert -->
                <div
                    v-if="generalError"
                    class="himoto-alert"
                    role="alert"
                    aria-live="assertive"
                    id="himoto-general-alert"
                >
                    <span class="himoto-alert-icon" aria-hidden="true">Lỗi</span>
                    <span>{{ generalError }}</span>
                </div>

                <form @submit.prevent="handleSignin" novalidate id="himoto_login_form">
                    <!-- Email field -->
                    <div class="himoto-field-group">
                        <label for="himoto-login-email" class="himoto-field-label">Email</label>
                        <div class="himoto-input-wrapper">
                            <input
                                id="himoto-login-email"
                                type="email"
                                name="email"
                                ref="emailInput"
                                class="himoto-input"
                                :class="{'is-invalid': emailError}"
                                v-model="form.email"
                                autocomplete="username"
                                autocapitalize="none"
                                spellcheck="false"
                                placeholder="name@himoto.vn"
                                :disabled="isSubmitting"
                                @input="clearEmailError"
                                :aria-invalid="emailError ? 'true' : 'false'"
                                :aria-describedby="emailError ? 'himoto-email-error' : null"
                            />
                        </div>
                        <div
                            v-if="emailError"
                            class="himoto-field-error"
                            id="himoto-email-error"
                            role="alert"
                        >
                            <span aria-hidden="true">Lỗi</span>
                            <span>{{ emailError }}</span>
                        </div>
                    </div>

                    <!-- Password field -->
                    <div class="himoto-field-group">
                        <label for="himoto-login-password" class="himoto-field-label">Mật khẩu</label>
                        <div class="himoto-input-wrapper has-toggle">
                            <input
                                id="himoto-login-password"
                                :type="showPassword ? 'text' : 'password'"
                                name="password"
                                ref="passwordInput"
                                class="himoto-input"
                                :class="{'is-invalid': passwordError}"
                                v-model="form.password"
                                autocomplete="current-password"
                                placeholder="••••••••"
                                :disabled="isSubmitting"
                                @input="clearPasswordError"
                                :aria-invalid="passwordError ? 'true' : 'false'"
                                :aria-describedby="passwordError ? 'himoto-password-error' : null"
                            />
                            <button
                                type="button"
                                class="himoto-toggle-pwd"
                                :aria-label="showPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
                                :title="showPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
                                @click="togglePassword"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path v-if="showPassword" d="m3 3 18 18"/></svg>
                            </button>
                        </div>
                        <div
                            v-if="passwordError"
                            class="himoto-field-error"
                            id="himoto-password-error"
                            role="alert"
                        >
                            <span aria-hidden="true">Lỗi</span>
                            <span>{{ passwordError }}</span>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button
                        type="submit"
                        class="himoto-btn-submit"
                        :disabled="isSubmitting"
                        id="himoto_btn_submit"
                    >
                        <span v-if="isSubmitting" class="himoto-spinner" aria-hidden="true"></span>
                        <span>{{ isSubmitting ? 'Đang đăng nhập...' : 'Đăng nhập' }}</span><span v-if="!isSubmitting" class="himoto-submit-arrow" aria-hidden="true">→</span>
                    </button>
                </form>

                <div class="himoto-form-footer">
                    <div class="himoto-support-divider"><span>Cần hỗ trợ truy cập?</span></div>
                    <p class="himoto-support-contact">Liên hệ quản trị viên</p>
                </div>
            </div>
            <!--end::Signin Form Card-->

            <!--begin::Secondary Legacy Flows (Signup/Forgot/Reset) for backward compatibility-->
            <div v-else class="himoto-auth-card">
                <!-- Forgot Password -->
                <div v-if="state === 'forgot'">
                    <div class="himoto-form-header">
                        <span class="himoto-form-badge">HỖ TRỢ TÀI KHOẢN</span>
                        <h2 class="himoto-form-title">Quên mật khẩu</h2>
                        <p class="himoto-form-desc">Nhập email để nhận mã khôi phục mật khẩu.</p>
                    </div>
                    <form @submit.prevent="forgotPassword">
                        <div class="himoto-field-group">
                            <label class="himoto-field-label">Email</label>
                            <div class="himoto-input-wrapper">
                                <input
                                    type="email"
                                    class="himoto-input"
                                    placeholder="name@himoto.vn"
                                    v-model="formForgotPassword.email"
                                    required
                                />
                            </div>
                        </div>
                        <button
                            type="submit"
                            class="himoto-btn-submit"
                            :disabled="is_submit_forgot_password"
                        >
                            <span v-if="is_submit_forgot_password" class="himoto-spinner" aria-hidden="true"></span>
                            <span>{{ is_submit_forgot_password ? 'Đang gửi...' : 'Gửi yêu cầu' }}</span>
                        </button>
                        <div class="himoto-form-footer">
                            <a href="#" @click.prevent="showForm('signin')" style="color: #c81018; font-weight: 600;">Quay lại đăng nhập</a>
                        </div>
                    </form>
                </div>

                <!-- Reset Password -->
                <div v-else-if="state === 'reset'">
                    <div class="himoto-form-header">
                        <span class="himoto-form-badge">BẢO MẬT</span>
                        <h2 class="himoto-form-title">Đặt lại mật khẩu</h2>
                        <p class="himoto-form-desc">Nhập mã xác thực và mật khẩu mới.</p>
                    </div>
                    <form @submit.prevent="resetPassword">
                        <div class="himoto-field-group">
                            <label class="himoto-field-label">Email</label>
                            <input type="email" class="himoto-input" v-model="reset_password.email" required />
                        </div>
                        <div class="himoto-field-group">
                            <label class="himoto-field-label">Mật khẩu mới</label>
                            <input type="password" class="himoto-input" v-model="reset_password.password" required />
                        </div>
                        <div class="himoto-field-group">
                            <label class="himoto-field-label">Mã xác nhận (Token)</label>
                            <input type="text" class="himoto-input" v-model="reset_password.token" required />
                        </div>
                        <button type="submit" class="himoto-btn-submit">Đặt lại mật khẩu</button>
                        <div class="himoto-form-footer">
                            <a href="#" @click.prevent="showForm('signin')" style="color: #c81018; font-weight: 600;">Quay lại đăng nhập</a>
                        </div>
                    </form>
                </div>

                <!-- Signup -->
                <div v-else-if="state === 'signup'">
                    <div class="himoto-form-header">
                        <span class="himoto-form-badge">ĐĂNG KÝ TÀI KHOẢN</span>
                        <h2 class="himoto-form-title">Tạo tài khoản</h2>
                    </div>
                    <form @submit.prevent="handleSignup">
                        <div class="himoto-field-group">
                            <label class="himoto-field-label">Họ và tên</label>
                            <input type="text" class="himoto-input" v-model="register.name" required />
                        </div>
                        <div class="himoto-field-group">
                            <label class="himoto-field-label">Email</label>
                            <input type="email" class="himoto-input" v-model="register.email" required />
                        </div>
                        <div class="himoto-field-group">
                            <label class="himoto-field-label">Mật khẩu</label>
                            <input type="password" class="himoto-input" v-model="register.password" required />
                        </div>
                        <div class="himoto-field-group">
                            <label for="himoto-register-confirmation" class="himoto-field-label">Xác nhận mật khẩu</label>
                            <input id="himoto-register-confirmation" type="password" class="himoto-input" v-model="register.password_confirmation" autocomplete="new-password" required />
                        </div>
                        <button type="submit" class="himoto-btn-submit">Đăng ký</button>
                        <div class="himoto-form-footer">
                            <a href="#" @click.prevent="showForm('signin')" style="color: #c81018; font-weight: 600;">Quay lại đăng nhập</a>
                        </div>
                    </form>
                </div>
            </div>
            <!--end::Secondary Legacy Flows-->
            <footer class="himoto-auth-copyright">© {{ currentYear }} HiMOTO. All rights reserved.</footer>
        </div>
        <!--end::Main Form Panel-->
    </div>
</template>

<style lang="scss">
@import "@/assets/himoto/_variables.scss";
@import "@/assets/himoto/_auth.scss";
</style>

<script>
import { mapGetters, mapState } from "vuex";
import { LOGIN, LOGOUT, REGISTER, FORGOT_PASSWORD, RESET_PASSWORD } from "@/core/services/store/auth.module";
import Swal from "sweetalert2";

export default {
    name: "Auth",
    data() {
        return {
            state: "signin",
            heroAvailable: true,
            form: {
                email: "",
                password: "",
            },
            showPassword: false,
            isSubmitting: false,
            emailError: "",
            passwordError: "",
            generalError: "",

            // Secondary flows
            is_submit_forgot_password: false,
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
        currentYear() {
            return new Date().getFullYear();
        },
    },
    created() {
        this.checkLinkRefferal();
    },
    methods: {
        clearEmailError() {
            this.emailError = "";
            this.generalError = "";
        },
        clearPasswordError() {
            this.passwordError = "";
            this.generalError = "";
        },
        togglePassword() {
            this.showPassword = !this.showPassword;
        },
        validateEmailFormat(email) {
            // Standard RFC 5322 compatible regex
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(String(email).toLowerCase());
        },
        handleSignin() {
            // Prevent multiple concurrent submissions
            if (this.isSubmitting) return;

            this.emailError = "";
            this.passwordError = "";
            this.generalError = "";

            const trimmedEmail = (this.form.email || "").trim();
            const password = this.form.password || "";

            let hasError = false;

            // 1. Email validation
            if (!trimmedEmail) {
                this.emailError = "Email không được để trống";
                hasError = true;
                if (this.$refs.emailInput) {
                    this.$refs.emailInput.focus();
                }
            } else if (!this.validateEmailFormat(trimmedEmail)) {
                this.emailError = "Địa chỉ email không hợp lệ";
                hasError = true;
                if (this.$refs.emailInput) {
                    this.$refs.emailInput.focus();
                }
            }

            // 2. Password validation
            if (!password) {
                this.passwordError = "Mật khẩu không được để trống";
                if (!hasError && this.$refs.passwordInput) {
                    this.$refs.passwordInput.focus();
                }
                hasError = true;
            }

            if (hasError) {
                return;
            }

            // Valid -> Dispatch LOGIN without artificial delay
            this.isSubmitting = true;

            this.$store
                .dispatch(LOGIN, { email: trimmedEmail, password: password })
                .then(() => {
                    const user = this.$store.getters.currentUser;
                    // Role 4 (Sale) is routed to leads; other roles to dashboard
                    if (user && user.role_id === 4) {
                        this.$router.push({ name: "leads" });
                    } else {
                        this.$router.push({ name: "dashboard" });
                    }
                })
                .catch((err) => {
                    let text = "Email hoặc mật khẩu không đúng.";

                    if (!err || typeof err.status === "undefined") {
                        // Offline or network timeout
                        text = "Không thể kết nối. Vui lòng kiểm tra mạng và thử lại.";
                    } else if (err.status >= 500) {
                        text = "Hệ thống đang gặp sự cố. Vui lòng thử lại sau.";
                    } else if (err.status === 422) {
                        if (err.data && err.data.email) {
                            this.emailError = Array.isArray(err.data.email) ? err.data.email[0] : err.data.email;
                            return;
                        }
                        if (err.data && err.data.password) {
                            this.passwordError = Array.isArray(err.data.password) ? err.data.password[0] : err.data.password;
                            return;
                        }
                        if (err.data && typeof err.data.error === "string") {
                            text = err.data.error;
                        } else if (err.data && typeof err.data.message === "string") {
                            text = err.data.message;
                        }
                    } else if (err.data && typeof err.data.error === "string") {
                        text = err.data.error;
                    } else if (err.data && typeof err.data.message === "string") {
                        text = err.data.message;
                    }

                    this.generalError = text;
                })
                .finally(() => {
                    this.isSubmitting = false;
                });
        },
        showForm(form) {
            this.state = form;
            this.generalError = "";
            this.emailError = "";
            this.passwordError = "";
        },
        checkLinkRefferal() {
            // Check if current URL is a referral link (e.g. /ref/123)
            const pathname = window.location.pathname || "";
            const refMatch = pathname.match(/\/ref\/(\d+)/);
            if (refMatch && refMatch[1]) {
                const code = refMatch[1];
                localStorage.setItem("referral_code", code);
                this.register.referral_code = code;
                this.showForm("signup");
            } else {
                this.state = "signin";
            }
            let referral_code = localStorage.getItem("referral_code");
            if (referral_code) {
                this.register.referral_code = referral_code;
                this.is_disable_input_ref_code = true;
            }
        },
        forgotPassword() {
            this.is_submit_forgot_password = true;
            this.$store
                .dispatch(FORGOT_PASSWORD, this.formForgotPassword)
                .then(() => {
                    Swal.fire({
                        title: "",
                        text: "Vui lòng kiểm tra email để tiếp tục khôi phục mật khẩu!",
                        icon: "success",
                        confirmButtonClass: "btn btn-secondary",
                        heightAuto: false,
                        timer: 9000,
                    });
                    this.is_submit_forgot_password = false;
                    this.showForm("reset");
                })
                .catch((error) => {
                    this.is_submit_forgot_password = false;
                    Swal.fire({
                        title: "",
                        text: error && error.data ? error.data.message : "Có lỗi xảy ra",
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
                        text: "Đặt lại mật khẩu thành công!",
                        icon: "success",
                        confirmButtonClass: "btn btn-secondary",
                        heightAuto: false,
                        timer: 5000,
                    });
                    this.showForm("signin");
                })
                .catch((error) => {
                    Swal.fire({
                        title: "",
                        text: error && error.data ? error.data.message : "Có lỗi xảy ra",
                        icon: "error",
                        confirmButtonClass: "btn btn-secondary",
                        heightAuto: false,
                        timer: 4000,
                    });
                });
        },
        handleSignup() {
            this.$store
                .dispatch(REGISTER, this.register)
                .then(() => this.$router.push({ name: "dashboard" }))
                .catch(() => {
                    Swal.fire({
                        title: "",
                        text: "Có lỗi xảy ra trong quá trình đăng ký",
                        icon: "error",
                        confirmButtonClass: "btn btn-secondary",
                        heightAuto: false,
                    });
                });
        },
    },
};
</script>
