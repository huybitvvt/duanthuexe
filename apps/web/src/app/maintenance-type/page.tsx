'use client';

import React, { useState } from 'react';
import AppShell from '@/components/layout/AppShell';

interface MaintenanceTypeItem {
  id: number;
  name: string;
  note: string;
}

const MOCK_TYPES: MaintenanceTypeItem[] = [
  { id: 1, name: 'Thay dầu máy', note: 'Sử dụng dầu xe tay ga 10W-40 định kỳ' },
  { id: 2, name: 'Bảo dưỡng định kỳ', note: 'Kiểm tra toàn bộ hệ thống phanh, điện, nhông xích' },
  { id: 3, name: 'Kiểm tra phanh & lốp', note: 'Đo độ mòn má phanh và áp suất lốp trước sau' },
  { id: 4, name: 'Vệ sinh bugi & lọc gió', note: 'Vệ sinh hoặc thay thế lọc gió bẩn' },
];

export default function MaintenanceTypePage() {
  const [types, setTypes] = useState<MaintenanceTypeItem[]>(MOCK_TYPES);
  const [savedSuccess, setSavedSuccess] = useState(false);

  const handleAdd = () => {
    setTypes([...types, { id: Date.now(), name: '', note: '' }]);
  };

  const handleRemove = (id: number) => {
    setTypes(types.filter((t) => t.id !== id));
  };

  const handleSave = () => {
    setSavedSuccess(true);
    setTimeout(() => setSavedSuccess(false), 3000);
  };

  return (
    <AppShell>
      <div className="card card-custom gutter-b">
        <div className="card-header d-flex justify-content-between align-items-center">
          <div className="card-title">
            <h3 className="card-label font-weight-bolder text-dark m-0">
              Cài đặt hình thức bảo dưỡng
            </h3>
          </div>
          <div className="card-toolbar">
            <button type="button" className="btn btn-light-success font-weight-bold mr-2" onClick={handleAdd}>
              + Thêm loại bảo dưỡng
            </button>
            <button type="button" className="btn btn-primary font-weight-bold" onClick={handleSave}>
              Lưu thay đổi
            </button>
          </div>
        </div>

        <div className="card-body">
          {savedSuccess && (
            <div className="alert alert-success font-weight-bold mb-4">
              ✓ Đã lưu cài đặt hình thức bảo dưỡng thành công!
            </div>
          )}

          <div className="table-responsive">
            <table className="table table-vertical-center table-hover table-bordered">
              <thead className="thead-light">
                <tr className="text-center">
                  <th style={{ width: 80 }}>STT</th>
                  <th>Tên loại bảo dưỡng</th>
                  <th>Ghi chú</th>
                  <th style={{ width: 120 }}>Hành động</th>
                </tr>
              </thead>
              <tbody>
                {types.map((item, idx) => (
                  <tr key={item.id}>
                    <td className="text-center">{idx + 1}</td>
                    <td>
                      <input
                        type="text"
                        className="form-control font-weight-bold"
                        placeholder="Tên loại bảo dưỡng"
                        value={item.name}
                        onChange={(e) => {
                          const val = e.target.value;
                          setTypes(types.map((t) => (t.id === item.id ? { ...t, name: val } : t)));
                        }}
                      />
                    </td>
                    <td>
                      <input
                        type="text"
                        className="form-control"
                        placeholder="Ghi chú"
                        value={item.note}
                        onChange={(e) => {
                          const val = e.target.value;
                          setTypes(types.map((t) => (t.id === item.id ? { ...t, note: val } : t)));
                        }}
                      />
                    </td>
                    <td className="text-center">
                      <button
                        type="button"
                        className="btn btn-sm btn-outline-danger font-weight-bold"
                        onClick={() => handleRemove(item.id)}
                      >
                        Xóa
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </AppShell>
  );
}
