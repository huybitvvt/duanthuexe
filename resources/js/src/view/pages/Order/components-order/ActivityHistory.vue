<template>
    <div class="w-100">
        <table class="table text-center table-vertical-center table-hover table-bordered">
            <thead>
            <tr>
                <th scope="col">Hành động</th>
                <th scope="col">Người thực hiện</th>
                <th scope="col">Thời gian</th>
                <th scope="col">Nội dung</th>
                <th v-if="skin!='order-show'" scope="col">Xem chi tiết</th>
            </tr>
            </thead>
            <tbody v-if="activityLogs.length">
            <tr v-for="(item, index) in activityLogs" :key="index">
                <th scope="row">Cập nhật</th>
                <td>{{ item.user && item.user.name }}</td>
                <td>{{ item.created_at }}</td>
                <td>{{ item.content }}</td>
                <td v-if="skin!='order-show'">Xem chi tiết</td>
            </tr>
            </tbody>
            <tbody v-else>
            <tr>
                <td scope="row" colspan="5">Không tìm thấy lịch sử cập nhật</td>
            </tr>
            </tbody>
        </table>
    </div>
</template>

<script>
export default {
    name: "ActivityHistory",
    props: {
        activityLogs: {
            type: Array,
            default: () => []
        },
        skin:{
            type: String,
            default: () => ''
        }
    }
}
</script>

<style scoped>

</style>
