import ApiService from "@/core/services/api.service";

// action types
export const EXPORT_CUSTOMERS = "export_customers";
export const EXPORT_VEHICLES = "export_vehicles";
export const EXPORT_TRANSACTIONS = "export_transactions";
export const EXPORT_ORDERS = "export_orders";
export const EXPORT_GENERAL_REPORT = "export_general_report";
export const EXPORT_VEHICLE_REVENUE = "export_vehicle_revenue";
export const EXPORT_BANK = "export_bank";
export const EXPORT_CASH = "export_cash";

// set

const state = {};

const getters = {};
const downloadExcel = (url, filename, params) => {
    return new Promise((resolve, reject) => {
        ApiService.download(url, params)
            .then((response) => {
                const data = response?.data !== undefined ? response.data : response;
                const blob = data instanceof Blob ? data : new Blob([data], {
                    type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
                });
                const objectUrl = window.URL.createObjectURL(blob);

                // Create a temporary link element
                const link = document.createElement("a");
                link.href = objectUrl;
                link.setAttribute("download", filename);

                // Simulate a click on the link to trigger the download
                document.body.appendChild(link);
                link.click();

                // Cleanup
                setTimeout(() => {
                    window.URL.revokeObjectURL(objectUrl);
                    if (link.parentNode) {
                        link.parentNode.removeChild(link);
                    }
                }, 1000);

                resolve(response);
            })
            .catch((error) => {
                reject(error?.response?.data || error?.response || error);
            });
    });
};

const actions = {
    [EXPORT_CUSTOMERS](context, params) {
        return downloadExcel(`/api/auth/export/customers`, 'customers.xlsx', params);
    },
    [EXPORT_VEHICLES](context, params) {
        return downloadExcel(`/api/auth/export/vehicles`, 'vehicles.xlsx', params);
    },
    [EXPORT_TRANSACTIONS](context, params) {
        return downloadExcel(`/api/auth/export/transactions`, `lich-su-thu-chi-${Date.now()}.xlsx`, params);
    },
    [EXPORT_ORDERS](context, params) {
        return downloadExcel(`/api/auth/export/orders`, 'orders.xlsx', params);
    },
    [EXPORT_GENERAL_REPORT](context, params) {
        return downloadExcel(`/api/auth/export/general_reports`, 'general_reports.zip', params);
    },
    [EXPORT_VEHICLE_REVENUE](context, params) {
        return downloadExcel(`/api/auth/export/vehicle_revenue`, 'vehicle_revenue.xlsx', params);
    },   
    [EXPORT_BANK](context, params) {
        return downloadExcel(`/api/auth/export/banks`, 'banks.xlsx', params);
    },
    [EXPORT_CASH](context, params) {
        return downloadExcel(`/api/auth/export/cash`, 'cash.xlsx', params);
    },
};

const mutations = {};

export default {
    state,
    actions,
    mutations,
    getters,
};
