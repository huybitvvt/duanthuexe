import ApiService from "@/core/services/api.service";

// action types
export const GET_ORDER_CAR_RENTAL = "get_order_car_rental";
export const GET_ORDER_CAR_RENTAL_REPORT = "get_order_car_rental_report";
export const CREATE_ORDER_CAR_RENTAL = "create_order_car_rental";
export const UPDATE_ORDER_CAR_RENTAL = "update_order_car_rental";
export const DEPOSIT_MONEY_ORDER = "deposit_order_car_rental";
export const COMPLETE_ORDER = "complete_order_car_rental";
export const DELETE_ORDER = "delete_order_car_rental";
export const SHOW_ORDER_CAR_RENTAL = "show_order_car_rental";
export const ORDER_REN_CAR_ADD_ON_PRICE = "order_ren_car_add_on_price";
export const START_ORDER = "start_car_rental";
export const CALC_ORDER_RETURN_EARLY_AMOUNT = "calc_order_return_early_amount";
export const CALC_ORDER_BEFORE_COMPLETE = "calc_order_before_complete";
export const CLOSE_DEPOSIT_ORDER = "destroy_deposit_order";
export const LOCK_ORDER_CONTRACT = "lock_order_contract";

// set

const state = {};

const getters = {};

const actions = {
    [GET_ORDER_CAR_RENTAL](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/order/car-rental", credentials)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },
    [GET_ORDER_CAR_RENTAL_REPORT](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/report/quick-report", credentials)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },

    [CREATE_ORDER_CAR_RENTAL](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.post("/api/auth/order/car-rental", credentials)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },

    [ORDER_REN_CAR_ADD_ON_PRICE](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.post("/api/auth/order/add-on-price", credentials)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },

	[CALC_ORDER_RETURN_EARLY_AMOUNT](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.post("/api/auth/order/calc_return_early_amount", credentials)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },

	[CALC_ORDER_BEFORE_COMPLETE](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.post("/api/auth/order/calc_order_before_complete", credentials)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },

	[CLOSE_DEPOSIT_ORDER](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.post("/api/auth/order/close-deposit-order", credentials)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },

    [LOCK_ORDER_CONTRACT](context, orderId) {
        return new Promise((resolve, reject) => {
            ApiService.post(`/api/auth/order/car-rental/lock-contract/${orderId}`)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },
	
    /**
     * api show order
     * @param context
     * @param id
     * @returns {Promise<unknown>}
     */
    [SHOW_ORDER_CAR_RENTAL](context, id) {
        return new Promise((resolve, reject) => {
            ApiService.get("/api/auth/order/car-rental", id)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },

    /**
     * api update order
     * @param context
     * @param payload
     * @returns {Promise<unknown>}
     */
    [UPDATE_ORDER_CAR_RENTAL](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.put("/api/auth/order/car-rental/" + payload.id, payload.params)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },

    /**
     * api deposit order
     * @param context
     * @param payload
     * @returns {Promise<unknown>}
     */
    [DEPOSIT_MONEY_ORDER](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.update("/api/auth/order/car-rental/deposit" , payload.id, payload.params)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },

    /**
     * api complete order
     * @param context
     * @param id
     * @returns {Promise<unknown>}
     */
    [COMPLETE_ORDER](context,  payload) {
        return new Promise((resolve, reject) => {
            ApiService.update("/api/auth/order/car-rental/complete",payload.order_id , payload)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },

	/**
     * api start order
     * @param context
     * @param id
     * @returns {Promise<unknown>}
     */
    [START_ORDER](context,  payload) {
        return new Promise((resolve, reject) => {
            ApiService.update("/api/auth/order/car-rental/start-contract", payload.order_id , payload)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },

    /**
     * api delete order
     * @param context
     * @param id
     * @returns {Promise<unknown>}
     */
    [DELETE_ORDER](context, id) {
        return new Promise((resolve, reject) => {
            ApiService.delete(`/api/auth/order/car-rental/${id}`)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },
};

const mutations = {};

export default {
    state, actions, mutations, getters
};
