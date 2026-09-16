import ApiService from "@/core/services/api.service";

export const LEASE_GET_CONTRACTS = "lease_get_contracts";
export const LEASE_GET_STATS = "lease_get_stats";
export const LEASE_GET_SHOW = "lease_get_show";
export const LEASE_CREATE_CONTRACT = "lease_create_contract";
export const LEASE_ALLOCATE_PAYMENT = "lease_allocate_payment";
export const LEASE_ADD_NOTE = "lease_add_note";
export const LEASE_EXPORT = "lease_export";

const normalizeContract = c => {
    if (!c) return c;
    c.remaining_debt = c.outstanding_balance;
    c.latest_debt_note = c.latest_note ? { ...c.latest_note, notes: c.latest_note.content, promised_date: c.latest_note.appointment_date } : null;
    (c.installments || []).forEach(i => {
        i.expected_amount = i.amount_due;
        i.paid_amount = i.amount_paid;
        i.adjustment_amount = Number(i.adjustment_amount || 0);
        i.remaining_amount = i.remaining_amount == null
            ? Math.max(0, Number(i.amount_due) - Number(i.amount_paid) - i.adjustment_amount)
            : Number(i.remaining_amount);
    });
    (c.debt_notes || []).forEach(n => { n.notes = n.note_content; n.promised_date = n.appointment_date; });
    return c;
};

const state = {
    contractList: [],
    stats: null,
};

const getters = {
    leaseContracts: (state) => state.contractList,
    leaseStats: (state) => state.stats,
};

const mutations = {
    SET_LEASE_CONTRACTS(state, list) {
        state.contractList = list;
    },
    SET_LEASE_STATS(state, stats) {
        state.stats = stats;
    },
};

const actions = {
    [LEASE_GET_CONTRACTS](context, params) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/lease-contracts", params || {})
                .then(({ data }) => {
                    (data?.data?.data || []).forEach(normalizeContract);
                    context.commit("SET_LEASE_CONTRACTS", data?.data?.data || data?.data || []);
                    resolve(data);
                })
                .catch((err) => {
                    reject(err?.response || err);
                });
        });
    },

    [LEASE_GET_STATS](context, params) {
        return new Promise((resolve, reject) => {
            ApiService.query("/api/auth/lease-contracts/stats", params || {})
                .then(({ data }) => {
                    const s = data.data;
                    if (s) {
                        s.total_remaining_debt = s.total_outstanding;
                        s.aging_buckets = s.buckets?.counts || {};
                        s.overdue_contracts = Object.entries(s.aging_buckets).filter(([k]) => k !== 'current').reduce((n, [, v]) => n + Number(v), 0);
                    }
                    context.commit("SET_LEASE_STATS", data?.data || null);
                    resolve(data);
                })
                .catch((err) => {
                    reject(err?.response || err);
                });
        });
    },

    [LEASE_GET_SHOW](context, id) {
        return new Promise((resolve, reject) => {
            ApiService.get(`/api/auth/lease-contracts/${id}`)
                .then(({ data }) => {
                    data.data = normalizeContract(data.data);
                    resolve(data);
                })
                .catch((err) => {
                    reject(err?.response || err);
                });
        });
    },

    [LEASE_CREATE_CONTRACT](context, payload) {
        return new Promise((resolve, reject) => {
            ApiService.post("/api/auth/lease-contracts", payload)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch((err) => {
                    reject(err?.response || err);
                });
        });
    },

    [LEASE_ALLOCATE_PAYMENT](context, { contractId, payload }) {
        return new Promise((resolve, reject) => {
            ApiService.post(`/api/auth/lease-contracts/${contractId}/payments`, payload)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch((err) => {
                    reject(err?.response || err);
                });
        });
    },

    [LEASE_ADD_NOTE](context, { contractId, payload }) {
        return new Promise((resolve, reject) => {
            ApiService.post(`/api/auth/lease-contracts/${contractId}/notes`, payload)
                .then(({ data }) => {
                    resolve(data);
                })
                .catch((err) => {
                    reject(err?.response || err);
                });
        });
    },

    [LEASE_EXPORT](context, params) {
        return new Promise((resolve, reject) => {
            ApiService.download("/api/auth/lease-contracts/export", params || {})
                .then((response) => {
                    const blob = new Blob([response.data], {
                        type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
                    });
                    const link = document.createElement("a");
                    link.href = window.URL.createObjectURL(blob);
                    link.download = `cong-no-thue-so-huu-${Date.now()}.xlsx`;
                    link.click();
                    setTimeout(() => window.URL.revokeObjectURL(link.href), 1000);
                    resolve(response);
                })
                .catch((err) => {
                    reject(err?.response || err);
                });
        });
    },
};

export default {
    state,
    actions,
    mutations,
    getters,
};
