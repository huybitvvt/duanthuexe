<template>
    <!-- begin:: Header Topbar -->
    <div class="topbar">

        <!--        &lt;!&ndash;begin: Search &ndash;&gt;-->
        <!--        <b-dropdown-->
        <!--            size="sm"-->
        <!--            id="kt_quick_search_toggle"-->
        <!--            variant="link"-->
        <!--            toggle-class="topbar-item text-decoration-none"-->
        <!--            no-caret-->
        <!--            right-->
        <!--            no-flip-->
        <!--        >-->
        <!--            <template v-slot:button-content>-->
        <!--                <div class="btn btn-icon btn-clean btn-lg btn-dropdown mr-1">-->
        <!--                    <span class="svg-icon svg-icon-xl svg-icon-primary">-->
        <!--                        <inline-svg src="media/svg/icons/General/Search.svg" />-->
        <!--                    </span>-->
        <!--                </div>-->
        <!--            </template>-->
        <!--            <b-dropdown-text tag="div" class="min-w-md-350px">-->
        <!--                <KTSearchDefault></KTSearchDefault>-->
        <!--            </b-dropdown-text>-->
        <!--        </b-dropdown>-->
        <!--        &lt;!&ndash;end: Search &ndash;&gt;-->

        <!--                &lt;!&ndash;begin: Notifications &ndash;&gt;-->
        <!--                <b-dropdown-->
        <!--                    size="sm"-->
        <!--                    variant="link"-->
        <!--                    toggle-class="topbar-item text-decoration-none"-->
        <!--                    no-caret-->
        <!--                    right-->
        <!--                    no-flip-->
        <!--                >-->
        <!--                    <template v-slot:button-content>-->
        <!--                        <div-->
        <!--                            class="btn btn-icon btn-clean btn-dropdown btn-lg mr-1 pulse pulse-primary"-->
        <!--                        >-->
        <!--                  <span class="svg-icon svg-icon-xl svg-icon-primary">-->
        <!--                    <inline-svg src="media/svg/icons/Code/Compiling.svg"/>-->
        <!--                  </span>-->
        <!--                            <span class="pulse-ring"></span>-->
        <!--                        </div>-->
        <!--                    </template>-->
        <!--                    <b-dropdown-text tag="div" class="min-w-md-350px">-->
        <!--                        <form>-->
        <!--                            <KTDropdownNotification></KTDropdownNotification>-->
        <!--                        </form>-->
        <!--                    </b-dropdown-text>-->
        <!--                </b-dropdown>-->
        <!--                &lt;!&ndash;end: Notifications &ndash;&gt;-->

        <!--                &lt;!&ndash;begin: Quick Actions &ndash;&gt;-->
        <!--                <b-dropdown-->
        <!--                    size="sm"-->
        <!--                    variant="link"-->
        <!--                    toggle-class="topbar-item text-decoration-none"-->
        <!--                    no-caret-->
        <!--                    right-->
        <!--                    no-flip-->
        <!--                >-->
        <!--                    <template v-slot:button-content>-->
        <!--                        <div class="btn btn-icon btn-clean btn-dropdown btn-lg mr-1">-->
        <!--                  <span class="svg-icon svg-icon-xl svg-icon-primary">-->
        <!--                    <inline-svg src="media/svg/icons/Media/Equalizer.svg"/>-->
        <!--                  </span>-->
        <!--                        </div>-->
        <!--                    </template>-->
        <!--                    <b-dropdown-text tag="div" class="min-w-md-350px">-->
        <!--                        <KTDropdownQuickAction></KTDropdownQuickAction>-->
        <!--                    </b-dropdown-text>-->
        <!--                </b-dropdown>-->
        <!--                &lt;!&ndash;end: Quick Actions &ndash;&gt;-->

        <!--                &lt;!&ndash;begin: My Cart &ndash;&gt;-->
        <!--                <b-dropdown-->
        <!--                    size="sm"-->
        <!--                    variant="link"-->
        <!--                    toggle-class="topbar-item text-decoration-none"-->
        <!--                    no-caret-->
        <!--                    right-->
        <!--                    no-flip-->
        <!--                >-->
        <!--                    <template v-slot:button-content>-->
        <!--                        <div class="btn btn-icon btn-clean btn-dropdown btn-lg mr-1">-->
        <!--                  <span class="svg-icon svg-icon-xl svg-icon-primary">-->
        <!--                    <inline-svg src="media/svg/icons/Shopping/Cart3.svg"/>-->
        <!--                  </span>-->
        <!--                        </div>-->
        <!--                    </template>-->
        <!--                    <b-dropdown-text tag="div" class="min-w-md-350px">-->
        <!--                        <KTDropdownMyCart></KTDropdownMyCart>-->
        <!--                    </b-dropdown-text>-->
        <!--                </b-dropdown>-->
        <!--                &lt;!&ndash;end: My Cart &ndash;&gt;-->

        <!--                &lt;!&ndash;begin: Quick panel toggle &ndash;&gt;-->
        <!--                <KTQuickPanel></KTQuickPanel>-->
        <!--                &lt;!&ndash;end: Quick panel toggle &ndash;&gt;-->

        <!--                &lt;!&ndash;begin: Language bar &ndash;&gt;-->
        <!--                <div class="topbar-item">-->
        <!--                    <b-dropdown-->
        <!--                        size="sm"-->
        <!--                        variant="link"-->
        <!--                        toggle-class="btn btn-icon btn-clean btn-dropdown btn-lg mr-1 text-decoration-none"-->
        <!--                        no-caret-->
        <!--                        right-->
        <!--                        no-flip-->
        <!--                    >-->
        <!--                        <template v-slot:button-content>-->
        <!--                            <img-->
        <!--                                class="h-20px w-20px rounded-sm"-->
        <!--                                :src="languageFlag || getLanguageFlag"-->
        <!--                                alt=""-->
        <!--                            />-->
        <!--                        </template>-->
        <!--                        <b-dropdown-text tag="div" class="min-w-md-175px">-->
        <!--                            <KTDropdownLanguage-->
        <!--                                v-on:language-changed="onLanguageChanged"-->
        <!--                            ></KTDropdownLanguage>-->
        <!--                        </b-dropdown-text>-->
        <!--                    </b-dropdown>-->
        <!--                </div>-->
        <!--                &lt;!&ndash;end: Language bar &ndash;&gt;-->

        <!--begin: User Bar -->
        <KTQuickUser></KTQuickUser>
        <!--end: User Bar -->
    </div>
    <!-- end:: Header Topbar -->
</template>

<style lang="scss">
    .topbar {
        .dropdown-toggle {
            padding: 0;

            &:hover {
                text-decoration: none;
            }

            &.dropdown-toggle-no-caret {
                &:after {
                    content: none;
                }
            }
        }

        .dropdown-menu {
            margin: 0;
            padding: 0;
            outline: none;

            .b-dropdown-text {
                padding: 0;
            }
        }
    }
</style>

<script>
    import KTQuickUser from "@/view/layout/extras/offcanvas/QuickUser.vue";
    import i18nService from "@/core/services/i18n.service.js";
    import UsdtContract from "../../../core/services/contract.service";
    import {mapGetters} from "vuex";
    import {ethers} from "ethers";
    import {SUM_SVL} from "../../../core/services/store/package.module";

    export default {
        name: "KTTopbar",
        data() {
            return {
                languageFlag: "",
                languages: i18nService.languages,
                balance_current: 0,
            };
        },
        computed: {
            ...mapGetters(["currentUser", "amountSvl"]),
            getLanguageFlag() {
                return this.onLanguageChanged();
            }
        },
        watch: {
        },
        mounted() {
        },
        components: {
            KTQuickUser
        },
        methods: {
            onLanguageChanged() {
                this.languageFlag = this.languages.find(val => {
                    return val.lang === i18nService.getActiveLanguage();
                }).flag;
            },
        }
    };
</script>
