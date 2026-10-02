'use client';

import React, { useState } from 'react';
import AppShell from '@/components/layout/AppShell';

interface DutySchedule {
  id: number;
  store_id: number;
  store_name: string;
  shift_name: string;
  staff_name: string;
  staff_phone: string;
  role_in_shift: string;
  notes: string;
}

interface StaffMember {
  id: number;
  staff_code: string;
  full_name: string;
  phone: string;
  email: string;
  store_name: string;
  position: string;
  department: string;
  status: 'active' | 'leave' | 'resigned';
}

const MOCK_DUTIES: DutySchedule[] = [
  { id: 1, store_id: 1, store_name: 'Cơ sở 02 Hàng Bút', shift_name: 'Ca Sáng (07:00 - 15:00)', staff_name: 'Nguyễn Văn Nam', staff_phone: '0988123456', role_in_shift: 'Trưởng ca', notes: 'Giao nhận xe ca sáng' },
  { id: 2, store_id: 1, store_name: 'Cơ sở 02 Hàng Bút', shift_name: 'Ca Chiều (15:00 - 23:00)', staff_name: 'Trần Thị Mai', staff_phone: '0977654321', role_in_shift: 'Nhân viên lễ tân', notes: 'Trực quầy và hợp đồng' },
  { id: 3, store_id: 2, store_name: 'Cơ sở Cầu Giấy', shift_name: 'Cả ngày (08:00 - 20:00)', staff_name: 'Lê Hoàng Long', staff_phone: '0912345678', role_in_shift: 'Nhân viên kỹ thuật', notes: 'Bảo dưỡng xe định kỳ' },
];

const MOCK_STAFF: StaffMember[] = [
  { id: 1, staff_code: 'NV-001', full_name: 'Nguyễn Văn Nam', phone: '0988123456', email: 'nam.nv@himoto.vn', store_name: '02 Hàng Bút', position: 'Quản lý cửa hàng', department: 'Vận hành', status: 'active' },
  { id: 2, staff_code: 'NV-002', full_name: 'Trần Thị Mai', phone: '0977654321', email: 'mai.tt@himoto.vn', store_name: '02 Hàng Bút', position: 'Chuyên viên lễ tân', department: 'Kinh doanh', status: 'active' },
  { id: 3, staff_code: 'NV-003', full_name: 'Lê Hoàng Long', phone: '0912345678', email: 'long.lh@himoto.vn', store_name: 'Cầu Giấy', position: 'Kỹ thuật viên trưởng', department: 'Kỹ thuật bảo dưỡng', status: 'active' },
  { id: 4, staff_code: 'NV-004', full_name: 'Phạm Quỳnh Nga', phone: '0933998877', email: 'nga.pq@himoto.vn', store_name: 'Tất cả chi nhánh', position: 'Kế toán tổng hợp', department: 'Tài chính - Kế toán', status: 'active' },
];

export default function DutySchedulePage() {
  const [activeTab, setActiveTab] = useState<'schedule' | 'staff' | 'organization' | 'attendance'>('schedule');
  const [selectedDate, setSelectedDate] = useState('2026-09-20');
  const [selectedStore, setSelectedStore] = useState('all');
  const [searchTerm, setSearchTerm] = useState('');
  const [showAddModal, setShowAddModal] = useState(false);

  return (
    <AppShell>
      <div className="card card-custom gutter-b">
        <div className="card-header border-0 pt-5 d-flex flex-wrap justify-content-between align-items-center">
          <div className="card-title">
            <h3 className="card-label font-weight-bolder text-dark m-0">
              Lịch trực cửa hàng & Phân hệ HCNS
            </h3>
          </div>
          <div className="card-toolbar">
            <ul className="nav nav-pills nav-pills-sm font-weight-bold">
              <li className="nav-item">
                <button
                  type="button"
                  className={`nav-link btn btn-sm font-weight-bold px-4 py-2 mr-2 ${activeTab === 'schedule' ? 'btn-primary active text-white' : 'btn-light text-dark'}`}
                  onClick={() => setActiveTab('schedule')}
                >
                  Lịch trực cơ sở theo ngày
                </button>
              </li>
              <li className="nav-item">
                <button
                  type="button"
                  className={`nav-link btn btn-sm font-weight-bold px-4 py-2 mr-2 ${activeTab === 'staff' ? 'btn-primary active text-white' : 'btn-light text-dark'}`}
                  onClick={() => setActiveTab('staff')}
                >
                  Danh bạ hồ sơ nhân sự (HCNS)
                </button>
              </li>
              <li className="nav-item">
                <button
                  type="button"
                  className={`nav-link btn btn-sm font-weight-bold px-4 py-2 mr-2 ${activeTab === 'organization' ? 'btn-primary active text-white' : 'btn-light text-dark'}`}
                  onClick={() => setActiveTab('organization')}
                >
                  Sơ đồ tổ chức
                </button>
              </li>
              <li className="nav-item">
                <button
                  type="button"
                  className={`nav-link btn btn-sm font-weight-bold px-4 py-2 ${activeTab === 'attendance' ? 'btn-primary active text-white' : 'btn-light text-dark'}`}
                  onClick={() => setActiveTab('attendance')}
                >
                  Chấm công
                </button>
              </li>
            </ul>
          </div>
        </div>

        <div className="card-body pt-2">
          {/* TAB 1: SCHEDULE */}
          {activeTab === 'schedule' && (
            <div>
              <div className="row align-items-center mb-6 bg-light rounded p-4">
                <div className="col-md-3 mb-2 mb-md-0">
                  <label className="font-weight-bold text-muted font-size-sm">NGÀY TRỰC:</label>
                  <input
                    type="date"
                    className="form-control"
                    value={selectedDate}
                    onChange={(e) => setSelectedDate(e.target.value)}
                  />
                </div>
                <div className="col-md-3 mb-2 mb-md-0">
                  <label className="font-weight-bold text-muted font-size-sm">CƠ SỞ / CHI NHÁNH:</label>
                  <select
                    className="form-control"
                    value={selectedStore}
                    onChange={(e) => setSelectedStore(e.target.value)}
                  >
                    <option value="all">Tất cả cơ sở</option>
                    <option value="1">02 Hàng Bút</option>
                    <option value="2">Cầu Giấy</option>
                  </select>
                </div>
                <div className="col-md-6 text-right pt-md-4">
                  <button type="button" className="btn btn-outline-secondary font-weight-bold mr-2">
                    Làm mới
                  </button>
                  <button
                    type="button"
                    className="btn btn-success font-weight-bold"
                    onClick={() => setShowAddModal(true)}
                  >
                    + Thêm ca trực
                  </button>
                </div>
              </div>

              <div className="table-responsive">
                <table className="table table-bordered table-hover">
                  <thead className="thead-light">
                    <tr>
                      <th style={{ width: 60 }}>STT</th>
                      <th>Cơ sở / Cửa hàng</th>
                      <th>Ca trực</th>
                      <th>Nhân viên trực</th>
                      <th>Số điện thoại liên hệ</th>
                      <th>Vai trò trong ca</th>
                      <th>Ghi chú ca trực</th>
                      <th style={{ width: 120 }} className="text-center">Thao tác</th>
                    </tr>
                  </thead>
                  <tbody>
                    {MOCK_DUTIES.map((duty, idx) => (
                      <tr key={duty.id}>
                        <td>{idx + 1}</td>
                        <td className="font-weight-bold text-dark">{duty.store_name}</td>
                        <td>
                          <span className="badge badge-info px-2 py-1 font-weight-bold">
                            {duty.shift_name}
                          </span>
                        </td>
                        <td className="font-weight-bolder text-primary">{duty.staff_name}</td>
                        <td>
                          <a href={`tel:${duty.staff_phone}`} className="font-weight-bold text-success">
                            {duty.staff_phone} (Bấm gọi)
                          </a>
                        </td>
                        <td>{duty.role_in_shift}</td>
                        <td>{duty.notes}</td>
                        <td className="text-center">
                          <button type="button" className="btn btn-sm btn-outline-danger font-weight-bold">
                            Xóa
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {/* TAB 2: STAFF HCNS */}
          {activeTab === 'staff' && (
            <div>
              <div className="row align-items-center mb-6 bg-light rounded p-4">
                <div className="col-md-4 mb-2 mb-md-0">
                  <label className="font-weight-bold text-muted font-size-sm">TÌM KIẾM NHÂN SỰ:</label>
                  <input
                    type="text"
                    className="form-control"
                    placeholder="Tên, mã NV, SĐT, email..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                  />
                </div>
                <div className="col-md-4 mb-2 mb-md-0">
                  <label className="font-weight-bold text-muted font-size-sm">CƠ SỞ TRỰC THUỘC:</label>
                  <select className="form-control" defaultValue="all">
                    <option value="all">Tất cả cơ sở</option>
                    <option value="1">02 Hàng Bút</option>
                    <option value="2">Cầu Giấy</option>
                  </select>
                </div>
                <div className="col-md-4 text-right pt-md-4">
                  <button type="button" className="btn btn-primary font-weight-bold">
                    + Thêm hồ sơ nhân sự
                  </button>
                </div>
              </div>

              <div className="table-responsive">
                <table className="table table-bordered table-hover">
                  <thead className="thead-light">
                    <tr>
                      <th>Mã NV</th>
                      <th>Họ và tên</th>
                      <th>Số điện thoại</th>
                      <th>Email</th>
                      <th>Cơ sở</th>
                      <th>Vị trí / Chức danh</th>
                      <th>Phòng ban</th>
                      <th>Trạng thái</th>
                      <th className="text-center">Thao tác</th>
                    </tr>
                  </thead>
                  <tbody>
                    {MOCK_STAFF.map((s) => (
                      <tr key={s.id}>
                        <td className="font-weight-bold">{s.staff_code}</td>
                        <td className="font-weight-bolder text-dark">{s.full_name}</td>
                        <td>{s.phone}</td>
                        <td>{s.email}</td>
                        <td>{s.store_name}</td>
                        <td>{s.position}</td>
                        <td>{s.department}</td>
                        <td>
                          <span className="badge badge-success px-2 py-1 font-weight-bold">
                            Đang làm việc
                          </span>
                        </td>
                        <td className="text-center">
                          <button type="button" className="btn btn-sm btn-light-primary mr-1">
                            Sửa
                          </button>
                          <button type="button" className="btn btn-sm btn-light-danger">
                            Nghỉ việc
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {/* TAB 3: ORGANIZATION */}
          {activeTab === 'organization' && (
            <div className="p-4">
              <h5 className="font-weight-bold mb-4 text-dark">Sơ đồ cơ cấu tổ chức HIMOTO</h5>
              <div className="row">
                <div className="col-md-4 mb-4">
                  <div className="card border p-4 bg-light">
                    <h6 className="font-weight-bold text-primary">Ban Giám Đốc</h6>
                    <p className="text-muted mb-1">Điều hành chung toàn hệ thống</p>
                    <div className="font-weight-bold">Giám đốc điều hành: CEO Himoto</div>
                  </div>
                </div>
                <div className="col-md-4 mb-4">
                  <div className="card border p-4 bg-light">
                    <h6 className="font-weight-bold text-success">Khối Vận Hành & Cửa Hàng</h6>
                    <p className="text-muted mb-1">02 Hàng Bút & Chi nhánh Cầu Giấy</p>
                    <div className="font-weight-bold">Quản lý cơ sở: Nguyễn Văn Nam</div>
                  </div>
                </div>
                <div className="col-md-4 mb-4">
                  <div className="card border p-4 bg-light">
                    <h6 className="font-weight-bold text-warning">Khối Kỹ Thuật & Bảo Dưỡng</h6>
                    <p className="text-muted mb-1">Xưởng bảo dưỡng & Điều phối xe</p>
                    <div className="font-weight-bold">Trưởng ban: Lê Hoàng Long</div>
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* TAB 4: ATTENDANCE */}
          {activeTab === 'attendance' && (
            <div className="p-4">
              <div className="d-flex justify-content-between align-items-center mb-4">
                <h5 className="font-weight-bold text-dark m-0">Bảng chấm công nhân viên cơ sở</h5>
                <button type="button" className="btn btn-outline-primary btn-sm font-weight-bold">
                  Xuất Excel chấm công
                </button>
              </div>
              <div className="table-responsive">
                <table className="table table-bordered">
                  <thead className="thead-light">
                    <tr>
                      <th>Nhân viên</th>
                      <th>Ngày</th>
                      <th>Giờ check-in</th>
                      <th>Giờ check-out</th>
                      <th>Ca trực</th>
                      <th>Địa điểm check-in</th>
                      <th>Trạng thái</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td className="font-weight-bold">Nguyễn Văn Nam</td>
                      <td>20/09/2026</td>
                      <td>06:55</td>
                      <td>15:05</td>
                      <td>Ca Sáng</td>
                      <td>02 Hàng Bút (GPS hợp lệ)</td>
                      <td><span className="badge badge-success">Đúng giờ</span></td>
                    </tr>
                    <tr>
                      <td className="font-weight-bold">Trần Thị Mai</td>
                      <td>20/09/2026</td>
                      <td>14:58</td>
                      <td>--:--</td>
                      <td>Ca Chiều</td>
                      <td>02 Hàng Bút (GPS hợp lệ)</td>
                      <td><span className="badge badge-info">Đang trong ca</span></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </div>
      </div>
    </AppShell>
  );
}
