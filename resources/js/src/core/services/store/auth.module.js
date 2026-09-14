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
export const SET_PASSWORD = "setPassword";
export const SET_ERROR = "setError";
export const SET_REFERENCING_TREE = "setReferencingTree";

let sessionCounter = 0;
export const generateSessionId = () => `sess_${Date.now()}_${++sessionCounter}_${Math.random().toString(36).substring(2, 8)}`;

const state = {
    user: {
        tree: null
    },
    isAuthenticated: !!JwtService.getToken(),
    authSessionId: generateSessionId()
};

const getters = {
    currentUser(state) {
        return state.user;
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
                .catch(({response}) => {
                    reject(response);
                    context.commit(SET_ERROR, response.data.error);
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
    [VERIFY_AUTH](context) {
        if (JwtService.getToken()) {
            ApiService.setHeader();
            ApiService.get("/api/verify-token")
                .then(({data}) => {
                    context.commit(SET_AUTH, data);
                })
                .catch(() => {
                    context.commit(PURGE_AUTH);
                });
        } else {
            context.commit(PURGE_AUTH);
        }
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
        state.user = user ? (user.user || user.data || user) : {};
        state.errors = {};
        state.isAuthenticated = true;
        state.authSessionId = generateSessionId();
        if (user && user.access_token) {
            JwtService.saveToken(user.access_token);
        }
        ApiService.setHeader();
    },
    [SET_PASSWORD](state, password) {
        state.user.password = password;
    },
    [PURGE_AUTH](state) {
        state.isAuthenticated = false;
        state.user = {};
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
