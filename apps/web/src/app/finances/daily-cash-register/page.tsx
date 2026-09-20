'use client';

import React, { useState } from 'react';
import { AppShell } from '@/components/layout/AppShell';
import { formatMoney, formatDate } from '@/lib/formatters';

interface CashRegisterSummary {
  status: 'open' | 'closed';
  closed_by_name?: string;
  closed_at?: string;
  opening_cash: number;
  cash_income: number;
  cash_expense: number;
  expected_closing_cash: number;
  actual_closing_cash?: number;
  cash_discrepancy?: number;

  personal_bank_income: number;
  personal_bank_expense: number;
  company_bank_income: number;
  company_bank_expense: number;
}

const SAMPLE_SUMMARY: CashRegisterSummary = {
  status: 'open',
  opening_cash: 5000000,
  cash_income: 14250000,
  cash_expense: 3500000,
  expected_closing_cash: 15750000,
  personal_bank_income: 28400000,
  personal_bank_expense: 8000000,
  company_bank_income: 16500000,
  company_bank_expense: 5200000,
};

export default function DailyCashRegisterPage() {
  const [selectedDate, setSelectedDate] = useState(() => new Date().toISOString().slice(0, 10));
  const [selectedStoreId, setSelectedStoreId] = useState('1');
  const [summary, setSummary] = useState<CashRegisterSummary>(SAMPLE_SUMMARY);
  const [actualCash, setActualCash] = useState(String(SAMPLE_SUMMARY.expected_closing_cash));
  const [note, setNote] = useState('');
  const [modalOpen, setModalOpen] = useState(false);

  const stores = [
    { id: '1', name: '02 Hàng Bút' },
    { id: '2', name: '66 Nguyễn Hoàng' },
    { id: '3', name: '476 Quang Trung' },
    { id: '4', name: '91 Trần Quốc Hoàn' },
  ];

  const handleCloseRegister = () => {
    const act = Number(actualCash);
    const disc = act - summary.expected_closing_cash;
    setSummary({
      ...summary,
      status: 'closed',
      closed_by_name: 'Quản trị viên',
      closed_at: new Date().toISOString(),
      actual_closing_cash: act,
      cash_discrepancy: disc,
    });
    setModalOpen(false);
    alert('Chốt két ngày thành công!');
  };

  const handleReopen = () => {
    if (confirm('Bạn có chắc muốn mở lại két ngày này để chỉnh sửa giao dịch?')) {
      setSummary({
        ...summary,
        status: 'open',
        closed_by_name: undefined,
        closed_at: undefined,
      });
    }
  };

  return (
    <AppShell>
      <div className="container-fluid py-4">
        <div className="card card-custom gutter-b">
          <div className="card-header border-0 d-flex justify-content-between align-items-center flex-wrap py-3">
            <div className="card-title mb-2 mb-sm-0">
              <h3 className="card-label font-weight-bold text-dark mb-0">
                Sổ két tính tiền theo ngày
              </h3>
            </div>
            <div className="card-toolbar d-flex align-items-center">
              {summary.status === 'closed' ? (
                <>
                  <span className="badge badge-success px-3 py-2 font-weight-bold mr-3">
                    ĐÃ CHỐT KÉT ({summary.closed_by_name || 'Admin'} - {formatDate(summary.closed_at)})
                  </span>
                  <button type="button" className="btn btn-sm btn-outline-danger" onClick={handleReopen}>
                    Mở lại két
                  </button>
                </>
              ) : (
                <>
                  <span className="badge badge-warning px-3 py-2 font-weight-bold mr-3">
                    ĐANG MỞ - CHƯA CHỐT
                  </span>
                  <button type="button" className="btn btn-sm btn-primary" onClick={() => setModalOpen(true)}>
                    <i className="fas fa-lock mr-1" /> Chốt két ngày
                  </button>
                </>
              )}
            </div>
          </div>

          <div className="card-body pt-2">
            {/* Bộ lọc ngày và cơ sở */}
            <div className="row align-items-center mb-4 bg-light rounded p-3">
              <div className="col-12 col-md-4 mb-2 mb-md-0">
                <label className="font-weight-bold text-muted font-size-sm">NGÀY XEM SỔ KÉT:</label>
                <input
                  type="date"
                  className="form-control"
                  value={selectedDate}
                  onChange={(e) => setSelectedDate(e.target.value)}
                />
              </div>
              <div className="col-12 col-md-4 mb-2 mb-md-0">
                <label className="font-weight-bold text-muted font-size-sm">CƠ SỞ / CỬA HÀNG:</label>
                <select
                  className="form-control"
                  value={selectedStoreId}
                  onChange={(e) => setSelectedStoreId(e.target.value)}
                >
                  {stores.map((s) => (
                    <option key={s.id} value={s.id}>{s.name}</option>
                  ))}
                </select>
              </div>
              <div className="col-12 col-md-4 text-md-right pt-md-4">
                <button type="button" className="btn btn-primary font-weight-bold" onClick={() => alert('Đã cập nhật số liệu mới nhất!')}>
                  <i className="fas fa-sync-alt mr-1" /> Làm mới số liệu
                </button>
              </div>
            </div>

            {/* 3 Cột nguồn tiền */}
            <div className="row g-3 mb-4">
              {/* Cột 1: Tiền mặt tại két */}
              <div className="col-12 col-lg-4 mb-3">
                <div className="card card-custom h-100 border shadow-xs">
                  <div className="card-header bg-light-primary py-3">
                    <h5 className="font-weight-bold text-primary mb-0">
                      <i className="fas fa-wallet mr-2" /> 1. Tiền mặt tại két
                    </h5>
                  </div>
                  <div className="card-body">
                    <div className="d-flex justify-content-between mb-2">
                      <span className="text-muted">Đầu ngày:</span>
                      <strong>{formatMoney(summary.opening_cash)}</strong>
                    </div>
                    <div className="d-flex justify-content-between mb-2 text-success">
                      <span>Thu tiền mặt:</span>
                      <strong>+{formatMoney(summary.cash_income)}</strong>
                    </div>
                    <div className="d-flex justify-content-between mb-2 text-danger">
                      <span>Chi tiền mặt:</span>
                      <strong>-{formatMoney(summary.cash_expense)}</strong>
                    </div>
                    <div className="border-top pt-2 d-flex justify-content-between mb-2">
                      <span className="font-weight-bold">Tồn lý thuyết cuối ngày:</span>
                      <strong className="text-primary font-size-lg">{formatMoney(summary.expected_closing_cash)}</strong>
                    </div>
                    {summary.status === 'closed' && (
                      <div className="bg-light p-2 rounded mt-3">
                        <div className="d-flex justify-content-between mb-1">
                          <span>Thực tế đếm:</span>
                          <strong>{formatMoney(summary.actual_closing_cash)}</strong>
                        </div>
                        <div className="d-flex justify-content-between">
                          <span>Chênh lệch:</span>
                          <strong className={summary.cash_discrepancy === 0 ? 'text-success' : 'text-danger'}>
                            {summary.cash_discrepancy === 0 ? 'Khớp két' : formatMoney(summary.cash_discrepancy)}
                          </strong>
                        </div>
                      </div>
                    )}
                  </div>
                </div>
              </div>

              {/* Cột 2: CK Cá nhân */}
              <div className="col-12 col-lg-4 mb-3">
                <div className="card card-custom h-100 border shadow-xs">
                  <div className="card-header bg-light-info py-3">
                    <h5 className="font-weight-bold text-info mb-0">
                      <i className="fas fa-user-check mr-2" /> 2. Chuyển khoản (Cá nhân)
                    </h5>
                  </div>
                  <div className="card-body">
                    <div className="d-flex justify-content-between mb-2 text-success">
                      <span>Tổng thu vào:</span>
                      <strong>+{formatMoney(summary.personal_bank_income)}</strong>
                    </div>
                    <div className="d-flex justify-content-between mb-2 text-danger">
                      <span>Tổng chi ra:</span>
                      <strong>-{formatMoney(summary.personal_bank_expense)}</strong>
                    </div>
                    <div className="border-top pt-2 d-flex justify-content-between">
                      <span className="font-weight-bold">Số dư thu ròng:</span>
                      <strong className="text-info font-size-lg">
                        {formatMoney(summary.personal_bank_income - summary.personal_bank_expense)}
                      </strong>
                    </div>
                  </div>
                </div>
              </div>

              {/* Cột 3: CK Công ty */}
              <div className="col-12 col-lg-4 mb-3">
                <div className="card card-custom h-100 border shadow-xs">
                  <div className="card-header bg-light-success py-3">
                    <h5 className="font-weight-bold text-success mb-0">
                      <i className="fas fa-building mr-2" /> 3. Chuyển khoản (Công ty)
                    </h5>
                  </div>
                  <div className="card-body">
                    <div className="d-flex justify-content-between mb-2 text-success">
                      <span>Tổng thu vào:</span>
                      <strong>+{formatMoney(summary.company_bank_income)}</strong>
                    </div>
                    <div className="d-flex justify-content-between mb-2 text-danger">
                      <span>Tổng chi ra:</span>
                      <strong>-{formatMoney(summary.company_bank_expense)}</strong>
                    </div>
                    <div className="border-top pt-2 d-flex justify-content-between">
                      <span className="font-weight-bold">Số dư thu ròng:</span>
                      <strong className="text-success font-size-lg">
                        {formatMoney(summary.company_bank_income - summary.company_bank_expense)}
                      </strong>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Modal Chốt Két */}
      {modalOpen && (
        <div className="modal fade show d-block" tabIndex={-1} role="dialog" style={{ backgroundColor: 'rgba(0,0,0,0.5)' }}>
          <div className="modal-dialog modal-md" role="document">
            <div className="modal-content">
              <div className="modal-header">
                <h5 className="modal-title font-weight-bold">Xác nhận chốt két ngày</h5>
                <button type="button" className="close" onClick={() => setModalOpen(false)}>
                  <span>&times;</span>
                </button>
              </div>
              <div className="modal-body">
                <div className="mb-3">
                  <span className="text-muted d-block font-size-sm">Tiền mặt lý thuyết trong két:</span>
                  <strong className="font-size-h4 text-primary">{formatMoney(summary.expected_closing_cash)}</strong>
                </div>
                <div className="form-group mb-3">
                  <label className="form-label font-weight-bold">Số tiền mặt thực tế kiểm đếm (VNĐ) *</label>
                  <input
                    type="number"
                    className="form-control font-weight-bold font-size-lg"
                    value={actualCash}
                    onChange={(e) => setActualCash(e.target.value)}
                    required
                  />
                  <small className="form-text text-muted">
                    Chênh lệch: {formatMoney(Number(actualCash) - summary.expected_closing_cash)}
                  </small>
                </div>
                <div className="form-group mb-2">
                  <label className="form-label">Ghi chú chốt két</label>
                  <textarea
                    className="form-control"
                    rows={2}
                    placeholder="Lý do chênh lệch (nếu có), số tờ tiền..."
                    value={note}
                    onChange={(e) => setNote(e.target.value)}
                  />
                </div>
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-secondary" onClick={() => setModalOpen(false)}>
                  Hủy bỏ
                </button>
                <button type="button" className="btn btn-primary font-weight-bold" onClick={handleCloseRegister}>
                  <i className="fas fa-lock mr-1" /> Xác nhận chốt két
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}
