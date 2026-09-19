import ApiService from "@/core/services/api.service";

// action types
export const VEHICLE_GET_ALL = "vehicle_get_all";
export const VEHICLE_WITH_REVENUE = "vehicle_with_revenue";
export const VEHICLE_GET_ALL_REPORT = "vehicle_get_all_report";
export const VEHICLE_CREATE = "vehicle_create";
export const VEHICLE_UPDATE = "vehicle_update";
export const VEHICLE_DELETE = "vehicle_delete";

export const PRICE_VEHICLES_INDEX = "price_vehicles_index";
export const PRICE_VEHICLES_UPDATE = "price_vehicles_update";
export const DELETE_PRICE_VEHICLES = "delete_price_vehicle";

export const MAINTENANCE_RULE_INDEX = "maintenance_rule_index";
export const MAINTENANCE_RULE_CREATE = "maintenance_rule_create";
export const MAINTENANCE_RULE_UPDATE = "maintenance_rule_update";
export const MAINTENANCE_RULE_DELETE = "maintenance_rule_delete";

export const MAINTENANCE_LOG_INDEX = "maintenance_log_index";
export const MAINTENANCE_LOG_CREATE = "maintenance_log_create";
export const MAINTENANCE_LOG_DELETE = "maintenance_log_delete";

export const MAINTENANCE_TYPE_GET_ALL = "maintenance_type_get_all";
export const MAINTENANCE_TYPE_INDEX = "maintenance_type_index";
export const MAINTENANCE_TYPE_CREATE = "maintenance_type_create";
export const MAINTENANCE_TYPE_UPDATE = "maintenance_type_update";
export const MAINTENANCE_TYPE_DELETE = "maintenance_type_delete";


export const MAINTENANCE_SCHEDULE_GET_ALL = "maintenance_schedule_get_all";
export const MAINTENANCE_SCHEDULE_INDEX = "maintenance_schedule_index";
export const MAINTENANCE_SCHEDULE_CREATE = "maintenance_schedule_create";
export const MAINTENANCE_SCHEDULE_UPDATE = "maintenance_schedule_update";
export const MAINTENANCE_SCHEDULE_DELETE = "maintenance_schedule_delete";


// set

const state = {};

const getters = {};

const actions = {
    [VEHICLE_GET_ALL](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/vehicle/vehicles", credentials)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },
    [VEHICLE_WITH_REVENUE](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query(
                "/api/auth/vehicle/vehicles_with_revenue",
                credentials
            )
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },
    [VEHICLE_GET_ALL_REPORT](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/vehicle/vehicles/report", credentials)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },
    [VEHICLE_CREATE](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.post("/api/auth/vehicle/vehicles/store", payload)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },

    [VEHICLE_UPDATE](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.post("/api/auth/vehicle/vehicles/update", payload)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },

    [PRICE_VEHICLES_INDEX](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/priceVehicles", payload)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },
    /**
     * api update pricing vehicles
     * @param context
     * @param payload
     * @returns {Promise<unknown>}
     */
    [PRICE_VEHICLES_UPDATE](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.post("/api/auth/priceVehicles", payload)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },

    /**
     * api delete setting price
     * @param context
     * @param id
     * @returns {Promise<unknown>}
     */
    [DELETE_PRICE_VEHICLES](context, id) {
        return new Promise((resolve, reject) => {
            ApiService.delete(`/api/auth/priceVehicles/${id}`)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },

    [VEHICLE_DELETE](context, id) {
        return new Promise((resolve, reject) => {
            ApiService.delete(`/api/auth/vehicle/vehicles/${id}`)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },
    [MAINTENANCE_RULE_INDEX](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/maintenance-rules", payload)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },
    [MAINTENANCE_RULE_CREATE](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.post("/api/auth/maintenance-rules", payload)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },
    [MAINTENANCE_RULE_UPDATE](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.post(`/api/auth/maintenance-rules`, payload)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },
    [MAINTENANCE_RULE_DELETE](context, id) {
        return new Promise((resolve, reject) => {
            ApiService.delete(`/api/auth/maintenance-rules/${id}`)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },
    [MAINTENANCE_LOG_INDEX](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/maintenance-log", payload)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },
    [MAINTENANCE_LOG_CREATE](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.post("/api/auth/maintenance-log", payload)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },
    [MAINTENANCE_LOG_DELETE](context, id) {
        return new Promise((resolve, reject) => {
            ApiService.delete(`/api/auth/maintenance-log/${id}`)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },

    [MAINTENANCE_TYPE_GET_ALL](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/maintenance-types", credentials)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },
    [MAINTENANCE_TYPE_INDEX](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/maintenance-types", payload)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },
    [MAINTENANCE_TYPE_CREATE](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.post("/api/auth/maintenance-types", payload)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },
    [MAINTENANCE_TYPE_UPDATE](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.post(`/api/auth/maintenance-types`, payload)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },
    [MAINTENANCE_TYPE_DELETE](context, id) {
        return new Promise((resolve, reject) => {
            ApiService.delete(`/api/auth/maintenance-types/${id}`)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },


    [MAINTENANCE_SCHEDULE_GET_ALL](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/maintenance-schedules", credentials)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },
    [MAINTENANCE_SCHEDULE_INDEX](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/maintenance-schedules", payload)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },
    [MAINTENANCE_SCHEDULE_CREATE](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.post("/api/auth/maintenance-schedules", payload)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },
    [MAINTENANCE_SCHEDULE_UPDATE](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.post(`/api/auth/maintenance-schedules`, payload)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },
    [MAINTENANCE_SCHEDULE_DELETE](context, id) {
        return new Promise((resolve, reject) => {
            ApiService.delete(`/api/auth/maintenance-schedules/${id}`)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch(({ response }) => {
                    reject(response);
                });
        });
    },


};

const mutations = {};

export default {
    state,
    actions,
    mutations,
    getters,
};
