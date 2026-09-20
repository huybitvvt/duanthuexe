'use client';

import React, { useState, useEffect } from 'react';
import { AppShell } from '@/components/layout/AppShell';
import { formatMoney, formatDateTime } from '@/lib/formatters';
import api from '@/lib/api-client';

interface TransactionStats {
  all_in: number;
  all_addon: number;
  all_out: number;
  banks: {
    id: number;
    name: string;
    in: number;
    out: number;
  }[];
  cash: {
    in: number;
    out: number;
  };
}

interface TransactionItem {
  id: number;
  code?: string;
  order_id?: number;
  contract_number?: string;
  type: 'in' | 'out';
  category_name: string;
  amount: number;
  payment_method: string;
  store_name: string;
  user_name: string;
  created_at: string;
  note?: string;
}

const SAMPLE_STATS: TransactionStats = {
  all_in: 48500000,
  all_addon: 3200000,
  all_out: 18400000,
  banks: [
    { id: 1, name: 'Vietcombank - 0011004321', in: 32000000, out: 12000000 },
    { id: 2, name: 'Techcombank - 1903554422', in: 15200000, out: 4400000 },
  ],
  cash: {
    in: 4500000,
    out: 2000000,
  },
};

const SAMPLE_TRANSACTIONS: TransactionItem[] = [
  {
    id: 5042,
    code: 'PT-0920-001',
    contract_number: 'HD-202609-042',
    type: 'in',
    category_name: 'Thu tiền cọc xe',
    amount: 1500000,
    payment_method: 'Chuyển khoản VCB',
    store_name: '02 Hàng Bút',
    user_name: 'Nguyễn Thu Hà',
    created_at: '2026-09-20 08:35',
    note: 'Thu cọc xe Vision 29B1-987.65',
  },
  {
    id: 5041,
    code: 'PT-0920-002',
    contract_number: 'HD-202609-041',
    type: 'in',
    category_name: 'Thu phí thuê xe',
    amount: 650000,
    payment_method: 'Tiền mặt',
    store_name: '66 Nguyễn Hoàng',
    user_name: 'Trần Văn Nam',
    created_at: '2026-09-19 12:10',
    note: 'Khách thanh toán hết tiền thuê',
  },
  {
    id: 5040,
    code: 'PC-0920-001',
    contract_number: 'HD-202609-041',
    type: 'out',
    category_name: 'Hoàn trả cọc xe',
    amount: 1000000,
    payment_method: 'Chuyển khoản TCB',
    store_name: '66 Nguyễn Hoàng',
    user_name: 'Trần Văn Nam',
    created_at: '2026-09-19 12:15',
    note: 'Hoàn trả cọc sau khi kiểm tra xe nguyên vẹn',
  },
];

export default function TransactionsPage() {
  const [stats, setStats] = useState<TransactionStats>(SAMPLE_STATS);
  const [transactions, setTransactions] = useState<TransactionItem[]>(SAMPLE_TRANSACTIONS);
  const [isDetailOpen, setIsDetailOpen] = useState(false);
  const [keyword, setKeyword] = useState('');
  const [typeFilter, setTypeFilter] = useState('');
  const [storeFilter, setStoreFilter] = useState('');
  const [exporting, setExporting] = useState(false);

  const totalIn = stats.all_in + stats.all_addon;
  const totalOut = stats.all_out;

  const handleExport = () => {
    setExporting(true);
    setTimeout(() => {
      setExporting(false);
      alert('Xuất file Excel lịch sử thu chi thành công!');
    }, 1000);
  };

  return (
    <AppShell>
      <div className="container-fluid py-4">
        <div className="card card-custom gutter-b mb-4">
          <div className="card-header d-flex justify-content-between align-items-center py-3">
            <div className="card-title mb-0">
              <h3 className="card-label font-weight-bold text-dark mb-0">
                Lịch sử thu chi
              </h3>
            </div>
            <div className="card-toolbar">
              <button
                type="button"
                className="btn btn-success d-inline-flex align-items-center"
                disabled={exporting}
                onClick={handleExport}
              >
                <i className={`fas ${exporting ? 'fa-spinner fa-spin' : 'fa-file-excel'} mr-2`} />
                <span>{exporting ? 'Đang xuất file...' : 'Xuất Excel'}</span>
              </button>
            </div>
          </div>

          <div className="card-body">
            {/* Khung Thống kê Tổng Thu - Tổng Chi */}
            <div className="card card-custom gutter-b border shadow-xs bg-white rounded mb-4">
              <div className="card-body p-4">
                <div className="row">
                  <div className="col-md-6 mb-3 mb-md-0">
                    <div className="stats-box stats-box-in p-4 rounded border h-100 shadow-xs bg-light">
                      <span className="text-dark font-weight-bolder font-size-sm text-uppercase d-block mb-1">
                        Tổng Thu
                      </span>
                      <span className="font-size-h2 font-weight-bolder text-success">
                        {formatMoney(totalIn)}
                      </span>
                    </div>
                  </div>

                  <div className="col-md-6">
                    <div className="stats-box stats-box-out p-4 rounded border h-100 shadow-xs bg-light">
                      <span className="text-dark font-weight-bolder font-size-sm text-uppercase d-block mb-1">
                        Tổng Chi
                      </span>
                      <span className="font-size-h2 font-weight-bolder text-danger">
                        {formatMoney(totalOut)}
                      </span>
                    </div>
                  </div>
                </div>

                {/* Thanh toggle Chi tiết */}
                <div className="mt-4 pt-3 border-top d-flex align-items-center justify-content-between flex-wrap">
                  <button
                    type="button"
                    className="btn btn-sm btn-outline-primary font-weight-bolder shadow-xs d-inline-flex align-items-center"
                    onClick={() => setIsDetailOpen(!isDetailOpen)}
                  >
                    <i className={`fas fa-chevron-${isDetailOpen ? 'up' : 'down'} mr-2`} />
                    <span>{isDetailOpen ? 'Thu gọn chi tiết' : 'Xem chi tiết tài khoản'}</span>
                    <span className="ml-2 badge badge-primary text-white">
                      {(stats.banks ? stats.banks.length : 0) + 1}
                    </span>
                  </button>
                  <span className="text-muted font-size-sm font-weight-bold mt-1 mt-md-0">
                    Bấm để xem chi tiết từng tài khoản ngân hàng & tiền mặt
                  </span>
                </div>

                {/* Bảng chi tiết mở rộng */}
                {isDetailOpen && (
                  <div className="mt-3 table-responsive rounded border bg-white shadow-xs">
                    <table className="table table-vertical-center table-hover table-bordered mb-0">
                      <thead className="thead-light">
                        <tr>
                          <th scope="col" className="font-weight-bolder text-dark">Tài Khoản / Phương thức</th>
                          <th scope="col" className="font-weight-bolder text-success text-right" style={{ minWidth: '140px' }}>Tổng Thu</th>
                          <th scope="col" className="font-weight-bolder text-danger text-right" style={{ minWidth: '140px' }}>Tổng Chi</th>
                        </tr>
                      </thead>
                      <tbody>
                        {stats.banks.map((b) => (
                          <tr key={b.id}>
                            <td className="font-weight-bold">{b.name}</td>
                            <td className="text-right text-success font-weight-bold">{formatMoney(b.in)}</td>
                            <td className="text-right text-danger font-weight-bold">{formatMoney(b.out)}</td>
                          </tr>
                        ))}
                        <tr>
                          <td className="font-weight-bold">Quỹ tiền mặt</td>
                          <td className="text-right text-success font-weight-bold">{formatMoney(stats.cash.in)}</td>
                          <td className="text-right text-danger font-weight-bold">{formatMoney(stats.cash.out)}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
            </div>

            {/* Filter Bar */}
            <div className="row g-2 mb-4">
              <div className="col-12 col-md-4 mb-2">
                <input
                  type="text"
                  className="form-control"
                  placeholder="Mã phiếu, số HĐ, ghi chú..."
                  value={keyword}
                  onChange={(e) => setKeyword(e.target.value)}
                />
              </div>
              <div className="col-12 col-md-3 mb-2">
                <select
                  className="form-control"
                  value={typeFilter}
                  onChange={(e) => setTypeFilter(e.target.value)}
                >
                  <option value="">Tất cả (Thu & Chi)</option>
                  <option value="in">Khoản thu (+)</option>
                  <option value="out">Khoản chi (-)</option>
                </select>
              </div>
              <div className="col-12 col-md-3 mb-2">
                <select
                  className="form-control"
                  value={storeFilter}
                  onChange={(e) => setStoreFilter(e.target.value)}
                >
                  <option value="">Tất cả cơ sở</option>
                  <option value="02 Hàng Bút">02 Hàng Bút</option>
                  <option value="66 Nguyễn Hoàng">66 Nguyễn Hoàng</option>
                  <option value="476 Quang Trung">476 Quang Trung</option>
                </select>
              </div>
            </div>

            {/* Transaction Table */}
            <div className="table-responsive">
              <table className="table table-hover table-striped align-middle">
                <thead className="thead-light">
                  <tr>
                    <th style={{ width: '60px' }}>#ID</th>
                    <th>Mã phiếu</th>
                    <th>Số HĐ liên quan</th>
                    <th>Khoản mục</th>
                    <th>Phương thức</th>
                    <th>Cơ sở</th>
                    <th>Số tiền</th>
                    <th>Người thực hiện</th>
                    <th>Thời gian</th>
                  </tr>
                </thead>
                <tbody>
                  {transactions.map((tx) => (
                    <tr key={tx.id}>
                      <td className="font-weight-bold">#{tx.id}</td>
                      <td>
                        <span className="badge badge-light-dark font-weight-bold">{tx.code || '--'}</span>
                      </td>
                      <td>
                        {tx.contract_number ? (
                          <strong className="text-primary">{tx.contract_number}</strong>
                        ) : (
                          <span className="text-muted">--</span>
                        )}
                      </td>
                      <td>{tx.category_name}</td>
                      <td>
                        <span className="badge badge-secondary">{tx.payment_method}</span>
                      </td>
                      <td>{tx.store_name}</td>
                      <td>
                        <strong className={`font-size-sm ${tx.type === 'in' ? 'text-success' : 'text-danger'}`}>
                          {tx.type === 'in' ? '+' : '-'}{formatMoney(tx.amount)}
                        </strong>
                      </td>
                      <td>{tx.user_name}</td>
                      <td className="font-size-sm text-muted">{formatDateTime(tx.created_at)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </AppShell>
  );
}
