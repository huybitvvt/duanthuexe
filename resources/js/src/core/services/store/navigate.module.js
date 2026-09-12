export default {
    namespaced: true,
    state: {
        shouldClearQuery: false,
    },
    getters: {},
    mutations: {
        setShouldClearQuery(state, value) {
            state.shouldClearQuery = value;
        },
    },
    actions: {
        setShouldClearQuery({ commit }, value) {
            commit("setShouldClearQuery", value);
        },
    },
};
