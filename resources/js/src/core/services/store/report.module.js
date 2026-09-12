import ApiService from "@/core/services/api.service";

// action types
export const REPORT_CAR_RENTAL = "report_car_rental";
export const REPORT_CAR_RENTAL_NEW = "report_car_rental_new";
export const REPORT_CAR_RENTAL_DAY_BY_DAY = "report_car_rental_day_by_day";

// set

const state = {};

const getters = {};

const actions = {

    [REPORT_CAR_RENTAL](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/report/detail-report", credentials)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },
	[REPORT_CAR_RENTAL_NEW](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/report/detail-report-new", credentials)
                .then(({data}) => {
                    resolve(data);
                })
                .catch(({response}) => {
                    reject(response);
                });
        });
    },
	[REPORT_CAR_RENTAL_DAY_BY_DAY](context, credentials) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/report/detail-report-day-by-day", credentials)
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
    state,
    actions,
    mutations,
    getters
};
