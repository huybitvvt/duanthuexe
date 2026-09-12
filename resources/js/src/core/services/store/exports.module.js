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
const downloadExcel = async (url, filename,params)=>{
    return new Promise((resolve, reject) => {
        ApiService.download(url,params)
            .then(({ data }) => {
                // Create a blob object from the response data
                const blob = new Blob([data], {
                    type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
                });
                const url = window.URL.createObjectURL(blob);

                // Create a temporary link element
                const link = document.createElement("a");
                link.href = url;
                link.setAttribute("download", filename);

                // Simulate a click on the link to trigger the download
                document.body.appendChild(link);
                link.click();

                // Cleanup
                window.URL.revokeObjectURL(url);
                document.body.removeChild(link);

                resolve(); // Resolve the promise
            })
            .catch(({ response }) => {
                reject(response); // Reject the promise with error response
            });
    });
};
const actions = {
    async   [EXPORT_CUSTOMERS](context, params) {
        try {
            const res = await downloadExcel(`/api/auth/export/customers`, 'customers.xlsx',params);
            return res;  
        } catch (error) {
            return error;  
        }
    },
    async  [EXPORT_VEHICLES](context, params) {
        try {
            const res = await downloadExcel(`/api/auth/export/vehicles`, 'vehicles.xlsx',params);
            return res;  
        } catch (error) {
            return error;  
        }
    },
    async  [EXPORT_TRANSACTIONS](context, params) {
        try {
            const res = await downloadExcel(`/api/auth/export/transactions`, 'transactions.xlsx',params);
            return res;  
        } catch (error) {
            return error;  
        }
    },
    async  [EXPORT_ORDERS](context, params) {
        try {
            const res = await downloadExcel(`/api/auth/export/orders`, 'orders.xlsx',params);
            return res;  
        } catch (error) {
            return error;  
        }
    },
    async  [EXPORT_GENERAL_REPORT](context, params) {
        try {
            const res = await downloadExcel(`/api/auth/export/general_reports`, 'general_reports.zip',params);
            return res;  
        } catch (error) {
            return error;  
        }
    },
    async  [EXPORT_VEHICLE_REVENUE](context, params) {
        try {
            const res = await downloadExcel(`/api/auth/export/vehicle_revenue`, 'vehicle_revenue.xlsx',params);
            return res;  
        } catch (error) {
            return error;  
        }
    },   
     async  [EXPORT_BANK](context, params) {
        try {
            const res = await downloadExcel(`/api/auth/export/banks`, 'banks.xlsx',params);
            return res;  
        } catch (error) {
            return error;  
        }
    },
    async  [EXPORT_CASH](context, params) {
        try {
            const res = await downloadExcel(`/api/auth/export/cash`, 'cash.xlsx',params);
            return res;  
        } catch (error) {
            return error;  
        }
    },

    
};

const mutations = {};

export default {
    state,
    actions,
    mutations,
    getters,
};
