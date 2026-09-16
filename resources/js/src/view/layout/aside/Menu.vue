<template>
    <div>
        <ul v-if="currentUser.role_id !== 4" class="menu-nav">
            <router-link to="/dashboard" v-slot="{ href, navigate, isActive, isExactActive }" >
                <li aria-haspopup="true" data-menu-toggle="hover" class="menu-item" :class="[
                    isActive && 'menu-item-active',
                    isExactActive && 'menu-item-active',
                ]">
                    <a :href="href" class="menu-link" @click="navigate">
                        <i class="menu-icon flaticon2-architecture-and-city"></i>
                        <span class="menu-text">Dashboard</span>
                    </a>
                </li>
            </router-link>

            <router-link to="/car-rental" replace v-slot="{ href, isActive }" @click.native="handleClick">
                <li aria-haspopup="true" data-menu-toggle="hover"
                    :class="[isActive && 'menu-item-active', 'menu-item']">
                    <a :href="href" class="menu-link">
                        <i class="menu-icon fas fa-shopping-cart"></i>
                        <span class="menu-text">Thuê xe</span>
                    </a>
                </li>
            </router-link>
            <router-link to="/lease-to-own" v-slot="{ href, isActive }"  @click.native="handleClick">
                <li aria-haspopup="true" data-menu-toggle="hover"
                    :class="[isActive && 'menu-item-active', 'menu-item']">
                    <a :href="href" class="menu-link">
                        <i class="menu-icon fas fa-hand-holding-usd"></i>
                        <span class="menu-text">Thuê sở hữu</span>
                    </a>
                </li>
            </router-link>
            <router-link to="/leads" v-slot="{ href, isActive }"  @click.native="handleClick">
                <li aria-haspopup="true" data-menu-toggle="hover"
                    :class="[isActive && 'menu-item-active', 'menu-item']">
                    <a :href="href" class="menu-link">
                        <i class="menu-icon fas fa-inbox"></i>
                        <span class="menu-text">Lead</span>
                    </a>
                </li>
            </router-link>
            <router-link to="/warehouses" v-slot="{ href, isActive }" @click.native="handleClick">
                <li aria-haspopup="true" data-menu-toggle="hover"
                    :class="[isActive && 'menu-item-active', 'menu-item']">
                    <a :href="href" class="menu-link">
                        <i class="menu-icon fas fa-warehouse"></i>
                        <span class="menu-text">Kho xe</span>
                    </a>
                </li>
            </router-link>
            <li  aria-haspopup="true" data-menu-toggle="hover" class="menu-item menu-item-submenu" v-bind:class="{
                'menu-item-open': hasActiveChildren('/vue-bootstrap/button'),
            }">
                <a href="#" class="menu-link menu-toggle"  >
                    <i class="menu-icon fas fa-motorcycle"></i>

                    <span class="menu-text">Quản lý xe</span>
                    <i class="menu-arrow"></i>
                </a>
                <div class="menu-submenu">
                    <span class="menu-arrow"></span>
                    <ul class="menu-subnav">
                        <li aria-haspopup="true" class="menu-item menu-item-parent">
                            <span class="menu-link">
                                <span class="menu-text"></span>
                            </span>
                        </li>

                        <router-link to="/vehicles" v-slot="{ href, navigate, isActive, isExactActive }"  @click.native="handleClick">
                            <li aria-haspopup="true" data-menu-toggle="hover" class="menu-item" :class="[
                                isActive && 'menu-item-active',
                                isExactActive && 'menu-item-active',
                            ]">
                                <a :href="href" class="menu-link" @click="navigate">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Danh sách xe </span>
                                </a>
                            </li>
                        </router-link>
                        <router-link to="/maintenance-log" v-slot="{ href, navigate, isActive, isExactActive }"  @click.native="handleClick">
                            <li aria-haspopup="true" data-menu-toggle="hover" class="menu-item" :class="[
                                isActive && 'menu-item-active',
                                isExactActive && 'menu-item-active',
                            ]">
                                <a :href="href" class="menu-link" @click="navigate">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Lịch sử bảo dưỡng</span>
                                </a>
                            </li>
                        </router-link>
                        <router-link to="/maintenance-schedule" v-slot="{ href, navigate, isActive, isExactActive }"  @click.native="handleClick">
                            <li aria-haspopup="true" data-menu-toggle="hover" class="menu-item" :class="[
                                isActive && 'menu-item-active',
                                isExactActive && 'menu-item-active',
                            ]">
                                <a :href="href" class="menu-link" @click="navigate">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Lịch hẹn bảo dưỡng</span>
                                </a>
                            </li>
                        </router-link>


                        <router-link to="/maintenance-rule" v-slot="{ href, navigate, isActive, isExactActive }"  @click.native="handleClick">
                            <li aria-haspopup="true" data-menu-toggle="hover" class="menu-item" :class="[
                                isActive && 'menu-item-active',
                                isExactActive && 'menu-item-active',
                            ]">
                                <a :href="href" class="menu-link" @click="navigate">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Tần suất bảo dưỡng chung</span>
                                </a>
                            </li>
                        </router-link>
                        <router-link to="/maintenance-type" v-slot="{ href, navigate, isActive, isExactActive }"  @click.native="handleClick">
                            <li aria-haspopup="true" data-menu-toggle="hover" class="menu-item" :class="[
                                isActive && 'menu-item-active',
                                isExactActive && 'menu-item-active',
                            ]">
                                <a :href="href" class="menu-link" @click="navigate">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Các loại bảo dưỡng</span>
                                </a>
                            </li>
                        </router-link>
                    </ul>
                </div>
            </li>

            <router-link to="/customers" v-slot="{ href, isActive }" @click.native="handleClick">
                <li aria-haspopup="true" data-menu-toggle="hover"
                    :class="[isActive && 'menu-item-active', 'menu-item']">
                    <a :href="href" class="menu-link">
                        <i class="menu-icon far fa-user"></i>
                        <span class="menu-text">Khách hàng</span>
                    </a>
                </li>
            </router-link>

            <router-link to="/stores" v-slot="{ href, isActive }" @click.native="handleClick">
                <li aria-haspopup="true" data-menu-toggle="hover"
                    :class="[isActive && 'menu-item-active', 'menu-item']">
                    <a :href="href" class="menu-link">
                        <i class="menu-icon flaticon-bag"></i>
                        <span class="menu-text">Cửa hàng</span>
                    </a>
                </li>
            </router-link>

            <li  aria-haspopup="true" data-menu-toggle="hover" class="menu-item menu-item-submenu" v-bind:class="{
                'menu-item-open': hasActiveChildren('/vue-bootstrap/button'),
            }">
                <a href="#" class="menu-link menu-toggle" >
                    <i class="menu-icon fas fa-money-bill"></i>

                    <span class="menu-text">Quản lý nguồn tiền</span>
                    <i class="menu-arrow"></i>
                </a>
                <div class="menu-submenu">
                    <span class="menu-arrow"></span>
                    <ul class="menu-subnav">
                        <li aria-haspopup="true" class="menu-item menu-item-parent">
                            <span class="menu-link">
                                <span class="menu-text"></span>
                            </span>
                        </li>

                        <router-link to="/banks" v-slot="{ href, navigate, isActive, isExactActive }" @click.native="handleClick">
                            <li aria-haspopup="true" data-menu-toggle="hover" class="menu-item" :class="[
                                isActive && 'menu-item-active',
                                isExactActive && 'menu-item-active',
                            ]">
                                <a :href="href" class="menu-link" @click="navigate">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Tài khoản ngân hàng</span>
                                </a>
                            </li>
                        </router-link>

                        <router-link to="/cash" v-slot="{ href, navigate, isActive, isExactActive }" @click.native="handleClick">
                            <li aria-haspopup="true" data-menu-toggle="hover" class="menu-item" :class="[
                                isActive && 'menu-item-active',
                                isExactActive && 'menu-item-active',
                            ]">
                                <a :href="href" class="menu-link" @click="navigate">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Tiền mặt</span>
                                </a>
                            </li>
                        </router-link>
                    </ul>
                </div>
            </li>

            <li aria-haspopup="true" data-menu-toggle="hover" class="menu-item menu-item-submenu" v-bind:class="{
                'menu-item-open': hasActiveChildren('/vue-bootstrap/button'),
            }"  >
                <a href="#" class="menu-link menu-toggle" > 
                    <i class="menu-icon fas fa-receipt"></i>

                    <span class="menu-text">Thu chi</span>
                    <i class="menu-arrow"></i>
                </a>
                <div class="menu-submenu">
                    <span class="menu-arrow"></span>
                    <ul class="menu-subnav">
                        <li aria-haspopup="true" class="menu-item menu-item-parent">
                            <span class="menu-link">
                                <span class="menu-text"></span>
                            </span>
                        </li>

                        <router-link to="/transactions" v-slot="{ href, navigate, isActive, isExactActive }" @click.native="handleClick">
                            <li aria-haspopup="true" data-menu-toggle="hover" class="menu-item" :class="[
                                isActive && 'menu-item-active',
                                isExactActive && 'menu-item-active',
                            ]">
                                <a :href="href" class="menu-link" @click="navigate">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Lịch sử thu chi</span>
                                </a>
                            </li>
                        </router-link>
                        <router-link to="/receipt" v-slot="{ href, navigate, isActive, isExactActive }" @click.native="handleClick">
                            <li aria-haspopup="true" data-menu-toggle="hover" class="menu-item" :class="[
                                isActive && 'menu-item-active',
                                isExactActive && 'menu-item-active',
                            ]">
                                <a :href="href" class="menu-link" @click="navigate">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Phiếu thu chi</span>
                                </a>
                            </li>
                        </router-link>


                    </ul>
                </div>
            </li>
            <li aria-haspopup="true" data-menu-toggle="hover" class="menu-item menu-item-submenu" v-bind:class="{
                'menu-item-open': hasActiveChildren('/vue-bootstrap/button'),
            }"  >
                <a href="#" class="menu-link menu-toggle"  >
                    <i class="menu-icon fas fa-check-double"></i>

                    <span class="menu-text">Báo cáo</span>
                    <i class="menu-arrow"></i>
                </a>
                <div class="menu-submenu">
                    <span class="menu-arrow"></span>
                    <ul class="menu-subnav">
                        <li aria-haspopup="true" class="menu-item menu-item-parent">
                            <span class="menu-link">
                                <span class="menu-text"></span>
                            </span>
                        </li>

                        <router-link to="/report/detail-report" v-slot="{ href, navigate, isActive, isExactActive }" @click.native="handleClick">
                            <li aria-haspopup="true" data-menu-toggle="hover" class="menu-item" :class="[
                                isActive && 'menu-item-active',
                                isExactActive && 'menu-item-active',
                            ]">
                                <a :href="href" class="menu-link" @click="navigate">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Tổng quan</span>
                                </a>
                            </li>
                        </router-link>

                        <router-link to="/report/vehicle-revenue" v-slot="{ href, navigate, isActive, isExactActive }" @click.native="handleClick">
                            <li aria-haspopup="true" data-menu-toggle="hover" class="menu-item" :class="[
                                isActive && 'menu-item-active',
                                isExactActive && 'menu-item-active',
                            ]">
                                <a :href="href" class="menu-link" @click="navigate">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Doanh thu xe</span>
                                </a>
                            </li>
                        </router-link>
                    </ul>
                </div>
            </li>
            <li aria-haspopup="true" data-menu-toggle="hover" class="menu-item menu-item-submenu" v-bind:class="{
                'menu-item-open': hasActiveChildren('/vue-bootstrap/button'),
            }"  >
                <a href="#" class="menu-link menu-toggle">
                    <i class="menu-icon fas fa-tools"></i>

                    <span class="menu-text">Setting</span>
                    <i class="menu-arrow"></i>
                </a>
                <div class="menu-submenu">
                    <span class="menu-arrow"></span>
                    <ul class="menu-subnav">
                        <li aria-haspopup="true" class="menu-item menu-item-parent">
                            <span class="menu-link">
                                <span class="menu-text"></span>
                            </span>
                        </li>

                        <router-link to="/pricing" v-slot="{ href, navigate, isActive, isExactActive }" @click.native="handleClick">
                            <li aria-haspopup="true" data-menu-toggle="hover" class="menu-item" :class="[
                                isActive && 'menu-item-active',
                                isExactActive && 'menu-item-active',
                            ]">
                                <a :href="href" class="menu-link" @click="navigate">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
                                    <span class="menu-text">Giá thuê</span>
                                </a>
                            </li>
                        </router-link>

                        <router-link to="/user" v-slot="{ href, navigate, isActive, isExactActive }" @click.native="handleClick">
                            <li aria-haspopup="true" data-menu-toggle="hover" class="menu-item" :class="[
                                isActive && 'menu-item-active',
                                isExactActive && 'menu-item-active',
                            ]">
                                <a :href="href" class="menu-link" @click="navigate">
                                    <i class="menu-bullet menu-bullet-dot">
                                        <span></span>
                                    </i>
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
                <li aria-haspopup="true" data-menu-toggle="hover"
                    :class="[isActive && 'menu-item-active', 'menu-item']">
                    <a :href="href" class="menu-link">
                        <i class="menu-icon fas fa-inbox"></i>
                        <span class="menu-text">Lead</span>
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
            this.setShouldClearQuery(!this.$store.state.navigate.shouldClearQuery); // Dispatch action to reset order prop
        }
    },
    computed: {
       
        ...mapGetters(["currentUser"]),
    },
};
</script>
