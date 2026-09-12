<template>
    <div>
        <button
            :disabled="is_disable_invest || is_loading_invest"
            class="btn btn-da"
            :class="{
            'btn-primary' : !is_disable_invest,
            'btn-danger' : is_disable_invest,
            'spinner spinner-white spinner-left' : is_loading_invest
            }"
            @click="investment(item)"
        >
            {{is_disable_invest ? "Invested" : "Invest"}}

        </button>
        <button
            @click="claim(item)"
            :disabled="is_disable_withdraw || is_loading_invest"
            class="btn"
            :class="{
            'btn-bg-secondary' : is_disable_withdraw,
            'btn-bg-success' : !is_disable_withdraw,
            'spinner spinner-white spinner-left' : is_loading_claim
            }"
        >
            Claim <span v-if="item.reward_day"><i class="fa far fa-clock text-primary"></i>{{item.reward_day}}</span>
        </button>
    </div>
</template>

<script>
    import Swal from "sweetalert2";
    import {INVESTMENT_POST, PACKAGE_CLAIM, SUM_SVL} from "../../../core/services/store/package.module";

    export default {
        name: "ActionItem",
        props: {
            item: {
                type: Object,
                default: () => {
                    return {};
                }
            },
        },
        data() {
            return {
                /*Loading*/
                is_loading_invest: false,
                is_loading_claim: false,
                /*End*/
                /*Disable*/
                is_disable_invest: false,
                is_disable_withdraw: true,
                /*End*/
            }
        },
        mounted() {
            this.checkButton();
        },
        watch: {
            item() {
                this.checkButton();
            }
        },
        methods: {
            checkButton() {
                if (this.item.is_invest) {
                    this.is_disable_invest = true;
                    if (this.item.is_reward_day) {
                        this.is_disable_withdraw = false;
                    }
                } else {
                    this.is_disable_invest = false;
                    this.is_disable_withdraw = true;
                }
            },
            investment(item) {
                Swal.fire({
                    title: "RISK AGREEMENT AND DISCLAIMER",
                    width: 500,
                    heightAuto: 500,
                    html:
                        "<p>Starverse Labs, when carrying out operations with cryptocurrency and investment consulting activities, notifies the client about the following risks associated with the implementation of operations in the financial market:</p>"+
                        "<h2>No investment tips</h2>" +
                        "<p>The information provided on this site does not constitute investment, trading, financial or any other advice, and nothing on this site should be construed as such. Starverse Labs does not recommend that you buy, sell, or hold any cryptocurrency. Do your own analysis and consult with a financial advisor before making any investment decision. We only provide access to a wide range of financial services, but you must make the decision yourself.</p>"+
                        "<h2>Financial risks</h2>" +
                        "<p>This risk manifests itself in an unfavorable change in the value of your financial instruments, including due to an unfavorable change in the political situation, a sharp devaluation of the national currency, a crisis in the cryptocurrency market, a banking and currency crisis, force majeure circumstances, mainly of a natural and military nature, and how as a consequence, it leads to a decrease in the value of the cryptocurrency or even losses. Depending on the chosen strategy, the market risk will consist in a decrease in the price of financial instruments. You should be aware that the value of your financial instruments can both rise and fall, and its growth in the past doesn’t mean its growth in the future. Each user should understand these risks and make decisions based on their knowledge and capabilities. Starverse Labs is not responsible for the possible loss of users' funds due to a sharp change in the rate of cryptocurrencies.</p>"+
                        "<h2>Legal risks</h2>"+
                        "<p>It is associated with the possible negative consequences of the approval of legislation or regulations, standards of self-regulatory organizations that regulate the securities market, the circulation of cryptocurrencies or other sectors of the economy, which may lead to negative consequences for you.</p>"+
                        "<p>Legal risk also includes the possibility of changing the rules for calculating tax, tax rates, cancellation of tax deductions and other changes in tax legislation that may lead to negative consequences for you. It is also necessary to take into account the risk associated with the termination or amendment of international agreements on the avoidance of double taxation, which may adversely affect the procedure and amount of taxation.</p>"+
                        "<h2>Denial of responsibility</h2>"+
                        "<p>We are not responsible for the actions of third-party services, cryptocurrency exchanges, wallets, exchange offices and other services, as well as their employees used by our users. Make a choice on your own, carefully assessing all the risks associated with a possible loss of funds due to unfair actions of the above services.</p>"+
                        "<h2>Disclosure of information about partners</h2>"+
                        "<p>We do not disclose information about our users to third parties. But in case of violations of the law by users and the receipt of a corresponding request from law enforcement agencies, whose jurisdiction applies to the actions of Starverse Labs, we will be forced to provide all the information we have about the user.</p>"+
                        "<p>Before starting investment activities, carefully consider whether the risks are acceptable for you, taking into account your personal circumstances and financial capabilities. You must clearly understand that cryptocurrency investing activities are high-risk and can lead to losses</p>",
                        showCancelButton: true,
                    confirmButtonColor: "#3085d6",
                    cancelButtonColor: "#d33",
                    confirmButtonText: "Yes"
                }).then(async result => {
                    if (result.isConfirmed) {
                        await this.callApiInvestment(item);
                    }
                });
            },
            claim(item) {
                Swal.fire({
                    title: "You definitely want to withdraw the commission?",
                    text: "You won't be able to revert this",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#3085d6",
                    cancelButtonColor: "#d33",
                    confirmButtonText: "Yes"
                }).then(async result => {
                    if (result.isConfirmed) {
                        await this.callApiClaim(item);
                    }
                });
            },
            callApiClaim(item) {
                this.is_loading_claim = true;
                this.$store.dispatch(PACKAGE_CLAIM, {
                    package_id: item.id,
                    group_id: item.group_id
                }).then((data) => {
                    Swal.fire({
                        position: 'top-end',
                        icon: 'success',
                        title: data.message,
                        showConfirmButton: false,
                        timer: 5000
                    })
                    this.is_loading_claim = false;
                    this.sumSvl();
                    this.emitInvestSuccess();
                }).catch((error) => {
                    Swal.fire({
                        position: 'top-end',
                        icon: 'error',
                        title: error.data.message,
                        showConfirmButton: false,
                        timer: 5000
                    })
                });
            },
            sumSvl() {
                this.$store.dispatch(SUM_SVL);
            },
            callApiInvestment(item) {
                this.is_loading_invest = true;
                this.$store.dispatch(INVESTMENT_POST, item).then((data) => {
                    Swal.fire({
                        position: 'top-end',
                        icon: 'success',
                        title: data.message,
                        showConfirmButton: false,
                        timer: 5000
                    })
                    this.is_loading_invest = false;
                    this.emitInvestSuccess();
                }).catch((error) => {
                    Swal.fire({
                        position: 'top-end',
                        icon: 'error',
                        title: error.data.message,
                        showConfirmButton: false,
                        timer: 5000
                    })
                });
            },
            emitInvestSuccess() {
                this.$emit('update-success');
            }
        }
    }
</script>

<style scoped>

</style>
