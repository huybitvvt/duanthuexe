<template>
  <div class="store-duty-schedule-page">
    <div class="card card-custom gutter-b">
      <div class="card-header border-0 pt-5">
        <div class="card-title">
          <h3 class="card-label font-weight-bolder text-dark">
            Lịch trực cửa hàng & Phân hệ HCNS
          </h3>
        </div>
        <div class="card-toolbar">
          <ul class="nav nav-pills nav-pills-sm font-weight-bold">
            <li class="nav-item">
              <button
                type="button"
                class="nav-link btn btn-sm font-weight-bold px-4 py-2 mr-2"
                :class="{ 'btn-primary active text-white': activeTab === 'schedule', 'btn-light text-dark': activeTab !== 'schedule' }"
                @click="selectTab('schedule')"
              >
                Lịch trực cơ sở theo ngày
              </button>
            </li>
            <li class="nav-item">
              <button
                type="button"
                class="nav-link btn btn-sm font-weight-bold px-4 py-2 mr-2"
                :class="{ 'btn-primary active text-white': activeTab === 'staff', 'btn-light text-dark': activeTab !== 'staff' }"
                @click="selectTab('staff')"
              >
                Danh bạ hồ sơ nhân sự (HCNS)
              </button>
            </li>
            <li class="nav-item">
              <button
                type="button"
                class="nav-link btn btn-sm font-weight-bold px-4 py-2 mr-2"
                :class="{ 'btn-primary active text-white': activeTab === 'organization', 'btn-light text-dark': activeTab !== 'organization' }"
                @click="selectTab('organization')"
              >
                Sơ đồ tổ chức
              </button>
            </li>
            <li class="nav-item">
              <button
                type="button"
                class="nav-link btn btn-sm font-weight-bold px-4 py-2"
                :class="{ 'btn-primary active text-white': activeTab === 'attendance', 'btn-light text-dark': activeTab !== 'attendance' }"
                @click="selectTab('attendance')"
              >
                Chấm công
              </button>
            </li>
          </ul>
        </div>
      </div>

      <div class="card-body pt-2">
        <!-- ==================== TAB 1: LỊCH TRỰC CƠ SỞ ==================== -->
        <div v-show="activeTab === 'schedule'">
          <!-- Bộ lọc ca trực -->
          <div class="row align-items-center mb-6 bg-light rounded p-4">
            <div class="col-md-3 mb-2 mb-md-0">
              <label class="font-weight-bold text-muted font-size-sm">NGÀY TRỰC:</label>
              <el-date-picker
                v-model="selectedDate"
                type="date"
                format="yyyy-MM-dd"
                value-format="yyyy-MM-dd"
                placeholder="Chọn ngày"
                class="w-100"
                @change="fetchDutySchedules"
              />
            </div>
            <div class="col-md-3 mb-2 mb-md-0">
              <label class="font-weight-bold text-muted font-size-sm">CƠ SỞ / CHI NHÁNH:</label>
              <el-select
                v-model="selectedStoreId"
                placeholder="Tất cả cơ sở"
                class="w-100"
                clearable
                filterable
                @change="fetchDutySchedules"
              >
                <el-option
                  v-for="s in stores"
                  :key="s.id"
                  :label="s.store_name"
                  :value="s.id"
                />
              </el-select>
            </div>
            <div class="col-md-6 text-right pt-md-4">
              <button
                type="button"
                class="btn btn-outline-secondary font-weight-bold mr-2"
                @click="fetchDutySchedules"
                :disabled="loadingSchedule"
              >
                {{ loadingSchedule ? 'Đang tải...' : 'Làm mới' }}
              </button>
              <button
                type="button"
                class="btn btn-success font-weight-bold"
                @click="openAddDutyModal"
              >
                Thêm ca trực
              </button>
            </div>
          </div>

          <!-- Danh sách ca trực -->
          <div class="table-responsive">
            <table class="table table-bordered table-hover">
              <thead class="thead-light">
                <tr>
                  <th style="width: 60px;">STT</th>
                  <th>Cơ sở / Cửa hàng</th>
                  <th>Ca trực</th>
                  <th>Nhân viên trực</th>
                  <th>Số điện thoại liên hệ</th>
                  <th>Vai trò trong ca</th>
                  <th>Ghi chú ca trực</th>
                  <th style="width: 120px;" class="text-center">Thao tác</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, idx) in dutyList" :key="item.id">
                  <td>{{ idx + 1 }}</td>
                  <td class="font-weight-bold text-dark">
                    {{ item.store ? item.store.store_name : ('Cơ sở #' + item.store_id) }}
                  </td>
                  <td>
                    <span class="badge badge-info px-2 py-1 font-weight-bold">
                      {{ item.shift_name || 'Cả ngày' }}
                    </span>
                  </td>
                  <td class="font-weight-bolder text-primary">
                    {{ item.staff_name }}
                  </td>
                  <td>
                    <a
                      v-if="item.staff_phone"
                      :href="'tel:' + item.staff_phone"
                      class="font-weight-bold text-success"
                      title="Bấm để gọi ngay"
                    >
                      {{ item.staff_phone }} (Bấm gọi)
                    </a>
                    <span v-else class="text-muted">Chưa có SĐT</span>
                  </td>
                  <td>{{ item.role_in_shift || 'Nhân viên trực' }}</td>
                  <td>{{ item.notes || '-' }}</td>
                  <td class="text-center">
                    <button
                      type="button"
                      class="btn btn-sm btn-outline-danger font-weight-bold"
                      @click="handleDeleteDuty(item)"
                    >
                      Xóa
                    </button>
                  </td>
                </tr>
                <tr v-if="!dutyList.length">
                  <td colspan="8" class="text-center text-muted py-5">
                    {{ loadingSchedule ? 'Đang tải dữ liệu...' : 'Không có ca trực nào được phân công trong ngày đã chọn.' }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- ==================== TAB 2: HỒ SƠ NHÂN SỰ HCNS ==================== -->
        <div v-show="activeTab === 'staff'">
          <!-- Bộ lọc nhân sự -->
          <div class="row align-items-center mb-6 bg-light rounded p-4">
            <div class="col-md-4 mb-2 mb-md-0">
              <label class="font-weight-bold text-muted font-size-sm">TÌM KIẾM NHÂN SỰ:</label>
              <search-suggest endpoint="/api/auth/hr/staff" :params="staffFilter" query-key="keyword" fields="full_name,staff_code,phone,email" @select="fetchStaffList" @submit="fetchStaffList"
                v-model="staffFilter.search"
                placeholder="Tên, mã NV, SĐT, email..."
                clearable
                class="w-100"
              />
            </div>
            <div class="col-md-3 mb-2 mb-md-0">
              <label class="font-weight-bold text-muted font-size-sm">CƠ SỞ TRỰC THUỘC:</label>
              <el-select
                v-model="staffFilter.store_id"
                placeholder="Tất cả cơ sở"
                class="w-100"
                clearable
                filterable
                @change="fetchStaffList"
              >
                <el-option
                  v-for="s in stores"
                  :key="s.id"
                  :label="s.store_name"
                  :value="s.id"
                />
              </el-select>
            </div>
            <div class="col-md-5 text-right pt-md-4">
              <button
                type="button"
                class="btn btn-outline-primary font-weight-bold mr-2"
                @click="fetchStaffList"
                :disabled="loadingStaff"
              >
                Tìm kiếm
              </button>
              <button
                type="button"
                class="btn btn-success font-weight-bold"
                @click="openAddStaffModal"
              >
                Thêm hồ sơ nhân viên
              </button>
            </div>
          </div>

          <!-- Bảng danh sách nhân sự -->
          <div class="table-responsive">
            <table class="table table-bordered table-hover">
              <thead class="thead-light">
                <tr>
                  <th style="width: 60px;">STT</th>
                  <th>Mã NV</th>
                  <th>Họ và tên</th>
                  <th>Chức danh / Vị trí</th>
                  <th>Cơ sở công tác</th>
                  <th>Số điện thoại</th>
                  <th>Email</th>
                  <th>Trạng thái</th>
                  <th style="width: 100px;" class="text-center">Thao tác</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(staff, idx) in staffList" :key="staff.id">
                  <td>{{ idx + 1 }}</td>
                  <td class="font-weight-bold">{{ staff.staff_code || ('NV' + staff.id) }}</td>
                  <td class="font-weight-bolder text-dark">{{ staff.full_name }}</td>
                  <td>{{ staff.position || '-' }}</td>
                  <td>{{ staff.store ? staff.store.store_name : (staff.store_id ? 'Cơ sở #' + staff.store_id : 'Văn phòng chính') }}</td>
                  <td>
                    <a
                      v-if="staff.phone"
                      :href="'tel:' + staff.phone"
                      class="font-weight-bold text-success"
                      title="Bấm để gọi ngay"
                    >
                      {{ staff.phone }} (Bấm gọi)
                    </a>
                    <span v-else class="text-muted">-</span>
                  </td>
                  <td>{{ staff.email || staff.personal_email || '-' }}</td>
                  <td>
                    <span
                      class="badge px-2 py-1 font-weight-bold"
                      :class="{
                        'badge-success': staff.status === 'active' || !staff.status,
                        'badge-warning': staff.status === 'on_leave',
                        'badge-danger': staff.status === 'resigned'
                      }"
                    >
                      {{ staff.status === 'resigned' ? 'Đã nghỉ việc' : (staff.status === 'on_leave' ? 'Nghỉ phép' : 'Đang làm việc') }}
                    </span>
                  </td>
                  <td class="text-center">
                    <button
                      type="button"
                      class="btn btn-sm btn-outline-primary font-weight-bold"
                      @click="openEditStaffModal(staff)"
                    >
                      Sửa
                    </button>
                  </td>
                </tr>
                <tr v-if="!staffList.length">
                  <td colspan="9" class="text-center text-muted py-5">
                    {{ loadingStaff ? 'Đang tải dữ liệu...' : 'Không tìm thấy hồ sơ nhân sự nào.' }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- ==================== TAB 3: SƠ ĐỒ TỔ CHỨC ==================== -->
        <div v-show="activeTab === 'organization'">
          <div class="row align-items-center mb-6 bg-light rounded p-4">
            <div class="col-md-5 mb-2 mb-md-0">
              <label class="font-weight-bold text-muted font-size-sm">CƠ SỞ / CHI NHÁNH:</label>
              <el-select
                v-model="orgStoreId"
                placeholder="Toàn hệ thống"
                class="w-100"
                clearable
                filterable
                @change="fetchOrganizationChart"
              >
                <el-option
                  v-for="s in stores"
                  :key="s.id"
                  :label="s.store_name"
                  :value="s.id"
                />
              </el-select>
            </div>
            <div class="col-md-7 text-right pt-md-4">
              <button
                type="button"
                class="btn btn-outline-primary font-weight-bold"
                :disabled="loadingOrganization"
                @click="fetchOrganizationChart"
              >
                {{ loadingOrganization ? 'Đang tải...' : 'Làm mới sơ đồ' }}
              </button>
            </div>
          </div>

          <div v-if="loadingOrganization" class="text-center text-muted py-8">
            Đang tải sơ đồ tổ chức...
          </div>
          <div v-else-if="!organizationUnits.length" class="text-center text-muted py-8">
            Chưa có dữ liệu phòng ban hoặc nhân sự đang làm việc.
          </div>
          <div v-else class="organization-grid">
            <section v-for="unit in organizationUnits" :key="unit.code || unit.id" class="organization-unit">
              <header class="organization-unit-header">
                <div>
                  <div class="organization-unit-code">{{ unit.code || 'PHÒNG BAN' }}</div>
                  <h4>{{ unit.name }}</h4>
                  <p v-if="unit.description">{{ unit.description }}</p>
                </div>
                <span class="organization-count">{{ unit.staff_count }} nhân sự</span>
              </header>

              <div v-if="unit.manager" class="organization-manager">
                <span class="organization-avatar" aria-hidden="true">QL</span>
                <div>
                  <strong>{{ unit.manager.full_name }}</strong>
                  <div>{{ unit.manager.position || 'Quản lý phòng ban' }}</div>
                  <a v-if="unit.manager.phone" :href="'tel:' + unit.manager.phone">{{ unit.manager.phone }}</a>
                </div>
              </div>

              <div class="organization-members">
                <div v-for="member in unit.members" :key="member.id" class="organization-member">
                  <span class="organization-avatar" aria-hidden="true">{{ initials(member.full_name) }}</span>
                  <div class="organization-member-info">
                    <strong>{{ member.full_name }}</strong>
                    <span>{{ member.position || 'Nhân viên' }}</span>
                    <small>{{ member.store ? member.store.store_name : 'Chưa gán cơ sở' }}</small>
                  </div>
                  <a v-if="member.phone" :href="'tel:' + member.phone" class="organization-phone">
                    {{ member.phone }}
                  </a>
                </div>
              </div>
            </section>
          </div>
        </div>

        <!-- ==================== TAB 4: CHẤM CÔNG ==================== -->
        <div v-show="activeTab === 'attendance'">
          <div class="row align-items-center mb-6 bg-light rounded p-4">
            <div class="col-md-3 mb-2 mb-md-0">
              <label class="font-weight-bold text-muted font-size-sm">NGÀY CHẤM CÔNG:</label>
              <el-date-picker
                v-model="attendanceDate"
                type="date"
                format="yyyy-MM-dd"
                value-format="yyyy-MM-dd"
                class="w-100"
                @change="fetchAttendance"
              />
            </div>
            <div class="col-md-4 mb-2 mb-md-0">
              <label class="font-weight-bold text-muted font-size-sm">CƠ SỞ / CHI NHÁNH:</label>
              <el-select
                v-model="attendanceStoreId"
                placeholder="Toàn hệ thống"
                class="w-100"
                clearable
                filterable
                @change="fetchAttendance"
              >
                <el-option v-for="s in stores" :key="s.id" :label="s.store_name" :value="s.id" />
              </el-select>
            </div>
            <div class="col-md-5 text-right pt-md-4">
              <button
                type="button"
                class="btn btn-outline-primary font-weight-bold"
                :disabled="loadingAttendance"
                @click="fetchAttendance"
              >
                {{ loadingAttendance ? 'Đang tải...' : 'Làm mới bảng công' }}
              </button>
            </div>
          </div>

          <div v-if="attendanceSchemaMessage" class="alert alert-warning" role="alert">
            {{ attendanceSchemaMessage }}
          </div>

          <div
            v-else
            v-drag-scroll
            class="table-responsive attendance-table"
            role="region"
            aria-label="Bảng chấm công, có thể kéo ngang bằng chuột"
          >
            <table class="table table-bordered table-hover">
              <thead class="thead-light">
                <tr>
                  <th>Nhân sự</th>
                  <th>Cơ sở</th>
                  <th>Trạng thái</th>
                  <th>Giờ vào</th>
                  <th>Giờ ra</th>
                  <th>Giờ công</th>
                  <th>Ghi chú</th>
                  <th class="text-center">Thao tác</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in attendanceRows" :key="row.staff_id">
                  <td>
                    <strong>{{ row.full_name }}</strong>
                    <small class="d-block text-muted">{{ row.staff_code || ('NV' + row.staff_id) }} · {{ row.position || 'Nhân viên' }}</small>
                  </td>
                  <td>{{ row.store ? row.store.store_name : 'Chưa gán cơ sở' }}</td>
                  <td>
                    <select v-model="row.attendance_status" class="form-control form-control-sm attendance-control">
                      <option value="not_recorded" disabled>Chưa ghi nhận</option>
                      <option value="present">Có mặt</option>
                      <option value="late">Đi muộn</option>
                      <option value="absent">Vắng mặt</option>
                      <option value="leave">Nghỉ phép</option>
                    </select>
                  </td>
                  <td><input v-model="row.clock_in" type="time" class="form-control form-control-sm attendance-control" :disabled="attendanceHasNoHours(row)" /></td>
                  <td><input v-model="row.clock_out" type="time" class="form-control form-control-sm attendance-control" :disabled="attendanceHasNoHours(row)" /></td>
                  <td class="font-weight-bold">{{ formatWorkMinutes(row.work_minutes) }}</td>
                  <td><input v-model="row.notes" type="text" class="form-control form-control-sm attendance-notes" maxlength="500" placeholder="Ghi chú" /></td>
                  <td class="text-center">
                    <button type="button" class="btn btn-sm btn-primary font-weight-bold" :disabled="row._saving" @click="saveAttendance(row)">
                      {{ row._saving ? 'Đang lưu...' : 'Lưu' }}
                    </button>
                  </td>
                </tr>
                <tr v-if="!attendanceRows.length">
                  <td colspan="8" class="text-center text-muted py-5">
                    {{ loadingAttendance ? 'Đang tải dữ liệu...' : 'Không có nhân sự phù hợp.' }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== MODAL PHÂN CÔNG CA TRỰC ==================== -->
    <el-dialog
      :visible.sync="showDutyModal"
      title="Phân công ca trực cửa hàng"
      width="550px"
      :close-on-click-modal="false"
    >
      <div class="duty-form">
        <div class="form-group mb-3">
          <label class="font-weight-bold">Cơ sở / Cửa hàng trực <span class="text-danger">*</span></label>
          <el-select
            v-model="dutyForm.store_id"
            placeholder="Chọn cơ sở"
            class="w-100"
            filterable
          >
            <el-option
              v-for="s in stores"
              :key="s.id"
              :label="s.store_name"
              :value="s.id"
            />
          </el-select>
        </div>

        <div class="form-group mb-3">
          <label class="font-weight-bold">Ngày trực <span class="text-danger">*</span></label>
          <el-date-picker
            v-model="dutyForm.duty_date"
            type="date"
            format="yyyy-MM-dd"
            value-format="yyyy-MM-dd"
            placeholder="Chọn ngày trực"
            class="w-100"
          />
        </div>

        <div class="form-group mb-3">
          <label class="font-weight-bold">Ca trực</label>
          <el-select v-model="dutyForm.shift_name" placeholder="Chọn ca trực" class="w-100">
            <el-option label="Ca sáng (08h00 - 12h00)" value="Ca sáng (08h00 - 12h00)" />
            <el-option label="Ca chiều (13h30 - 17h30)" value="Ca chiều (13h30 - 17h30)" />
            <el-option label="Ca tối (17h30 - 21h30)" value="Ca tối (17h30 - 21h30)" />
            <el-option label="Cả ngày (08h00 - 21h30)" value="Cả ngày (08h00 - 21h30)" />
          </el-select>
        </div>

        <div class="form-group mb-3">
          <label class="font-weight-bold">Chọn từ danh sách nhân viên HCNS</label>
          <el-select
            v-model="dutyForm.selectedStaffId"
            placeholder="-- Chọn nhân viên để tự điền thông tin --"
            class="w-100"
            clearable
            filterable
            @change="handleSelectStaffForDuty"
          >
            <el-option
              v-for="st in staffList"
              :key="st.id"
              :label="st.full_name + ' - ' + (st.phone || '')"
              :value="st.id"
            />
          </el-select>
        </div>

        <div class="form-group mb-3">
          <label class="font-weight-bold">Họ và tên nhân viên trực <span class="text-danger">*</span></label>
          <el-input
            v-model="dutyForm.staff_name"
            placeholder="Nhập họ tên nhân viên trực"
            class="w-100"
          />
        </div>

        <div class="form-group mb-3">
          <label class="font-weight-bold">Số điện thoại liên hệ</label>
          <el-input
            v-model="dutyForm.staff_phone"
            placeholder="Số điện thoại nhận bàn giao / hỗ trợ khách"
            class="w-100"
          />
        </div>

        <div class="form-group mb-3">
          <label class="font-weight-bold">Vai trò trong ca</label>
          <el-input
            v-model="dutyForm.role_in_shift"
            placeholder="Ví dụ: Trưởng ca, Nhân viên kỹ thuật xe, Bàn giao xe..."
            class="w-100"
          />
        </div>

        <div class="form-group mb-3">
          <label class="font-weight-bold">Ghi chú</label>
          <el-input
            v-model="dutyForm.notes"
            type="textarea"
            :rows="2"
            placeholder="Ghi chú về ca trực..."
            class="w-100"
          />
        </div>
      </div>
      <div slot="footer" class="dialog-footer text-right">
        <button
          type="button"
          class="btn btn-outline-secondary font-weight-bold mr-2"
          @click="showDutyModal = false"
        >
          Hủy bỏ
        </button>
        <button
          type="button"
          class="btn btn-primary font-weight-bold"
          @click="handleSaveDutySchedule"
          :disabled="savingDuty"
        >
          {{ savingDuty ? 'Đang lưu...' : 'Lưu ca trực' }}
        </button>
      </div>
    </el-dialog>

    <!-- ==================== MODAL HỒ SƠ NHÂN SỰ ==================== -->
    <el-dialog
      :visible.sync="showStaffModal"
      :title="editingStaffId ? 'Cập nhật hồ sơ nhân sự' : 'Thêm mới hồ sơ nhân sự'"
      width="550px"
      :close-on-click-modal="false"
    >
      <div class="staff-form">
        <div class="form-group mb-3">
          <label class="font-weight-bold">Mã nhân viên</label>
          <el-input
            v-model="staffForm.staff_code"
            placeholder="Ví dụ: NV001"
            class="w-100"
          />
        </div>

        <div class="form-group mb-3">
          <label class="font-weight-bold">Họ và tên <span class="text-danger">*</span></label>
          <el-input
            v-model="staffForm.full_name"
            placeholder="Nhập họ và tên đầy đủ"
            class="w-100"
          />
        </div>

        <div class="form-group mb-3">
          <label class="font-weight-bold">Số điện thoại <span class="text-danger">*</span></label>
          <el-input
            v-model="staffForm.phone"
            placeholder="Nhập số điện thoại"
            class="w-100"
          />
        </div>

        <div class="form-group mb-3">
          <label class="font-weight-bold">Email</label>
          <el-input
            v-model="staffForm.email"
            placeholder="Nhập địa chỉ email"
            class="w-100"
          />
        </div>

        <div class="form-group mb-3">
          <label class="font-weight-bold">Chức danh / Vị trí</label>
          <el-input
            v-model="staffForm.position"
            placeholder="Ví dụ: Quản lý chi nhánh, Nhân viên kỹ thuật..."
            class="w-100"
          />
        </div>

        <div class="form-group mb-3">
          <label class="font-weight-bold">Cơ sở công tác</label>
          <el-select
            v-model="staffForm.store_id"
            placeholder="Chọn cơ sở"
            class="w-100"
            clearable
            filterable
          >
            <el-option
              v-for="s in stores"
              :key="s.id"
              :label="s.store_name"
              :value="s.id"
            />
          </el-select>
        </div>

        <div class="form-group mb-3">
          <label class="font-weight-bold">Trạng thái làm việc</label>
          <el-select v-model="staffForm.status" class="w-100">
            <el-option label="Đang làm việc" value="active" />
            <el-option label="Nghỉ phép" value="on_leave" />
            <el-option label="Đã nghỉ việc" value="resigned" />
          </el-select>
        </div>
      </div>
      <div slot="footer" class="dialog-footer text-right">
        <button
          type="button"
          class="btn btn-outline-secondary font-weight-bold mr-2"
          @click="showStaffModal = false"
        >
          Hủy bỏ
        </button>
        <button
          type="button"
          class="btn btn-primary font-weight-bold"
          @click="handleSaveStaff"
          :disabled="savingStaff"
        >
          {{ savingStaff ? 'Đang lưu...' : 'Lưu thông tin' }}
        </button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
import ApiService from "@/core/services/api.service";

export default {
  name: "StoreDutySchedule",
  data() {
    return {
      activeTab: "schedule",
      selectedDate: new Date().toISOString().slice(0, 10),
      selectedStoreId: null,
      stores: [],
      dutyList: [],
      loadingSchedule: false,
      savingDuty: false,
      showDutyModal: false,
      dutyForm: {
        store_id: null,
        duty_date: new Date().toISOString().slice(0, 10),
        shift_name: "Ca sáng (08h00 - 12h00)",
        selectedStaffId: null,
        staff_name: "",
        staff_phone: "",
        role_in_shift: "Nhân viên trực cửa hàng",
        notes: "",
      },

      staffList: [],
      organizationUnits: [],
      orgStoreId: null,
      loadingOrganization: false,
      attendanceRows: [],
      attendanceDate: new Date().toISOString().slice(0, 10),
      attendanceStoreId: null,
      attendanceSchemaMessage: "",
      loadingAttendance: false,
      loadingStaff: false,
      savingStaff: false,
      showStaffModal: false,
      editingStaffId: null,
      staffFilter: {
        search: "",
        store_id: null,
      },
      staffForm: {
        staff_code: "",
        full_name: "",
        phone: "",
        email: "",
        position: "",
        store_id: null,
        status: "active",
      },
    };
  },
  created() {
    this.fetchStores();
    this.fetchDutySchedules();
    this.fetchStaffList();
  },
  methods: {
    selectTab(tab) {
      this.activeTab = tab;
      if (tab === "organization" && !this.organizationUnits.length) {
        this.fetchOrganizationChart();
      }
      if (tab === "attendance" && !this.attendanceRows.length) {
        this.fetchAttendance();
      }
    },

    attendanceHasNoHours(row) {
      return row.attendance_status === "absent" || row.attendance_status === "leave";
    },

    formatWorkMinutes(minutes) {
      const value = Number(minutes) || 0;
      if (!value) return "-";
      return `${Math.floor(value / 60)} giờ ${value % 60} phút`;
    },

    async fetchAttendance() {
      this.loadingAttendance = true;
      this.attendanceSchemaMessage = "";
      try {
        const res = await ApiService.query("/api/auth/hr/attendance", {
          date: this.attendanceDate,
          store_id: this.attendanceStoreId || undefined,
        });
        const payload = res.data.data || [];
        this.attendanceRows = (Array.isArray(payload) ? payload : []).map((row) => ({
          ...row,
          _saving: false,
        }));
      } catch (err) {
        this.attendanceRows = [];
        const payload = err.response?.data || {};
        if (payload.code === "SCHEMA_NOT_READY") {
          this.attendanceSchemaMessage = "Chức năng chấm công đang khóa an toàn. Cần chạy migration 000008 trên staging trước khi sử dụng.";
        } else {
          this.$message.error(payload.message || "Không thể tải bảng chấm công");
        }
      } finally {
        this.loadingAttendance = false;
      }
    },

    async saveAttendance(row) {
      if (row.attendance_status === "not_recorded") {
        this.$message.warning("Vui lòng chọn trạng thái chấm công");
        return;
      }

      row._saving = true;
      try {
        await ApiService.post("/api/auth/hr/attendance", {
          staff_id: row.staff_id,
          attendance_date: this.attendanceDate,
          clock_in: this.attendanceHasNoHours(row) ? null : (row.clock_in || null),
          clock_out: this.attendanceHasNoHours(row) ? null : (row.clock_out || null),
          status: row.attendance_status,
          notes: row.notes || null,
        });
        this.$message.success(`Đã lưu chấm công cho ${row.full_name}`);
        await this.fetchAttendance();
      } catch (err) {
        this.$message.error(err.response?.data?.message || "Không thể lưu chấm công");
      } finally {
        row._saving = false;
      }
    },

    initials(name) {
      return (name || "NV")
        .trim()
        .split(/\s+/)
        .slice(-2)
        .map((part) => part.charAt(0).toUpperCase())
        .join("");
    },

    async fetchOrganizationChart() {
      this.loadingOrganization = true;
      try {
        const res = await ApiService.query("/api/auth/hr/organization-chart", {
          store_id: this.orgStoreId || undefined,
        });
        const payload = res.data.data || [];
        this.organizationUnits = Array.isArray(payload) ? payload : [];
      } catch (err) {
        this.organizationUnits = [];
        this.$message.error(err.response?.data?.message || "Không thể tải sơ đồ tổ chức");
      } finally {
        this.loadingOrganization = false;
      }
    },

    async fetchStores() {
      try {
        const res = await ApiService.query("/api/auth/stores/all", {});
        const stores = res.data.data || res.data || [];
        this.stores = Array.isArray(stores) ? stores : [];
      } catch (err) {
        console.error("Không thể tải danh sách cơ sở", err);
      }
    },

    async fetchDutySchedules() {
      this.loadingSchedule = true;
      try {
        const params = {
          date: this.selectedDate,
          store_id: this.selectedStoreId || undefined,
        };
        const res = await ApiService.query("/api/auth/hr/duty-schedules", params);
        const groups = res.data.data || [];
        this.dutyList = Array.isArray(groups)
          ? groups.reduce((items, group) => items.concat(group.schedules || []), [])
          : [];
      } catch (err) {
        this.$message.error(err.response?.data?.message || "Không thể tải lịch trực cửa hàng");
      } finally {
        this.loadingSchedule = false;
      }
    },

    openAddDutyModal() {
      this.dutyForm = {
        store_id: this.selectedStoreId || (this.stores.length ? this.stores[0].id : null),
        duty_date: this.selectedDate,
        shift_name: "Ca sáng (08h00 - 12h00)",
        selectedStaffId: null,
        staff_name: "",
        staff_phone: "",
        role_in_shift: "Nhân viên trực cửa hàng",
        notes: "",
      };
      this.showDutyModal = true;
    },

    handleSelectStaffForDuty(staffId) {
      if (!staffId) return;
      const st = this.staffList.find((s) => s.id === staffId);
      if (st) {
        this.dutyForm.staff_name = st.full_name;
        this.dutyForm.staff_phone = st.phone || "";
        if (st.position) {
          this.dutyForm.role_in_shift = st.position;
        }
        if (st.store_id) {
          this.dutyForm.store_id = st.store_id;
        }
      }
    },

    async handleSaveDutySchedule() {
      if (!this.dutyForm.store_id) {
        this.$message.warning("Vui lòng chọn cơ sở / cửa hàng trực");
        return;
      }
      if (!this.dutyForm.duty_date) {
        this.$message.warning("Vui lòng chọn ngày trực");
        return;
      }
      if (!this.dutyForm.staff_name) {
        this.$message.warning("Vui lòng nhập tên nhân viên trực");
        return;
      }

      this.savingDuty = true;
      try {
        await ApiService.post("/api/auth/hr/duty-schedules", this.dutyForm);
        this.$message.success("Đã phân công ca trực thành công");
        this.showDutyModal = false;
        this.fetchDutySchedules();
      } catch (err) {
        this.$message.error(err.response?.data?.message || "Không thể lưu ca trực");
      } finally {
        this.savingDuty = false;
      }
    },

    async handleDeleteDuty(item) {
      try {
        await this.$confirm(
          `Bạn có chắc chắn muốn xóa ca trực của "${item.staff_name}" tại cơ sở này không?`,
          "Xác nhận xóa",
          {
            confirmButtonText: "Đồng ý xóa",
            cancelButtonText: "Hủy bỏ",
            type: "warning",
          }
        );

        await ApiService.delete(`/api/auth/hr/duty-schedules/${item.id}`);
        this.$message.success("Đã xóa ca trực thành công");
        this.fetchDutySchedules();
      } catch (err) {
        if (err !== "cancel") {
          this.$message.error(err.response?.data?.message || "Không thể xóa ca trực");
        }
      }
    },

    async fetchStaffList() {
      this.loadingStaff = true;
      try {
        const params = {
          keyword: this.staffFilter.search || undefined,
          store_id: this.staffFilter.store_id || undefined,
        };
        const res = await ApiService.query("/api/auth/hr/staff", params);
        const payload = res.data.data || {};
        this.staffList = Array.isArray(payload) ? payload : (payload.data || []);
      } catch (err) {
        this.$message.error(err.response?.data?.message || "Không thể tải danh sách nhân sự");
      } finally {
        this.loadingStaff = false;
      }
    },

    openAddStaffModal() {
      this.editingStaffId = null;
      this.staffForm = {
        staff_code: "",
        full_name: "",
        phone: "",
        email: "",
        position: "",
        store_id: null,
        status: "active",
      };
      this.showStaffModal = true;
    },

    openEditStaffModal(staff) {
      this.editingStaffId = staff.id;
      this.staffForm = {
        id: staff.id,
        staff_code: staff.staff_code || "",
        full_name: staff.full_name || "",
        phone: staff.phone || "",
        email: staff.email || staff.personal_email || "",
        position: staff.position || "",
        store_id: staff.store_id || null,
        status: staff.status || "active",
      };
      this.showStaffModal = true;
    },

    async handleSaveStaff() {
      if (!this.staffForm.full_name) {
        this.$message.warning("Vui lòng nhập họ và tên nhân viên");
        return;
      }
      if (!this.staffForm.phone) {
        this.$message.warning("Vui lòng nhập số điện thoại nhân viên");
        return;
      }

      this.savingStaff = true;
      try {
        await ApiService.post("/api/auth/hr/staff", this.staffForm);
        this.$message.success(this.editingStaffId ? "Cập nhật hồ sơ thành công" : "Thêm mới nhân sự thành công");
        this.showStaffModal = false;
        this.fetchStaffList();
      } catch (err) {
        this.$message.error(err.response?.data?.message || "Không thể lưu thông tin nhân sự");
      } finally {
        this.savingStaff = false;
      }
    },
  },
};
</script>

<style scoped>
.badge-info {
  background-color: #3699ff;
  color: #ffffff;
}

.organization-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
  gap: 18px;
}

.organization-unit {
  overflow: hidden;
  border: 1px solid #e2e7ef;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 4px 14px rgba(28, 39, 60, 0.06);
}

.organization-unit-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding: 18px;
  border-bottom: 1px solid #edf0f4;
  background: #f8fafc;
}

.organization-unit-header h4 { margin: 3px 0; font-size: 18px; }
.organization-unit-header p { margin: 4px 0 0; color: #667085; }
.organization-unit-code { color: #9f1118; font-size: 11px; font-weight: 800; letter-spacing: .5px; }
.organization-count { flex: 0 0 auto; padding: 5px 9px; border-radius: 999px; background: #edf6ff; color: #1769aa; font-size: 12px; font-weight: 700; }
.organization-manager { display: flex; align-items: center; gap: 10px; margin: 14px; padding: 12px; border: 1px solid #f0d1d3; border-radius: 9px; background: #fff7f7; }
.organization-manager a { color: #087f5b; font-weight: 700; }
.organization-avatar { display: grid; flex: 0 0 38px; width: 38px; height: 38px; place-items: center; border-radius: 50%; background: #e8eef7; color: #344054; font-size: 12px; font-weight: 800; }
.organization-members { padding: 0 14px 14px; }
.organization-member { display: flex; align-items: center; gap: 10px; min-height: 62px; border-bottom: 1px solid #eef1f5; }
.organization-member:last-child { border-bottom: 0; }
.organization-member-info { display: flex; min-width: 0; flex: 1; flex-direction: column; }
.organization-member-info strong,
.organization-member-info span,
.organization-member-info small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.organization-member-info span,
.organization-member-info small { color: #667085; }
.organization-phone { flex: 0 0 auto; color: #087f5b; font-weight: 700; }
.attendance-table table { min-width: 1120px; }
.attendance-control { min-width: 125px; }
.attendance-notes { min-width: 190px; }

@media (max-width: 576px) {
  .organization-grid { grid-template-columns: 1fr; }
  .organization-phone { display: none; }
}
</style>
