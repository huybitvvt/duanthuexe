import ApiService from "@/core/services/api.service";

// action types
export const LEAD_GET_ALL = "lead_get_all";
export const LEAD_INDEX = "lead_index";
export const LEAD_CREATE = "lead_create";
export const LEAD_SHOW = "lead_show";
export const LEAD_UPDATE = "lead_update";
export const LEAD_DELETE = "lead_delete";
export const LEAD_UNIQUE_USERS = "lead_unique_users";

const PREFIX_ENDPOINT = "/api/auth/leads";

const actions = {
    [LEAD_GET_ALL](_, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query(`${PREFIX_ENDPOINT}/all`, credentials)
                .then(({ data }) => resolve(data))
                .catch(({ response }) => reject(response));
        });
    },
    [LEAD_UNIQUE_USERS](_, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query(`${PREFIX_ENDPOINT}/unique-users`, credentials)
                .then(({ data }) => resolve(data))
                .catch(({ response }) => reject(response));
        });
    },
    [LEAD_INDEX](_, credentials) {
        return new Promise((res, rej) => [
            ApiService.query(`${PREFIX_ENDPOINT}`, credentials)
                .then(({ data }) => res(data))
                .catch(({ response }) => rej(response)),
        ]);
    },
    [LEAD_CREATE](_, payload) {
        return new Promise((res, rej) => {
            ApiService.post(`${PREFIX_ENDPOINT}`, payload)
                .then(({ data }) => res(data))
                .catch(({ response }) => rej(response));
        });
    },
    [LEAD_SHOW](_, id) {
        return new Promise((res, rej) => {
            ApiService.get(`${PREFIX_ENDPOINT}/${id}`)
                .then(({ data }) => res(data))
                .catch(({ response }) => rej(response));
        });
    },
    [LEAD_UPDATE](_, payload) {
        return new Promise((res, rej) => {
            const { id, ...body } = payload;
            ApiService.put(`${PREFIX_ENDPOINT}/${id}`, body)
                .then(({ data }) => res(data))
                .catch(({ response }) => rej(response));
        });
    },
    [LEAD_DELETE](_, payload) {
        return new Promise((res, rej) => {
            ApiService.post(`${PREFIX_ENDPOINT}/${payload.id}`, payload)
                .then(({ data }) => res(data))
                .catch(({ response }) => rej(response));
        });
    },
};

export default {
    state: {},
    getters: {},
    mutations: {},
    actions,
};
