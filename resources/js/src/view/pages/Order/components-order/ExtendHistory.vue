<template>
    <div class="w-100">
        <table class="table text-center table-vertical-center table-hover table-bordered">
            <thead>
            <tr>
                <th scope="col">Lần gia hạn</th>
                <th scope="col">Ngày bắt đầu</th>
                <th scope="col">Ngày kết thúc</th>
                <th scope="col">Số tiền</th>
                <th scope="col">Trạng thái</th>
            </tr>
            </thead>
            <tbody v-if="extendData.length">
            <tr v-for="(item, index) in extendData" :key="index">
                <th scope="row">{{ item.id }}</th>
                <td>{{ item.created_at }}</td>
                <td>{{ item.note }}</td>
                <td>
                    <div>
                        Đặt cọc: <span class="text-danger">{{ item.pid | formatPrice }}</span>
                    </div>
                    <div>
                        Tổng giá trị hợp đồng: <span class="text-danger">{{
                            item.total | formatPrice
                        }}</span>
                    </div>
                </td>
                <td></td>
                <td></td>
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
    name: "ExtendHistory",
    props: {
        extendData: {
            type: Array,
            default: () => []
        }
    }
}
</script>

<style scoped>

</style>
