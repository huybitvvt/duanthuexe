<template>
    <div class="himoto-clean-menu">
        <ul v-if="currentUser.role_id !== 4" class="menu-nav">
            <router-link to="/dashboard" v-slot="{ href, navigate, isActive, isExactActive }">
                <li aria-haspopup="true" class="menu-item" :class="[
                    isActive && 'menu-item-active',
                    isExactActive && 'menu-item-active',
                ]">
                    <a :href="href" class="menu-link" @click="navigate">
                        <span class="menu-text font-weight-bold">Dashboard</span>
                    </a>
                </li>
            </router-link>

            <router-link to="/car-rental" replace v-slot="{ href, isActive }" @click.native="handleClick">
                <li aria-haspopup="true" :class="[isActive && 'menu-item-active', 'menu-item']">
                    <a :href="href" class="menu-link">
                        <span class="menu-text font-weight-bold">Thuê xe</span>
                    </a>
                </li>
            </router-link>

            <router-link to="/lease-to-own" v-slot="{ href, isActive }" @click.native="handleClick">
                <li aria-haspopup="true" :class="[isActive && 'menu-item-active', 'menu-item']">
                    <a :href="href" class="menu-link">
                        <span class="menu-text font-weight-bold">Thuê sở hữu</span>
                    </a>
                </li>
            </router-link>

            <router-link to="/leads" v-slot="{ href, isActive }" @click.native="handleClick">
                <li aria-haspopup="true" :class="[isActive && 'menu-item-active', 'menu-item']">
                    <a :href="href" class="menu-link">
                        <span class="menu-text">Nguồn Lead</span>
                    </a>
                </li>
            </router-link>

            <router-link to="/warehouses" v-slot="{ href, isActive }" @click.native="handleClick">
                <li aria-haspopup="true" :class="[isActive && 'menu-item-active', 'menu-item']">
                    <a :href="href" class="menu-link">
                        <span class="menu-text font-weight-bold">Kho xe</span>
                    </a>
                </li>
            </router-link>

            <router-link to="/customer-reminders" v-slot="{ href, isActive }" @click.native="handleClick">
                <li aria-haspopup="true" :class="[isActive && 'menu-item-active', 'menu-item']">
                    <a :href="href" class="menu-link">
                        <span class="menu-text font-weight-bold">Nhắc nợ khách</span>
                    </a>
                </li>
            </router-link>

            <!-- Quản lý xe Submenu -->
            <li aria-haspopup="true" data-menu-toggle="hover" class="menu-item menu-item-submenu" v-bind:class="{
                'menu-item-open': hasActiveChildren('/vehicles') || hasActiveChildren('/maintenance'),
            }">
                <a href="#" class="menu-link menu-toggle">
                    <span class="menu-text">Quản lý xe</span>
                </a>
                <div class="menu-submenu">
                    <ul class="menu-subnav">
                        <router-link to="/vehicles" v-slot="{ href, navigate, isActive, isExactActive }" @click.native="handleClick">
                            <li aria-haspopup="true" class="menu-item" :class="[isActive && 'menu-item-active', isExactActive && 'menu-item-active']">
                                <a :href="href" class="menu-link" @click="navigate">
                                    <span class="menu-text">Danh sách xe</span>
                                </a>
                            </li>
                        </router-link>
                        <router-link to="/maintenance-log" v-slot="{ href, navigate, isActive, isExactActive }" @click.native="handleClick">
                            <li aria-haspopup="true" class="menu-item" :class="[isActive && 'menu-item-active', isExactActive && 'menu-item-active']">
                                <a :href="href" class="menu-link" @click="navigate">
                                    <span class="menu-text">Lịch sử bảo dưỡng</span>
                                </a>
                            </li>
                        </router-link>
                        <router-link to="/maintenance-schedule" v-slot="{ href, navigate, isActive, isExactActive }" @click.native="handleClick">
                            <li aria-haspopup="true" class="menu-item" :class="[isActive && 'menu-item-active', isExactActive && 'menu-item-active']">
                                <a :href="href" class="menu-link" @click="navigate">
                                    <span class="menu-text">Lịch hẹn bảo dưỡng</span>
                                </a>
                            </li>
                        </router-link>
                        <router-link to="/maintenance-rule" v-slot="{ href, navigate, isActive, isExactActive }" @click.native="handleClick">
                            <li aria-haspopup="true" class="menu-item" :class="[isActive && 'menu-item-active', isExactActive && 'menu-item-active']">
                                <a :href="href" class="menu-link" @click="navigate">
                                    <span class="menu-text">Tần suất bảo dưỡng</span>
                                </a>
                            </li>
                        </router-link>
                        <router-link to="/maintenance-type" v-slot="{ href, navigate, isActive, isExactActive }" @click.native="handleClick">
                            <li aria-haspopup="true" class="menu-item" :class="[isActive && 'menu-item-active', isExactActive && 'menu-item-active']">
                                <a :href="href" class="menu-link" @click="navigate">
                                    <span class="menu-text">Các loại bảo dưỡng</span>
                                </a>
                            </li>
                        </router-link>
                    </ul>
                </div>
            </li>

            <router-link to="/customers" v-slot="{ href, isActive }" @click.native="handleClick">
                <li aria-haspopup="true" :class="[isActive && 'menu-item-active', 'menu-item']">
                    <a :href="href" class="menu-link">
                        <span class="menu-text">Khách hàng</span>
                    </a>
                </li>
            </router-link>

            <!-- Cửa hàng & Nhân sự Submenu -->
            <li aria-haspopup="true" data-menu-toggle="hover" class="menu-item menu-item-submenu" v-bind:class="{
                'menu-item-open': hasActiveChildren('/stores') || hasActiveChildren('/hr'),
            }">
                <a href="#" class="menu-link menu-toggle">
                    <span class="menu-text">Cửa hàng & Nhân sự</span>
                </a>
                <div class="menu-submenu">
                    <ul class="menu-subnav">
                        <router-link to="/stores" v-slot="{ href, navigate, isActive, isExactActive }" @click.native="handleClick">
                            <li aria-haspopup="true" class="menu-item" :class="[isActive && 'menu-item-active', isExactActive && 'menu-item-active']">
                                <a :href="href" class="menu-link" @click="navigate">
                                    <span class="menu-text">Danh sách cơ sở</span>
                                </a>
                            </li>
                        </router-link>
                        <router-link to="/hr/duty-schedule" v-slot="{ href, navigate, isActive, isExactActive }" @click.native="handleClick">
                            <li aria-haspopup="true" class="menu-item" :class="[isActive && 'menu-item-active', isExactActive && 'menu-item-active']">
                                <a :href="href" class="menu-link" @click="navigate">
                                    <span class="menu-text font-weight-bold">Lịch trực cơ sở</span>
                                </a>
                            </li>
                        </router-link>
                    </ul>
                </div>
            </li>

            <!-- Quản lý nguồn tiền Submenu -->
            <li aria-haspopup="true" data-menu-toggle="hover" class="menu-item menu-item-submenu" v-bind:class="{
                'menu-item-open': hasActiveChildren('/finances') || hasActiveChildren('/banks') || hasActiveChildren('/cash'),
            }">
                <a href="#" class="menu-link menu-toggle">
                    <span class="menu-text font-weight-bold">Quản lý nguồn tiền</span>
                </a>
                <div class="menu-submenu">
                    <ul class="menu-subnav">
                        <router-link to="/finances/daily-cash-register" v-slot="{ href, navigate, isActive, isExactActive }" @click.native="handleClick">
                            <li aria-haspopup="true" class="menu-item" :class="[isActive && 'menu-item-active', isExactActive && 'menu-item-active']">
                                <a :href="href" class="menu-link" @click="navigate">
                                    <span class="menu-text font-weight-bolder text-primary">Sổ két ngày</span>
                                </a>
                            </li>
                        </router-link>
                        <router-link to="/banks" v-slot="{ href, navigate, isActive, isExactActive }" @click.native="handleClick">
                            <li aria-haspopup="true" class="menu-item" :class="[isActive && 'menu-item-active', isExactActive && 'menu-item-active']">
                                <a :href="href" class="menu-link" @click="navigate">
                                    <span class="menu-text">Tài khoản ngân hàng</span>
                                </a>
                            </li>
                        </router-link>
                        <router-link to="/cash" v-slot="{ href, navigate, isActive, isExactActive }" @click.native="handleClick">
                            <li aria-haspopup="true" class="menu-item" :class="[isActive && 'menu-item-active', isExactActive && 'menu-item-active']">
                                <a :href="href" class="menu-link" @click="navigate">
                                    <span class="menu-text">Quỹ tiền mặt</span>
                                </a>
                            </li>
                        </router-link>
                    </ul>
                </div>
            </li>

            <!-- Thu chi Submenu -->
            <li aria-haspopup="true" data-menu-toggle="hover" class="menu-item menu-item-submenu" v-bind:class="{
                'menu-item-open': hasActiveChildren('/transactions') || hasActiveChildren('/receipt'),
            }">
                <a href="#" class="menu-link menu-toggle">
                    <span class="menu-text">Thu chi</span>
                </a>
                <div class="menu-submenu">
                    <ul class="menu-subnav">
                        <router-link to="/transactions" v-slot="{ href, navigate, isActive, isExactActive }" @click.native="handleClick">
                            <li aria-haspopup="true" class="menu-item" :class="[isActive && 'menu-item-active', isExactActive && 'menu-item-active']">
                                <a :href="href" class="menu-link" @click="navigate">
                                    <span class="menu-text">Lịch sử thu chi</span>
                                </a>
                            </li>
                        </router-link>
                        <router-link to="/receipt" v-slot="{ href, navigate, isActive, isExactActive }" @click.native="handleClick">
                            <li aria-haspopup="true" class="menu-item" :class="[isActive && 'menu-item-active', isExactActive && 'menu-item-active']">
                                <a :href="href" class="menu-link" @click="navigate">
                                    <span class="menu-text">Phiếu thu chi</span>
                                </a>
                            </li>
                        </router-link>
                    </ul>
                </div>
            </li>

            <!-- Báo cáo Submenu -->
            <li aria-haspopup="true" data-menu-toggle="hover" class="menu-item menu-item-submenu" v-bind:class="{
                'menu-item-open': hasActiveChildren('/report'),
            }">
                <a href="#" class="menu-link menu-toggle">
                    <span class="menu-text">Báo cáo</span>
                </a>
                <div class="menu-submenu">
                    <ul class="menu-subnav">
                        <router-link to="/report/detail-report" v-slot="{ href, navigate, isActive, isExactActive }" @click.native="handleClick">
                            <li aria-haspopup="true" class="menu-item" :class="[isActive && 'menu-item-active', isExactActive && 'menu-item-active']">
                                <a :href="href" class="menu-link" @click="navigate">
                                    <span class="menu-text">Báo cáo tổng quan</span>
                                </a>
                            </li>
                        </router-link>
                        <router-link to="/report/vehicle-revenue" v-slot="{ href, navigate, isActive, isExactActive }" @click.native="handleClick">
                            <li aria-haspopup="true" class="menu-item" :class="[isActive && 'menu-item-active', isExactActive && 'menu-item-active']">
                                <a :href="href" class="menu-link" @click="navigate">
                                    <span class="menu-text">Doanh thu theo xe</span>
                                </a>
                            </li>
                        </router-link>
                    </ul>
                </div>
            </li>

            <!-- Cài đặt Submenu -->
            <li aria-haspopup="true" data-menu-toggle="hover" class="menu-item menu-item-submenu" v-bind:class="{
                'menu-item-open': hasActiveChildren('/pricing') || hasActiveChildren('/user'),
            }">
                <a href="#" class="menu-link menu-toggle">
                    <span class="menu-text">Cài đặt</span>
                </a>
                <div class="menu-submenu">
                    <ul class="menu-subnav">
                        <router-link to="/pricing" v-slot="{ href, navigate, isActive, isExactActive }" @click.native="handleClick">
                            <li aria-haspopup="true" class="menu-item" :class="[isActive && 'menu-item-active', isExactActive && 'menu-item-active']">
                                <a :href="href" class="menu-link" @click="navigate">
                                    <span class="menu-text">Bảng giá thuê</span>
                                </a>
                            </li>
                        </router-link>
                        <router-link to="/user" v-slot="{ href, navigate, isActive, isExactActive }" @click.native="handleClick">
                            <li aria-haspopup="true" class="menu-item" :class="[isActive && 'menu-item-active', isExactActive && 'menu-item-active']">
                                <a :href="href" class="menu-link" @click="navigate">
                                    <span class="menu-text">Người dùng</span>
                                </a>
                            </li>
                        </router-link>
                    </ul>
                </div>
            </li>
        </ul>

        <ul v-if="currentUser.role_id == 4" class="menu-nav">
            <router-link to="/leads" v-slot="{ href, isActive }" @click.native="handleClick">
                <li aria-haspopup="true" :class="[isActive && 'menu-item-active', 'menu-item']">
                    <a :href="href" class="menu-link">
                        <span class="menu-text font-weight-bold">Lead khách hàng</span>
                    </a>
                </li>
            </router-link>
        </ul>
    </div>
</template>

<script>
import { mapActions, mapGetters, mapState } from "vuex";

export default {
    name: "KTMenu",
    methods: {
        hasActiveChildren(match) {
            return this.$route["path"].indexOf(match) !== -1;
        },
        ...mapActions('navigate', ['setShouldClearQuery']),
        ...mapState({
            navigate: (state) => state.navigate,
        }),
        handleClick() {
            this.setShouldClearQuery(!this.$store.state.navigate.shouldClearQuery);
        }
    },
    computed: {
        ...mapGetters(["currentUser"]),
    },
};
</script>

<style scoped>
.himoto-clean-menu .menu-link {
    padding: 10px 16px !important;
    display: flex;
    align-items: center;
    border-radius: 6px;
    margin: 2px 8px;
    transition: background 0.15s ease, color 0.15s ease;
    text-decoration: none;
}
.himoto-clean-menu .menu-item-active > .menu-link {
    background-color: rgba(30, 144, 255, 0.12) !important;
    color: #1e88e5 !important;
    border-left: 3px solid #1e88e5;
}
.himoto-clean-menu .menu-subnav .menu-link {
    padding-left: 24px !important;
}
</style>
