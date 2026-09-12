<template>
    <div class="w-100">
        <table class="table text-center table-vertical-center table-hover table-bordered">
            <thead>
            <tr>
                <th scope="col">Người nạp</th>
                <th scope="col">Ngày nạp</th>
                <th scope="col">Số tiền</th>
            </tr>
            </thead>
            <tbody v-if="data.length">
            <tr v-for="(item, index) in data" :key="index">
                <th scope="row">{{ item.user ? item.user.name : '' }}</th>
                <td>{{ item.created_at | formatDateTime }}</td>
                <td>{{ item.price | formatPrice }}</td>
            </tr>
            </tbody>
            <tbody v-else>
            <tr>
                <td scope="row" colspan="5">Hợp đồng không gia hạn</td>
            </tr>
            </tbody>
        </table>
    </div>
</template>

<script>
export default {
    name: "AddOnHistory",
    props: {
        data: {
            type: Array,
            default: () => []
        }
    }
}
</script>

<style scoped>

</style>
