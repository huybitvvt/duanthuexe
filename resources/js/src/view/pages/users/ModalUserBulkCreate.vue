<template>
    <div>
        <button type="button" class="btn btn-primary font-weight-bold ml-2" @click="open">
            Thêm hàng loạt
        </button>
        <el-dialog
            :visible.sync="visible"
            title="Thêm hàng loạt nhân sự"
            width="1100px"
            :close-on-click-modal="false"
            append-to-body
        >
            <p class="text-muted mb-3">
                Danh sách đã điền theo sơ đồ ngày 10/09/2025. Email và mật khẩu được tạo sẵn, sửa được trước khi ghi.
            </p>
            <div class="form-group">
                <label class="font-weight-bold">Mật khẩu dùng chung</label>
                <el-input v-model="password" show-password placeholder="Ít nhất 6 ký tự" />
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-sm mb-0">
                    <thead>
                        <tr>
                            <th style="width: 42px;" class="text-center">
                                <input type="checkbox" :checked="allSelected" @change="toggleAll($event.target.checked)" />
                            </th>
                            <th>Nhân sự</th>
                            <th style="width: 140px;">Số điện thoại</th>
                            <th style="width: 230px;">Email</th>
                            <th style="width: 200px;">Quyền</th>
                            <th style="width: 160px;">Cửa hàng</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.staff_code">
                            <td class="text-center">
                                <input type="checkbox" v-model="row.selected" />
                            </td>
                            <td>
                                <div class="font-weight-bold">{{ row.name }}</div>
                                <small class="text-muted">{{ row.position }}</small>
                            </td>
                            <td>
                                <el-input v-model="row.phone" size="small" />
                            </td>
                            <td>
                                <el-input v-model="row.email" size="small" />
                            </td>
                            <td>
                                <el-select v-model="row.role_id" filterable placeholder="Chọn quyền" class="w-100" size="small">
                                    <el-option v-for="role in roles" :key="role.id" :label="role.name" :value="role.id" />
                                </el-select>
                            </td>
                            <td>
                                <el-select v-model="row.store_id" clearable filterable placeholder="Không gắn" class="w-100" size="small">
                                    <el-option v-for="store in stores" :key="store.id" :label="storeLabel(store)" :value="store.id" />
                                </el-select>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div slot="footer" class="text-right">
                <button type="button" class="btn btn-outline-secondary font-weight-bold mr-2" @click="visible = false">
                    Hủy
                </button>
                <button type="button" class="btn btn-success font-weight-bold" :disabled="saving" @click="submit">
                    {{ saving ? 'Đang tạo...' : 'Tạo ' + selectedCount + ' tài khoản' }}
                </button>
            </div>
        </el-dialog>
    </div>
</template>

<script>
import { USER_BULK_CREATE } from "../../../core/services/store/user.module";
import { getApiMessage } from "@/utils/apiErrorHandler";

const ORG_STAFF = [
    { staff_code: "ORG-VH-01", name: "Phan Công Minh", phone: "0342567705", role: "van-hanh", store: null, position: "Vận hành", department_code: "VH", department_name: "Vận hành" },
    { staff_code: "ORG-HCNS-01", name: "Nguyễn Minh Tuấn", phone: "0986268908", role: "nhan-su", store: null, position: "HCNS", department_code: "HCNS", department_name: "Hành chính nhân sự", notes: "SĐT phụ 0916683055." },
    { staff_code: "ORG-HCNS-02", name: "Vũ Hồng Diệp", phone: "0944280985", role: "nhan-su", store: null, position: "HCNS", department_code: "HCNS", department_name: "Hành chính nhân sự" },
    { staff_code: "ORG-QLCN-01", name: "Nguyễn Như Hoài Linh", phone: "0988296110", role: "ke-toan", store: null, position: "Leader công nợ / Kế toán / Leader thuê sở hữu", department_code: "QLCN", department_name: "Quản lý công nợ", notes: "Cùng một người ở ba ô trên sơ đồ. Quyền mặc định là Kế toán, đổi được trước khi tạo." },
    { staff_code: "ORG-QLCN-02", name: "Nguyễn Văn Nam", phone: "09866547251", role: "thue-so-huu-thu-hoi-no", store: "CS6", position: "Quản lý công nợ", department_code: "QLCN", department_name: "Quản lý công nợ", notes: "SĐT phụ 0919594358. Số chính trên sơ đồ có 11 chữ số." },
    { staff_code: "ORG-QLCN-03", name: "Phan Đức Minh", phone: "0982392644", role: "thue-so-huu-thu-hoi-no", store: "CS6", position: "Quản lý công nợ", department_code: "QLCN", department_name: "Quản lý công nợ", notes: "SĐT phụ 0915923055." },
    { staff_code: "ORG-BPSC-01", name: "Đỗ Đức Hưng", phone: "0968630185", role: "nhan-vien", store: null, position: "BPSC", department_code: "BPSC", department_name: "BPSC", notes: "Sơ đồ không có quyền tương ứng. Mặc định Nhân viên, đổi được trước khi tạo." },
    { staff_code: "ORG-TLS-01", name: "Hoàng Thị Kim Huệ", phone: "0364788339", role: "telesale", store: null, position: "Telesale", department_code: "TLS", department_name: "Telesales", notes: "SĐT phụ 0886184116." },
    { staff_code: "ORG-TLS-02", name: "Nguyễn Như Thu Trang", phone: "03347418746", role: "telesale", store: null, position: "Telesale", department_code: "TLS", department_name: "Telesales", notes: "SĐT phụ 0855184116. Số chính trên sơ đồ có 11 chữ số." },
    { staff_code: "ORG-CS2-TPKD", name: "Vi Văn Tượng", phone: "0376654118", role: "quan-ly-cua-hang", store: "CS2", position: "TPKD Nguyễn Hoàng", department_code: "KDCS", department_name: "Kinh doanh cơ sở" },
    { staff_code: "ORG-CS2-NVKD", name: "Vũ Hoàng Minh", phone: "0775284212", role: "nhan-vien", store: "CS2", position: "NVKD Nguyễn Hoàng", department_code: "KDCS", department_name: "Kinh doanh cơ sở" },
    { staff_code: "ORG-CS2-PT01", name: "Kiều Phương Anh", phone: "0325518898", role: "nhan-vien", store: "CS2", position: "PT Nguyễn Hoàng", department_code: "KDCS", department_name: "Kinh doanh cơ sở" },
    { staff_code: "ORG-CS2-PT02", name: "Nguyễn Nhật Lil", phone: "0706355781", role: "nhan-vien", store: "CS2", position: "PT Nguyễn Hoàng", department_code: "KDCS", department_name: "Kinh doanh cơ sở" },
    { staff_code: "ORG-CS1-TPKD", name: "Nguyễn Đức Anh", phone: "0395505622", role: "quan-ly-cua-hang", store: "CS1", position: "TPKD Láng", department_code: "KDCS", department_name: "Kinh doanh cơ sở" },
    { staff_code: "ORG-CS1-NVKD", name: "Nguyễn Minh Hiếu", phone: "0394566430", role: "nhan-vien", store: "CS1", position: "NVKD Láng", department_code: "KDCS", department_name: "Kinh doanh cơ sở" },
    { staff_code: "ORG-CS1-PT", name: "Hoàng Thị Yến Vi", phone: "0348444989", role: "nhan-vien", store: "CS1", position: "PT Láng", department_code: "KDCS", department_name: "Kinh doanh cơ sở" },
    { staff_code: "ORG-CS3-TPKD", name: "Nguyễn Hải Đăng", phone: "0378784066", role: "quan-ly-cua-hang", store: "CS3", position: "TPKD Thuốc Bắc", department_code: "KDCS", department_name: "Kinh doanh cơ sở", notes: "Sơ đồ ghi Thuốc Bắc, gắn CS3." },
    { staff_code: "ORG-CS3-NVKD", name: "Nguyễn Quang Trường", phone: "0325378569", role: "nhan-vien", store: "CS3", position: "NVKD Thuốc Bắc", department_code: "KDCS", department_name: "Kinh doanh cơ sở", notes: "Sơ đồ ghi Thuốc Bắc, gắn CS3." },
    { staff_code: "ORG-CS5-NVKD01", name: "Trần Tấn Cảnh", phone: "0974992405", role: "nhan-vien", store: "CS5", position: "NVKD Hà Đông", department_code: "KDCS", department_name: "Kinh doanh cơ sở" },
    { staff_code: "ORG-CS5-NVKD02", name: "Phạm Quốc Cường", phone: "0355403060", role: "nhan-vien", store: "CS5", position: "NVKD Hà Đông", department_code: "KDCS", department_name: "Kinh doanh cơ sở" },
    { staff_code: "ORG-CS5-PT", name: "Đinh Ngọc Phụng", phone: "0386125866", role: "nhan-vien", store: "CS5", position: "PT Hà Đông", department_code: "KDCS", department_name: "Kinh doanh cơ sở" },
    { staff_code: "ORG-CS4-TPKD", name: "Nguyễn Thị Vui", phone: "0396589623", role: "quan-ly-cua-hang", store: "CS4", position: "TPKD Giáp Bát", department_code: "KDCS", department_name: "Kinh doanh cơ sở" },
    { staff_code: "ORG-CS4-NVKD01", name: "Hà Viết Giang", phone: "0333648392", role: "nhan-vien", store: "CS4", position: "NVKD Giáp Bát", department_code: "KDCS", department_name: "Kinh doanh cơ sở" },
    { staff_code: "ORG-CS4-NVKD02", name: "Lê Duy Thủy", phone: "0963165055", role: "nhan-vien", store: "CS4", position: "NVKD Giáp Bát", department_code: "KDCS", department_name: "Kinh doanh cơ sở" },
    { staff_code: "ORG-CS4-PT", name: "Lê Trần Hiến", phone: "0778430858", role: "nhan-vien", store: "CS4", position: "PT Giáp Bát", department_code: "KDCS", department_name: "Kinh doanh cơ sở" },
];

export default {
    name: "ModalUserBulkCreate",
    props: {
        roles: { type: Array, default: () => [] },
        stores: { type: Array, default: () => [] },
    },
    data() {
        return {
            visible: false,
            saving: false,
            password: "",
            rows: [],
        };
    },
    computed: {
        selectedRows() {
            return this.rows.filter((row) => row.selected);
        },
        selectedCount() {
            return this.selectedRows.length;
        },
        allSelected() {
            return this.rows.length > 0 && this.selectedCount === this.rows.length;
        },
    },
    methods: {
        open() {
            this.password = this.makePassword();
            this.rows = ORG_STAFF.map((person) => ({
                ...person,
                selected: true,
                email: this.emailFor(person.name),
                role_id: this.roleId(person.role),
                store_id: this.storeId(person.store),
            }));
            this.visible = true;
        },
        emailFor(name) {
            const local = name
                .normalize("NFD")
                .replace(/[\u0300-\u036f]/g, "")
                .replace(/đ/gi, "d")
                .toLowerCase()
                .replace(/[^a-z]/g, "");
            return `${local}@himoto.vn`;
        },
        roleId(slug) {
            const role = this.roles.find((item) => item.slug === slug);
            return role ? role.id : null;
        },
        storeId(code) {
            if (!code) return null;
            const store = this.stores.find((item) => item.code === code);
            return store ? store.id : null;
        },
        storeLabel(store) {
            return store.code ? `${store.code} — ${store.store_name}` : store.store_name;
        },
        makePassword() {
            const chars = "ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789";
            const bytes = new Uint8Array(10);
            window.crypto.getRandomValues(bytes);
            return Array.from(bytes, (byte) => chars[byte % chars.length]).join("");
        },
        toggleAll(checked) {
            this.rows.forEach((row) => {
                row.selected = checked;
            });
        },
        async submit() {
            if (!this.password || this.password.length < 6) {
                this.$message.warning("Mật khẩu cần ít nhất 6 ký tự");
                return;
            }
            if (!this.selectedCount) {
                this.$message.warning("Chọn ít nhất một nhân sự");
                return;
            }
            const missingRole = this.selectedRows.find((row) => !row.role_id);
            if (missingRole) {
                this.$message.warning(`Chưa có quyền cho ${missingRole.name}`);
                return;
            }
            this.saving = true;
            try {
                const result = await this.$store.dispatch(USER_BULK_CREATE, {
                    password: this.password,
                    users: this.selectedRows.map((row) => ({
                        name: row.name,
                        email: row.email,
                        phone: row.phone,
                        role_id: row.role_id,
                        store_id: row.store_id || null,
                        staff_code: row.staff_code,
                        position: row.position,
                        department_code: row.department_code,
                        department_name: row.department_name,
                        notes: row.notes || null,
                    })),
                });
                this.$message.success(result?.message || "Đã tạo tài khoản nhân sự");
                this.visible = false;
                this.$emit("storeSuccess");
            } catch (error) {
                this.$message.error(getApiMessage(error));
            } finally {
                this.saving = false;
            }
        },
    },
};
</script>
