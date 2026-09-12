<template>
  <ValidationObserver>
  <div class="list-vehicles">
    <div class="row">
      <div class="col-md-3">
        <div class="form-group">
          <label for="amount"> Tên chi phí</label>
          <ValidationProvider vid="name" name="Tên chi phí"   rules="required" v-slot="{ errors }">
          <el-input placeholder="Tên" id="name" type="text" v-model="fee.name" ></el-input>
          <error-message :errors="errors" field="name"></error-message>
        </ValidationProvider>
        </div>
      </div>
      <div class="col-md-4">
        <div class="form-group">
          <label for="amount"> Số tiền</label>
          <ValidationProvider vid="amount" name="Số tiền"   rules="required|min_value:1000"  v-slot="{ errors }">
          <money
          :disabled="order_status==='completed'"
            id="amount"
            v-model="fee.value"
            v-bind="money"
            class="form-control"
            rules="required"
          ></money>
          <error-message :errors="errors" field="amount"></error-message>
        </ValidationProvider>
        </div>
      </div>
      <div class="col-md-4">
        <div class="form-group">
          <label for="note">Ghi chú</label>
          <ValidationProvider vid="note" name="Ghi chú"   rules="required" v-slot="{ errors }">
          <el-input
            placeholder="Ghi chú"
            id="note"
            type="textarea"
            v-model="fee.note"
            rules="required"
          ></el-input>
          <error-message :errors="errors" field="note"></error-message>
        </ValidationProvider>
        </div>
      </div>
      <div class="col-md-1" style="margin: auto">
        <span title="Xóa" @click="deleteFee">
          <i class="fas fa-trash text-danger cursor-pointer" title="Xóa"></i>
        </span>
      </div>
    </div>
  </div>
</ValidationObserver>
</template>

<script>
import ErrorMessage from "../../common/ErrorMessage";
import { Money } from "v-money";

export default {
  name: "Fee",
  props: {
    index: {
      type: Number,
      default: () => {
        return 0;
      },
    },
    fee: {
      type: Object,
      default: () => {
        return {};
      },
    },
    order_item_id: {
      type: Number,
      default: () => {
        return 0;
      },
    },
    order_id: {
      type: Number,
      default: () => {
        return 0;
      },
    },
    order_status: {
      type: String,
      default: () => {
        return '';
      },
    },
  },
  components: {
    ErrorMessage,
    Money,
  },
  data() {
    return {
      /* v-money */
      money: {
        decimal: ",",
        thousands: ",",
        prefix: "",
        suffix: " VNĐ",
        precision: 0,
        masked: false,
      },
    };
  },

  methods: {
  
    deleteFee() {
      this.$emit("deleteFee", this.index);
    },
  },
  watch: {
	fee: {
		handler(newVal, oldVal) {
			this.$emit("feeChanged");
		},
		deep: true
	}
  }
};
</script>

<style scoped></style>
