export default {
    data() {
        return {}
    },
    methods: {

        formatPrice(value, type = 'đ') {
            if (value == '' || value == null) {
                return 0 + type;
            }
            let val = (value / 1).toFixed(0).replace('.', ',')
            return val.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".") + type
        },

        pushParamsUrl(params){
            history.pushState(
                {},
                null,
                this.$route.path +
                '?' +
                Object.keys(params).map(key => {
                    return (encodeURIComponent(key) + '=' + encodeURIComponent(params[key]))
                })
                    .join('&')
            )
        },

        pullParamsFromUrl(formQuery){
            for (const formQueryKey in formQuery) {
                if (Array.isArray(formQuery[formQueryKey])){
                    formQuery[formQueryKey] = this.$route.query[formQueryKey] ? this.$route.query[formQueryKey].split(',').map(Number): [];
                }else if (Number(this.$route.query[formQueryKey]) > 0){
                    formQuery[formQueryKey] = Number(this.$route.query[formQueryKey]);
                }else if (formQueryKey === 'page') {
                    formQuery[formQueryKey] = this.$route.query[formQueryKey] ? Number(this.$route.query[formQueryKey]) : 1;
                }else {
                    formQuery[formQueryKey] = this.$route.query[formQueryKey] ? this.$route.query[formQueryKey] : '';
                }1
            }
        },

        noticeMessage(type, title, message) {
            this.$notify({
                type: type,
                title: title,
                message: message
            });
        },

        getIndexPaginate(current_page, index, limit) {
            return (current_page - 1) * limit + index + 1
        }
    },
};
