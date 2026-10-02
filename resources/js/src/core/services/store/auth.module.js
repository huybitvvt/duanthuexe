import ApiService from "@/core/services/api.service";
import JwtService from "@/core/services/jwt.service";

// action types
export const VERIFY_AUTH = "verifyAuth";
export const LOGIN = "login";
export const LOGOUT = "logout";
export const REGISTER = "register";
export const UPDATE_PASSWORD = "updateUser";
export const UPDATE_CMT = "updateCMT";
export const VERIFY_KYC = "VERIFY_KYC";
export const GET_LIST_USER = "get_list_user";
export const WITHDRAW = "WITHDRAW";
export const WITHDRAW_SEND_TOKEN = "withdraw_send_token";
export const FORGOT_PASSWORD = "forgot_password";
export const RESET_PASSWORD = "reset_password";
export const REFERENCING_TREE = "referencing_tree";

// mutation types
export const PURGE_AUTH = "logOut";
export const SET_AUTH = "setUser";
export const SET_VERIFIED_AUTH = "setVerifiedUser";
export const SET_PASSWORD = "setPassword";
export const SET_ERROR = "setError";
export const SET_REFERENCING_TREE = "setReferencingTree";

let sessionCounter = 0;
let pendingVerification = null;
let verifiedToken = null;
let verifiedAt = 0;
const VERIFICATION_TTL = 30000;
export const generateSessionId = () => `sess_${Date.now()}_${++sessionCounter}_${Math.random().toString(36).substring(2, 8)}`;

function authScope(user, capabilities) {
    return JSON.stringify([user?.id, user?.store_id, user?.role_id, user?.role,
        [...(capabilities || [])].sort()]);
}

function applyAuth(state, payload, preserveSession = false) {
    const user = payload ? (payload.user || payload.data || payload) : {};
    const capabilities = Array.isArray(payload?.capabilities) ? payload.capabilities : [];
    const sameScope = state.isAuthenticated && authScope(state.user, state.capabilities) === authScope(user, capabilities);
    state.user = user;
    state.capabilities = capabilities;
    state.errors = {};
    state.isAuthenticated = true;
    if (!preserveSession || !sameScope) {
        state.authSessionId = generateSessionId();
        ApiService.invalidateReads();
    }
    if (payload?.access_token) JwtService.saveToken(payload.access_token);
    verifiedToken = JwtService.getToken();
    verifiedAt = Date.now();
    ApiService.setHeader();
}

const state = {
    user: {
        tree: null
    },
    capabilities: [],
    isAuthenticated: !!JwtService.getToken(),
    authSessionId: generateSessionId()
};

const getters = {
    currentUser(state) {
        return state.user;
    },
    capabilities(state) {
        return state.capabilities;
    },
    isAuthenticated(state) {
        return state.isAuthenticated;
    },
    authSessionId(state) {
        return state.authSessionId;
    }
};

const actions = {
    [LOGIN](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.post("/api/auth/login", credentials)
                .then(({data}) => {
                    context.commit(SET_AUTH, data);
                    resolve(data);
                })
                .catch((error) => {
                    const response = error && error.response ? error.response : null;
                    const errorMsg = response && response.data && response.data.error
                        ? response.data.error
                        : (response && response.data && response.data.message ? response.data.message : (error ? error.message : "Network error"));
                    context.commit(SET_ERROR, errorMsg);
                    reject(response || error);
                });
        });
    },
    [LOGOUT](context) {
        context.commit(PURGE_AUTH);
    },
    [REGISTER](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.post("/api/auth/register", credentials)
                .then(({data}) => {
                    context.commit(SET_AUTH, data);
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                    context.commit(SET_ERROR, response.data);
                });
        });
    },
    [VERIFY_AUTH](context, options = {}) {
        const token = JwtService.getToken();
        if (!token) {
            if (context.state.isAuthenticated || context.state.user?.id) context.commit(PURGE_AUTH);
            return Promise.resolve();
        }
        ApiService.setHeader();
        const session = context.state.authSessionId;
        if (!options.force && context.state.user?.id && verifiedToken === token && Date.now() - verifiedAt < VERIFICATION_TTL) {
            return Promise.resolve();
        }
        if (pendingVerification?.token === token && pendingVerification.session === session) return pendingVerification.promise;
        const current = { token, session, promise: null };
        current.promise = ApiService.get("/api/verify-token")
            .then(({data}) => {
                if (JwtService.getToken() === token && context.state.authSessionId === session) context.commit(SET_VERIFIED_AUTH, data);
            })
            .catch(error => {
                if (JwtService.getToken() !== token || context.state.authSessionId !== session) return;
                const status = error?.status || error?.response?.status;
                if (status === 401 || status === 403) context.commit(PURGE_AUTH);
                else throw error;
            })
            .finally(() => { if (pendingVerification === current) pendingVerification = null; });
        pendingVerification = current;
        return current.promise;
    },
    [UPDATE_PASSWORD](context, payload) {
        const password = payload;

        return ApiService.put("password", password).then(({data}) => {
            context.commit(SET_PASSWORD, data);
            return data;
        });
    },
    [UPDATE_CMT](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.post("/api/post/cmt", credentials)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },
    [VERIFY_KYC](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.post("/api/verify-kyc", credentials)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },
    [WITHDRAW](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.post("/api/withdraw", credentials)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },
    [WITHDRAW_SEND_TOKEN](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.get("/api/withdraw/send-token", credentials)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },
    [FORGOT_PASSWORD](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.post("/api/forgot-password", credentials)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },
    [RESET_PASSWORD](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.post("/api/reset-password", credentials)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },
    [GET_LIST_USER](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/get-list-user", credentials)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },
    [REFERENCING_TREE](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/referencing-tree", credentials)
                .then(({data}) => {
                    context.commit(SET_REFERENCING_TREE, data);
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    }
};

const mutations = {
    [SET_ERROR](state, error) {
        state.errors = error;
    },
    [SET_AUTH](state, user) {
        applyAuth(state, user);
    },
    [SET_VERIFIED_AUTH](state, user) {
        applyAuth(state, user, true);
    },
    [SET_PASSWORD](state, password) {
        state.user.password = password;
    },
    [PURGE_AUTH](state) {
        ApiService.invalidateReads();
        verifiedToken = null;
        verifiedAt = 0;
        pendingVerification = null;
        state.isAuthenticated = false;
        state.user = {};
        state.capabilities = [];
        state.errors = {};
        state.authSessionId = generateSessionId();
        JwtService.destroyToken();
        ApiService.setHeader();
    },
    [SET_REFERENCING_TREE](state, tree) {
        state.user.tree = tree;
    }
};

export default {
    state,
    actions,
    mutations,
    getters
};
