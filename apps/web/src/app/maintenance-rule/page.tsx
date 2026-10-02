'use client';

import React, { useState } from 'react';
import AppShell from '@/components/layout/AppShell';

interface MaintenanceRuleItem {
  id: number;
  maintenance_type_id: number;
  maintenance_type_name: string;
  value: number; // days
}

const MOCK_RULES: MaintenanceRuleItem[] = [
  { id: 1, maintenance_type_id: 1, maintenance_type_name: 'Thay dầu máy', value: 30 },
  { id: 2, maintenance_type_id: 2, maintenance_type_name: 'Bảo dưỡng định kỳ', value: 90 },
  { id: 3, maintenance_type_id: 3, maintenance_type_name: 'Kiểm tra phanh & lốp', value: 15 },
  { id: 4, maintenance_type_id: 4, maintenance_type_name: 'Vệ sinh bugi & lọc gió', value: 60 },
];

export default function MaintenanceRulePage() {
  const [rules, setRules] = useState<MaintenanceRuleItem[]>(MOCK_RULES);
  const [savedSuccess, setSavedSuccess] = useState(false);

  const handleAddRow = () => {
    setRules([
      ...rules,
      { id: Date.now(), maintenance_type_id: 1, maintenance_type_name: 'Loại bảo dưỡng mới', value: 30 },
    ]);
  };

  const handleRemoveRow = (id: number) => {
    setRules(rules.filter((r) => r.id !== id));
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
              Cấu hình tần suất bảo dưỡng chung
            </h3>
          </div>
          <div className="card-toolbar">
            <button type="button" className="btn btn-light-success font-weight-bold mr-2" onClick={handleAddRow}>
              + Thêm quy tắc
            </button>
            <button type="button" className="btn btn-primary font-weight-bold" onClick={handleSave}>
              Lưu thay đổi
            </button>
          </div>
        </div>

        <div className="card-body">
          {savedSuccess && (
            <div className="alert alert-success font-weight-bold mb-4">
              ✓ Đã lưu cấu hình tần suất bảo dưỡng thành công!
            </div>
          )}

          <div className="table-responsive">
            <table className="table table-vertical-center table-hover table-bordered">
              <thead className="thead-light">
                <tr className="text-center">
                  <th style={{ width: 80 }}>STT</th>
                  <th>Loại bảo dưỡng</th>
                  <th>Tần suất bảo dưỡng (ngày)</th>
                  <th style={{ width: 120 }}>Hành động</th>
                </tr>
              </thead>
              <tbody>
                {rules.map((item, idx) => (
                  <tr key={item.id} className="text-center">
                    <td>{idx + 1}</td>
                    <td className="text-left font-weight-bold">
                      {item.maintenance_type_name}
                    </td>
                    <td>
                      <input
                        type="number"
                        className="form-control text-center mx-auto"
                        style={{ maxWidth: 180 }}
                        value={item.value}
                        onChange={(e) => {
                          const val = parseInt(e.target.value, 10) || 0;
                          setRules(rules.map((r) => (r.id === item.id ? { ...r, value: val } : r)));
                        }}
                      />
                    </td>
                    <td>
                      <button
                        type="button"
                        className="btn btn-sm btn-outline-danger font-weight-bold"
                        onClick={() => handleRemoveRow(item.id)}
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
