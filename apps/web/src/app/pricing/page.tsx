'use client';

import React, { useState } from 'react';
import { AppShell } from '@/components/layout/AppShell';
import { formatMoney } from '@/lib/formatters';

interface PriceRule {
  id: number;
  type: string;
  from_year: number;
  to_year: number;
  from_date: number;
  to_date: number;
  price: number;
  pricing_type: string;
}

const INITIAL_PRICE_RULES: PriceRule[] = [
  { id: 1, type: 'Xe ga', from_year: 2020, to_year: 2024, from_date: 1, to_date: 3, price: 180000, pricing_type: 'Theo ngày' },
  { id: 2, type: 'Xe ga', from_year: 2020, to_year: 2024, from_date: 4, to_date: 7, price: 160000, pricing_type: 'Theo ngày' },
  { id: 3, type: 'Xe ga', from_year: 2020, to_year: 2024, from_date: 8, to_date: 30, price: 140000, pricing_type: 'Theo ngày' },
  { id: 4, type: 'Xe số', from_year: 2018, to_year: 2024, from_date: 1, to_date: 3, price: 120000, pricing_type: 'Theo ngày' },
  { id: 5, type: 'Xe số', from_year: 2018, to_year: 2024, from_date: 4, to_date: 30, price: 100000, pricing_type: 'Theo ngày' },
  { id: 6, type: 'Xe côn', from_year: 2021, to_year: 2024, from_date: 1, to_date: 30, price: 250000, pricing_type: 'Theo ngày' },
];

export default function PricingPage() {
  const [rules, setRules] = useState<PriceRule[]>(INITIAL_PRICE_RULES);

  const handleAddRule = () => {
    const newRule: PriceRule = {
      id: Date.now(),
      type: 'Xe ga',
      from_year: 2022,
      to_year: 2024,
      from_date: 1,
      to_date: 7,
      price: 150000,
      pricing_type: 'Theo ngày',
    };
    setRules([...rules, newRule]);
  };

  const handleDeleteRule = (id: number) => {
    setRules(rules.filter((r) => r.id !== id));
  };

  return (
    <AppShell>
      <div className="container-fluid py-4">
        <div className="card card-custom gutter-b">
          <div className="card-header d-flex justify-content-between align-items-center py-3">
            <div className="card-title mb-0">
              <h3 className="card-label font-weight-bold text-dark mb-0">
                Cài đặt bảng giá thuê xe
              </h3>
            </div>
            <button type="button" className="btn btn-success btn-sm" onClick={handleAddRule}>
              <i className="fas fa-plus mr-1" /> Thêm quy tắc giá mới
            </button>
          </div>

          <div className="card-body">
            <div className="table-responsive">
              <table className="table table-bordered table-hover align-middle">
                <thead className="thead-light text-center">
                  <tr>
                    <th>Loại xe</th>
                    <th>Từ năm sản xuất</th>
                    <th>Đến năm sản xuất</th>
                    <th>Từ ngày</th>
                    <th>Đến ngày</th>
                    <th>Mức giá</th>
                    <th>Hình thức tính</th>
                    <th style={{ width: '80px' }}>Thao tác</th>
                  </tr>
                </thead>
                <tbody>
                  {rules.map((item) => (
                    <tr key={item.id} className="text-center">
                      <td>
                        <select
                          className="form-control form-control-sm"
                          value={item.type}
                          onChange={(e) => {
                            const val = e.target.value;
                            setRules(rules.map((r) => (r.id === item.id ? { ...r, type: val } : r)));
                          }}
                        >
                          <option value="Xe ga">Xe ga</option>
                          <option value="Xe số">Xe số</option>
                          <option value="Xe côn">Xe côn</option>
                        </select>
                      </td>
                      <td>
                        <input
                          type="number"
                          className="form-control form-control-sm text-center"
                          value={item.from_year}
                          onChange={(e) => {
                            const val = Number(e.target.value);
                            setRules(rules.map((r) => (r.id === item.id ? { ...r, from_year: val } : r)));
                          }}
                        />
                      </td>
                      <td>
                        <input
                          type="number"
                          className="form-control form-control-sm text-center"
                          value={item.to_year}
                          onChange={(e) => {
                            const val = Number(e.target.value);
                            setRules(rules.map((r) => (r.id === item.id ? { ...r, to_year: val } : r)));
                          }}
                        />
                      </td>
                      <td>
                        <input
                          type="number"
                          className="form-control form-control-sm text-center"
                          value={item.from_date}
                          onChange={(e) => {
                            const val = Number(e.target.value);
                            setRules(rules.map((r) => (r.id === item.id ? { ...r, from_date: val } : r)));
                          }}
                        />
                      </td>
                      <td>
                        <input
                          type="number"
                          className="form-control form-control-sm text-center"
                          value={item.to_date}
                          onChange={(e) => {
                            const val = Number(e.target.value);
                            setRules(rules.map((r) => (r.id === item.id ? { ...r, to_date: val } : r)));
                          }}
                        />
                      </td>
                      <td>
                        <div className="input-group input-group-sm">
                          <input
                            type="number"
                            className="form-control text-right font-weight-bold"
                            value={item.price}
                            onChange={(e) => {
                              const val = Number(e.target.value);
                              setRules(rules.map((r) => (r.id === item.id ? { ...r, price: val } : r)));
                            }}
                          />
                          <div className="input-group-append">
                            <span className="input-group-text">đ</span>
                          </div>
                        </div>
                      </td>
                      <td>
                        <span className="badge badge-light-primary">{item.pricing_type}</span>
                      </td>
                      <td>
                        <button
                          type="button"
                          className="btn btn-sm btn-outline-danger"
                          onClick={() => handleDeleteRule(item.id)}
                          title="Xóa quy tắc"
                        >
                          <i className="fas fa-trash" />
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <div className="mt-3 text-right">
              <button
                type="button"
                className="btn btn-primary"
                onClick={() => alert('Cập nhật bảng giá thành công!')}
              >
                <i className="fas fa-save mr-1" /> Lưu toàn bộ bảng giá
              </button>
            </div>
          </div>
        </div>
      </div>
    </AppShell>
  );
}
