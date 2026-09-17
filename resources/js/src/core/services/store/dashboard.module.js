import ApiService from "@/core/services/api.service";
import { PURGE_AUTH } from "./auth.module";

// action types
export const DASHBOARD_REPORT = "dashboard_report";
export const DASHBOARD_REPORT_CHART = "dashboard_report_chart";
export const DASHBOARD_OVERVIEW = "dashboard_overview";
export const CLEAR_DASHBOARD_CACHE = "clear_dashboard_cache";

const state = {
    reportCache: {},
    chartCache: {},
    overviewCache: {}
};

const pendingOverviewRequests = {};

const getters = {};

function getScopedCacheKey(context, credentials) {
    const user = context.rootGetters?.currentUser || context.rootState?.auth?.user;
    const userId = user?.id ? String(user.id) : "guest";
    const storeKey = credentials?.store_id ? String(credentials.store_id) : "all";
    return `${userId}:${storeKey}`;
}

const actions = {
    [CLEAR_DASHBOARD_CACHE](context) {
        context.commit("RESET_DASHBOARD_CACHE");
    },

    [DASHBOARD_OVERVIEW](context, credentials) {
        const cacheKey = getScopedCacheKey(context, credentials);
        const cached = context.state.overviewCache[cacheKey];
        if (cached && (Date.now() - cached.timestamp < 30000)) {
            return Promise.resolve(cached.data);
        }
        if (pendingOverviewRequests[cacheKey]) {
            return pendingOverviewRequests[cacheKey];
        }

        const userAtStart = context.rootGetters?.currentUser || context.rootState?.auth?.user;
        const userIdAtStart = userAtStart?.id ? String(userAtStart.id) : "guest";
        const sessionIdAtStart = context.rootGetters?.authSessionId || context.rootState?.auth?.authSessionId;

        const overviewRequest = ApiService.query("/api/auth/dashboard/overview", credentials)
            .catch((error) => {
                const status = error?.response?.status || error?.status;
                if (status !== 404) {
                    throw error;
                }

                // Keep the dashboard usable while static and API services are
                // rolling out independently on Render.
                return Promise.all([
                    ApiService.query("/api/auth/dashboard/report", credentials),
                    ApiService.query("/api/auth/dashboard/report-chart", credentials)
                ]).then(([reportResponse, chartResponse]) => ({
                    data: {
                        error: false,
                        data: {
                            report: reportResponse?.data?.data || {},
                            chart: chartResponse?.data?.data || {}
                        }
                    }
                }));
            });

        pendingOverviewRequests[cacheKey] = overviewRequest
            .then(({ data }) => {
                const currentSessionId = context.rootGetters?.authSessionId || context.rootState?.auth?.authSessionId;
                const currentUser = context.rootGetters?.currentUser || context.rootState?.auth?.user;
                const currentUserId = currentUser?.id ? String(currentUser.id) : "guest";
                const isAuth = context.rootGetters?.isAuthenticated;

                if (isAuth && currentSessionId === sessionIdAtStart && currentUserId === userIdAtStart) {
                    context.commit("SET_DASHBOARD_OVERVIEW_CACHE", { key: cacheKey, data });
                }
                return data;
            })
            .catch((error) => {
                throw error?.response || error;
            })
            .finally(() => {
                delete pendingOverviewRequests[cacheKey];
            });

        return pendingOverviewRequests[cacheKey];
    },

    [DASHBOARD_REPORT](context, credentials) {
        const userAtStart = context.rootGetters?.currentUser || context.rootState?.auth?.user;
        const userIdAtStart = userAtStart?.id ? String(userAtStart.id) : "guest";
        const sessionIdAtStart = context.rootGetters?.authSessionId || context.rootState?.auth?.authSessionId;
        const storeKey = credentials?.store_id ? String(credentials.store_id) : "all";
        const cacheKey = `${userIdAtStart}:${storeKey}`;

        const cached = context.state.reportCache[cacheKey];
        if (cached && (Date.now() - cached.timestamp < 30000)) {
            return Promise.resolve(cached.data);
        }

        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/dashboard/report", credentials)
                .then(({data}) => {
                    // Strict Session Generation & Auth Guard:
                    // Only commit if the exact same session is still active and authenticated.
                    // If user logged out or re-logged in (even with same user_id), sessionIdAtStart !== currentSessionId,
                    // and this late response is safely discarded.
                    const currentSessionId = context.rootGetters?.authSessionId || context.rootState?.auth?.authSessionId;
                    const isAuth = context.rootGetters?.isAuthenticated;

                    if (currentSessionId && currentSessionId === sessionIdAtStart && isAuth) {
                        context.commit("SET_DASHBOARD_REPORT_CACHE", { key: cacheKey, data });
                    }
                    resolve(data);
                })
                .catch((error) => {
                    reject(error?.response || error);
                });
        });
    },

    [DASHBOARD_REPORT_CHART](context, credentials) {
        const userAtStart = context.rootGetters?.currentUser || context.rootState?.auth?.user;
        const userIdAtStart = userAtStart?.id ? String(userAtStart.id) : "guest";
        const sessionIdAtStart = context.rootGetters?.authSessionId || context.rootState?.auth?.authSessionId;
        const storeKey = credentials?.store_id ? String(credentials.store_id) : "all";
        const cacheKey = `${userIdAtStart}:${storeKey}`;

        const cached = context.state.chartCache[cacheKey];
        if (cached && (Date.now() - cached.timestamp < 30000)) {
            return Promise.resolve(cached.data);
        }

        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/dashboard/report-chart", credentials)
                .then(({data}) => {
                    // Strict Session Generation & Auth Guard
                    const currentSessionId = context.rootGetters?.authSessionId || context.rootState?.auth?.authSessionId;
                    const isAuth = context.rootGetters?.isAuthenticated;

                    if (currentSessionId && currentSessionId === sessionIdAtStart && isAuth) {
                        context.commit("SET_DASHBOARD_CHART_CACHE", { key: cacheKey, data });
                    }
                    resolve(data);
                })
                .catch((error) => {
                    reject(error?.response || error);
                });
        });
    },
};

const mutations = {
    [PURGE_AUTH](state) {
        state.reportCache = {};
        state.chartCache = {};
        state.overviewCache = {};
    },
    RESET_DASHBOARD_CACHE(state) {
        state.reportCache = {};
        state.chartCache = {};
        state.overviewCache = {};
    },
    SET_DASHBOARD_REPORT_CACHE(state, { key, data }) {
        state.reportCache[key] = { data, timestamp: Date.now() };
    },
    SET_DASHBOARD_CHART_CACHE(state, { key, data }) {
        state.chartCache[key] = { data, timestamp: Date.now() };
    },
    SET_DASHBOARD_OVERVIEW_CACHE(state, { key, data }) {
        state.overviewCache[key] = { data, timestamp: Date.now() };
    }
};

export default {
    state,
    actions,
    mutations,
    getters
};
