<template>
  <b-modal
    id="modal-vehicle-exchange"
    v-model="visible"
    title="Đổi xe gặp sự cố / không phù hợp cho hợp đồng"
    size="lg"
    no-close-on-backdrop
    @hidden="resetForm"
  >
    <div v-loading="loading">
      <div class="alert alert-custom alert-light-warning mb-4 p-3" role="alert">
        <div class="alert-text small">
          Quy trình đổi xe: Xe cũ gặp sự cố sẽ được thu hồi và chuyển sang trạng thái <strong>Sửa chữa / Bảo dưỡng</strong>. Xe thay thế sẽ được gán vào hợp đồng, hợp đồng gốc và tiền cọc được bảo toàn nguyên vẹn kèm bản ghi <strong>Phụ lục điều chỉnh hợp đồng</strong>.
        </div>
      </div>

      <div class="row">
        <!-- Đơn hàng / Hợp đồng -->
        <div class="col-md-6 form-group">
          <label class="font-weight-bold">ID Đơn hàng / Hợp đồng <span class="text-danger">*</span></label>
          <el-input
            v-model="form.order_id"
            placeholder="Nhập ID đơn (VD: 125)"
            type="number"
          />
        </div>

        <!-- Lý do đổi xe -->
        <div class="col-md-6 form-group">
          <label class="font-weight-bold">Lý do đổi xe <span class="text-danger">*</span></label>
          <el-input
            v-model="form.reason"
            placeholder="VD: Hỏng bình ắc quy, thủng lốp, xe chạy yếu..."
          />
        </div>

        <!-- Xe cũ cần thu hồi -->
        <div class="col-md-6 form-group">
          <label class="font-weight-bold">ID Xe đang thuê gặp sự cố (Xe cũ) <span class="text-danger">*</span></label>
          <el-input
            v-model="form.old_vehicle_id"
            placeholder="Nhập ID xe cũ"
            type="number"
          />
          <small class="form-text text-muted" v-if="oldVehicleInfo">
            Xe: <strong>{{ oldVehicleInfo.license }} - {{ oldVehicleInfo.name }}</strong>
          </small>
        </div>

        <!-- Xe mới thay thế -->
        <div class="col-md-6 form-group">
          <label class="font-weight-bold">Xe thay thế (Xe mới sẵn sàng) <span class="text-danger">*</span></label>
          <el-select
            v-model="form.new_vehicle_id"
            filterable
            placeholder="Chọn xe thay thế"
            class="w-100"
            :loading="loadingReadyVehicles"
          >
            <el-option
              v-for="v in readyVehicles"
              :key="v.id"
              :label="`${v.license} - ${v.name} (${v.type || 'N/A'})`"
              :value="v.id"
            />
          </el-select>
        </div>

        <!-- ODO xe cũ & ODO xe mới -->
        <div class="col-md-6 form-group">
          <label class="font-weight-bold">Số ODO xe cũ khi thu hồi (km)</label>
          <el-input
            v-model="form.old_vehicle_odometer"
            placeholder="VD: 8200"
            type="number"
          />
        </div>

        <div class="col-md-6 form-group">
          <label class="font-weight-bold">Số ODO xe mới khi giao (km)</label>
          <el-input
            v-model="form.new_vehicle_odometer"
            placeholder="VD: 500"
            type="number"
          />
        </div>

        <!-- Tiền chênh lệch nếu có -->
        <div class="col-md-6 form-group">
          <label class="font-weight-bold">Chênh lệch giá thuê phát sinh (VNĐ)</label>
          <el-input
            v-model="form.price_difference"
            placeholder="0"
            type="number"
          />
          <small class="form-text text-muted">Dương: Thu thêm của khách. Âm: Hoàn tiền thừa cho khách. 0: Cùng hạng giá.</small>
        </div>

        <!-- Phương thức thanh toán khi có chênh lệch -->
        <div class="col-md-6 form-group" v-if="Number(form.price_difference) !== 0">
          <label class="font-weight-bold">Phương thức bù trừ chênh lệch <span class="text-danger">*</span></label>
          <el-select v-model="form.payment_method" placeholder="Chọn hình thức" class="w-100">
            <el-option label="Tiền mặt (Quỹ cơ sở)" :value="1" />
            <el-option label="Chuyển khoản (Ngân hàng)" :value="2" />
          </el-select>
        </div>

        <!-- Tài khoản ngân hàng khi chuyển khoản -->
        <div class="col-md-6 form-group" v-if="Number(form.price_difference) !== 0 && form.payment_method === 2">
          <label class="font-weight-bold">Tài khoản ngân hàng <span class="text-danger">*</span></label>
          <el-select v-model="form.bank_id" placeholder="Chọn tài khoản ngân hàng" class="w-100" :loading="loadingBanks">
            <el-option
              v-for="b in banks"
              :key="b.id"
              :label="bankLabel(b)"
              :value="b.id"
            />
          </el-select>
        </div>

        <!-- Ghi chú tình trạng xe -->
        <div class="col-md-12 form-group">
          <label class="font-weight-bold">Ghi chú biên bản & tình trạng lỗi</label>
          <el-input
            type="textarea"
            :rows="2"
            v-model="form.condition_notes"
            placeholder="Mô tả cụ thể triệu chứng hỏng của xe cũ..."
          />
        </div>
      </div>
    </div>

    <template #modal-footer="{ cancel }">
      <b-button variant="secondary" @click="cancel">Đóng</b-button>
      <b-button variant="warning" :disabled="loading" @click="handleSubmit">
        Xác nhận đổi xe & Lập phụ lục
      </b-button>
    </template>
  </b-modal>
</template>

<script>
import {
  WAREHOUSE_EXCHANGE_VEHICLE,
  WAREHOUSE_GET_VEHICLES,
} from "@/core/services/store/warehouse.module";
import { BANK_INDEX } from "@/core/services/store/banks.module";
import Swal from "sweetalert2";

export default {
  name: "ModalVehicleExchange",
  props: {
    defaultStoreId: {
      type: [Number, String],
      default: null,
    },
  },
  data() {
    return {
      visible: false,
      loading: false,
      loadingReadyVehicles: false,
      loadingBanks: false,
      oldVehicleInfo: null,
      readyVehicles: [],
      banks: [],
      form: {
        order_id: null,
        old_vehicle_id: null,
        new_vehicle_id: null,
        reason: "Đổi xe do sự cố kỹ thuật",
        condition_notes: "",
        price_difference: 0,
        payment_method: 1,
        bank_id: null,
        exchange_store_id: null,
        old_vehicle_odometer: null,
        new_vehicle_odometer: null,
      },
    };
  },
  methods: {
    open(orderId = null, oldVehicle = null, storeId = null) {
      this.visible = true;
      this.form.order_id = orderId || null;
      if (oldVehicle) {
        this.form.old_vehicle_id = oldVehicle.id;
        this.oldVehicleInfo = oldVehicle;
      }
      const targetStore = storeId || this.defaultStoreId;
      this.form.exchange_store_id = targetStore ? Number(targetStore) : null;
      this.fetchReadyVehicles(targetStore);
      this.fetchBanks(targetStore);
    },
    fetchReadyVehicles(storeId) {
      if (!storeId) return;
      this.loadingReadyVehicles = true;
      this.$store
        .dispatch(WAREHOUSE_GET_VEHICLES, {
          storeId: storeId,
          params: { status: "ready", location_mode: "present", limit: 100 },
        })
        .then((res) => {
          this.readyVehicles = res?.data?.vehicles?.data || res?.data?.vehicles || [];
        })
        .catch(() => {
          this.readyVehicles = [];
        })
        .finally(() => {
          this.loadingReadyVehicles = false;
        });
    },
    fetchBanks(storeId) {
      this.loadingBanks = true;
      const params = storeId ? { store_id: storeId } : {};
      this.$store
        .dispatch(BANK_INDEX, params)
        .then((res) => {
          const payload = res?.data ?? res;
          const rows = payload?.banks?.data || payload?.banks || payload?.data || payload;
          this.banks = Array.isArray(rows)
            ? rows
                .filter((bank) => bank && bank.id && (bank.account_number || bank.account_no || bank.accountNo))
                .map((bank) => ({
                  ...bank,
                  bank_name: bank.bank_name || bank.bank || bank.name || "Ngân hàng chưa cập nhật",
                  account_number: bank.account_number || bank.account_no || bank.accountNo,
                  owner_name: bank.owner_name || bank.account_name || bank.account_holder || bank.owner || "Chủ tài khoản chưa cập nhật",
                }))
            : [];
        })
        .catch(() => {
          this.banks = [];
        })
        .finally(() => {
          this.loadingBanks = false;
        });
    },
    bankLabel(bank) {
      const bankName = bank.bank_name || bank.bank || bank.name || "Ngân hàng chưa cập nhật";
      const accountNumber = bank.account_number || bank.account_no || bank.accountNo || "Chưa có số TK";
      const ownerName = bank.owner_name || bank.account_name || bank.account_holder || bank.owner || "Chủ tài khoản chưa cập nhật";
      return `${bankName} - ${accountNumber} (${ownerName})`;
    },
    handleSubmit() {
      if (!this.form.order_id) {
        Swal.fire("Lỗi", "Vui lòng nhập ID đơn hàng.", "warning");
        return;
      }
      if (!this.form.old_vehicle_id) {
        Swal.fire("Lỗi", "Vui lòng nhập ID xe cũ đang gặp sự cố.", "warning");
        return;
      }
      if (!this.form.new_vehicle_id) {
        Swal.fire("Lỗi", "Vui lòng chọn xe thay thế sẵn sàng.", "warning");
        return;
      }
      if (!this.form.reason) {
        Swal.fire("Lỗi", "Vui lòng nhập lý do đổi xe.", "warning");
        return;
      }

      const priceDiff = Number(this.form.price_difference || 0);
      const payload = {
        order_id: Number(this.form.order_id),
        old_vehicle_id: Number(this.form.old_vehicle_id),
        new_vehicle_id: Number(this.form.new_vehicle_id),
        reason: this.form.reason,
        condition_notes: this.form.condition_notes,
        price_difference: priceDiff,
        exchange_store_id: this.form.exchange_store_id,
        old_vehicle_odometer: this.form.old_vehicle_odometer ? Number(this.form.old_vehicle_odometer) : null,
        new_vehicle_odometer: this.form.new_vehicle_odometer ? Number(this.form.new_vehicle_odometer) : null,
      };

      if (priceDiff !== 0) {
        if (!this.form.payment_method) {
          Swal.fire("Lỗi", "Vui lòng chọn phương thức bù trừ chênh lệch giá.", "warning");
          return;
        }
        payload.payment_method = Number(this.form.payment_method);
        if (payload.payment_method === 2) {
          if (!this.form.bank_id) {
            Swal.fire("Lỗi", "Vui lòng chọn tài khoản ngân hàng bù trừ.", "warning");
            return;
          }
          payload.bank_id = Number(this.form.bank_id);
        }
      }

      this.loading = true;
      this.$store
        .dispatch(WAREHOUSE_EXCHANGE_VEHICLE, payload)
        .then((res) => {
          Swal.fire(
            "Thành công",
            res?.message || "Đã đổi xe thành công. Xe cũ đã chuyển sang trạng thái sửa chữa và xe mới đã được gán vào hợp đồng.",
            "success"
          );
          this.visible = false;
          this.$emit("success");
        })
        .catch((err) => {
          const msg = err?.data?.message || err?.message || "Đã xảy ra lỗi khi thực hiện đổi xe.";
          Swal.fire("Lỗi", msg, "error");
        })
        .finally(() => {
          this.loading = false;
        });
    },
    resetForm() {
      this.form = {
        order_id: null,
        old_vehicle_id: null,
        new_vehicle_id: null,
        reason: "Đổi xe do sự cố kỹ thuật",
        condition_notes: "",
        price_difference: 0,
        payment_method: 1,
        bank_id: null,
        exchange_store_id: null,
        old_vehicle_odometer: null,
        new_vehicle_odometer: null,
      };
      this.oldVehicleInfo = null;
      this.readyVehicles = [];
      this.banks = [];
    },
  },
};
</script>
