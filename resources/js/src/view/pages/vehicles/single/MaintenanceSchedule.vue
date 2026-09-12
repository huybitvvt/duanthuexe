<template>
          <div class="example-preview table-responsive">
                               
                                <p v-if="vehicle && vehicle.maintenance_schedule.length ==0">Chưa có lịch hẹn</p>
                                <table class="table edit-history" v-if="vehicle && vehicle.maintenance_schedule.length >0 ">
                                    <thead>
                                        <tr>
                                           
                                         
                                            <th scope="col">Loại bảo dưỡng</th>
                                            <th scope="col">Lịch hẹn (thủ công)</th>
                                            <th scope="col">Lịch hẹn (tự động)</th>
                                            
                                            


                                        </tr>
                                    </thead>
                                    <tbody>

                                        <tr v-for="(item, j) in vehicle.maintenance_schedule" :key="j">
                                            <td>{{ item.maintenance_type.name }}</td>
                                            <td>{{ item.next_time_manual | formatDateTime }}</td>
                                            <td>{{ item.next_time_auto | formatDateTime }}</td>
                                          
                                         
                                             


                                        </tr>

                                    </tbody>
                                </table>

                            </div>

</template>

<script>

 

export default {
    name: "MaintenanceSchedule",
   
   
  
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
