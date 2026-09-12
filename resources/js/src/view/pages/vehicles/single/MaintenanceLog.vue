<template>
          <div class="example-preview table-responsive">
                               
                                <p v-if="vehicle && vehicle.maintenance_log.length ==0">Chưa có lịch sử bảo dưỡng</p>
                                <table class="table edit-history" v-if="vehicle && vehicle.maintenance_log.length >0 ">
                                    <thead>
                                        <tr>
                                           
                                            <th scope="col">Ngày bảo dưỡng</th>
                                            <th scope="col">Loại bảo dưỡng</th>
                                            <th scope="col">Ghi chú </th>
                                            


                                        </tr>
                                    </thead>
                                    <tbody>

                                        <tr v-for="(item, j) in vehicle.maintenance_log" :key="j">
                                            
                                            <td>{{ item.maintenance_at | formatDateTime }}</td>
                                            <td>{{ item.maintenance_type.name }}</td>
                                            <td>{{ item.note  || 'Không có ghi chú'}}</td>
                                             


                                        </tr>

                                    </tbody>
                                </table>

                            </div>

</template>

<script>

 

export default {
    name: "MaintenanceLog",
   
   
  
    props: {
        vehicle: {
            type: Object,
            default: () => {
                return {};
            }
        },
     

    },
 

     

}
</script>

<style scoped>
 
</style>
