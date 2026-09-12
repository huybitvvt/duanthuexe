import Vue from "vue";
import { mapGetters, mapState } from "vuex";
import { debounce } from "lodash-es";

export default {
    watch: {
        cloneQueryOld: {
          
            handler(newVal, oldVal) {
                const keywordChanged = "keyword" in newVal && newVal.keyword.trim() !== "" && newVal.keyword !== oldVal.keyword;
                const nameChanged = "name" in newVal && newVal.name.trim() !== "" && newVal.name !== oldVal.name;
            
                if (keywordChanged || nameChanged) {
                    this.debouncedSearch();
                } else {
                    this.search();
                }
            },
            deep: true,
        },
        "navigate.shouldClearQuery"(newVal, oldVal) {
            this.query = {};
        },
    },
    computed: {
        ...mapState({
            navigate: (state) => state.navigate,
        }),

        cloneQuery() {
            return Object.assign({}, this.query);
        },
    },
    created() {
        this.debouncedSearch = debounce(this.search, 5000);
    },
};
