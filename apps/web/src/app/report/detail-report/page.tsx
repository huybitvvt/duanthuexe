'use client';

import React, { useState } from 'react';
import { AppShell } from '@/components/layout/AppShell';
import { formatMoney } from '@/lib/formatters';

interface StoreReportRow {
  store_id: number;
  store_name: string;
  total_real_in: number;
  total_real_out: number;
  total_deposit: number;
  total_renew: number;
  total_rental_fees: number;
  total_real_refund: number;
  total_money_early: number;
  total_money_out_date: number;
  days_data?: {
    date: string;
    real_in: number;
    real_out: number;
    deposit: number;
    renew: number;
    rental_fees: number;
  }[];
}

const SAMPLE_STORE_REPORTS: StoreReportRow[] = [
  {
    store_id: 1,
    store_name: '02 Hàng Bút',
    total_real_in: 28500000,
    total_real_out: 9200000,
    total_deposit: 12000000,
    total_renew: 4500000,
    total_rental_fees: 12000000,
    total_real_refund: 8000000,
    total_money_early: -500000,
    total_money_out_date: 700000,
    days_data: [
      { date: '20/09/2026', real_in: 8500000, real_out: 2200000, deposit: 3500000, renew: 1500000, rental_fees: 3500000 },
      { date: '19/09/2026', real_in: 11000000, real_out: 4000000, deposit: 4500000, renew: 1500000, rental_fees: 5000000 },
      { date: '18/09/2026', real_in: 9000000, real_out: 3000000, deposit: 4000000, renew: 1500000, rental_fees: 3500000 },
    ],
  },
  {
    store_id: 2,
    store_name: '66 Nguyễn Hoàng',
    total_real_in: 18200000,
    total_real_out: 5800000,
    total_deposit: 8000000,
    total_renew: 2200000,
    total_rental_fees: 8000000,
    total_real_refund: 5000000,
    total_money_early: -200000,
    total_money_out_date: 400000,
    days_data: [
      { date: '20/09/2026', real_in: 6000000, real_out: 1800000, deposit: 2500000, renew: 1000000, rental_fees: 2500000 },
      { date: '19/09/2026', real_in: 7200000, real_out: 2500000, deposit: 3000000, renew: 700000, rental_fees: 3500000 },
      { date: '18/09/2026', real_in: 5000000, real_out: 1500000, deposit: 2500000, renew: 500000, rental_fees: 2000000 },
    ],
  },
  {
    store_id: 3,
    store_name: '476 Quang Trung',
    total_real_in: 12400000,
    total_real_out: 3400000,
    total_deposit: 5500000,
    total_renew: 1400000,
    total_rental_fees: 5500000,
    total_real_refund: 3000000,
    total_money_early: -100000,
    total_money_out_date: 300000,
  },
  {
    store_id: 4,
    store_name: '91 Trần Quốc Hoàn',
    total_real_in: 14600000,
    total_real_out: 4100000,
    total_deposit: 6500000,
    total_renew: 1600000,
    total_rental_fees: 6500000,
    total_real_refund: 3500000,
    total_money_early: -150000,
    total_money_out_date: 350000,
  },
];

export default function DetailReportPage() {
  const [dateMode, setDateMode] = useState<'single' | 'range'>('single');
  const [singleDate, setSingleDate] = useState(() => new Date().toISOString().slice(0, 10));
  const [startDate, setStartDate] = useState(() => new Date().toISOString().slice(0, 10));
  const [endDate, setEndDate] = useState(() => new Date().toISOString().slice(0, 10));
  const [storeFilter, setStoreFilter] = useState('');
  const [loading, setLoading] = useState(false);
  const [expandedStoreId, setExpandedStoreId] = useState<number | null>(null);
  const [loadingStoreId, setLoadingStoreId] = useState<number | null>(null);

  // Totals for "Tất cả cửa hàng"
  const totals = SAMPLE_STORE_REPORTS.reduce(
    (acc, row) => ({
      real_in: acc.real_in + row.total_real_in,
      real_out: acc.real_out + row.total_real_out,
      deposit: acc.deposit + row.total_deposit,
      renew: acc.renew + row.total_renew,
      rental_fees: acc.rental_fees + row.total_rental_fees,
      real_refund: acc.real_refund + row.total_real_refund,
      money_early: acc.money_early + row.total_money_early,
      money_out_date: acc.money_out_date + row.total_money_out_date,
    }),
    { real_in: 0, real_out: 0, deposit: 0, renew: 0, rental_fees: 0, real_refund: 0, money_early: 0, money_out_date: 0 }
  );

  const handleSelectToday = () => {
    const today = new Date().toISOString().slice(0, 10);
    setDateMode('single');
    setSingleDate(today);
    setStartDate(today);
    setEndDate(today);
  };

  const handleViewStoreDetail = (storeId: number) => {
    setLoadingStoreId(storeId);
    setTimeout(() => {
      setExpandedStoreId(expandedStoreId === storeId ? null : storeId);
      setLoadingStoreId(null);

      // Smooth scroll down to the store section
      const el = document.getElementById(`store-section-${storeId}`);
      if (el) {
        el.scrollIntoView({ behavior: 'smooth' });
      }
    }, 600);
  };

  return (
    <AppShell>
      <div className="container-fluid py-4">
        <div className="card card-custom gutter-b mb-4">
          <div className="card-header d-flex justify-content-between align-items-center py-3">
            <div className="card-title mb-0">
              <h3 className="card-label font-weight-bold text-dark mb-0">
                Tổng quan báo cáo thuê xe
              </h3>
            </div>
            <button
              type="button"
              className="btn btn-success"
              onClick={() => alert('Đang xuất file Excel báo cáo tổng quan...')}
            >
              <i className="fas fa-file-excel mr-2" /> Xuất Excel
            </button>
          </div>

          <div className="card-body">
            {/* Bộ chọn ngày & bộ lọc */}
            <div className="filter-wrapper bg-light p-4 rounded mb-4 border">
              <div className="d-flex align-items-center justify-content-between flex-wrap mb-3">
                <div className="d-flex align-items-center flex-wrap">
                  <span className="font-weight-bold text-dark mr-3 mb-2">
                    <i className="fas fa-filter text-primary mr-1" /> Lựa chọn ngày:
                  </span>
                  <div className="btn-group mr-3 mb-2">
                    <button
                      type="button"
                      className={`btn btn-sm ${dateMode === 'single' ? 'btn-primary font-weight-bold' : 'btn-white border text-dark'}`}
                      onClick={() => setDateMode('single')}
                    >
                      <i className="fas fa-calendar-day mr-1" /> 1. Lựa chọn từng ngày (lẻ 1 ngày)
                    </button>
                    <button
                      type="button"
                      className={`btn btn-sm ${dateMode === 'range' ? 'btn-primary font-weight-bold' : 'btn-white border text-dark'}`}
                      onClick={() => setDateMode('range')}
                    >
                      <i className="fas fa-calendar-alt mr-1" /> 2. Lựa chọn khoảng ngày
                    </button>
                  </div>
                  <button
                    type="button"
                    className="btn btn-sm btn-warning font-weight-bold mb-2 shadow-sm"
                    onClick={handleSelectToday}
                  >
                    ⚡ Hôm nay
                  </button>
                </div>

                <div className="mb-2">
                  <span className="badge badge-light-primary font-size-sm p-2">
                    <i className="fas fa-clock mr-1" /> Đang xem: <strong>{dateMode === 'single' ? singleDate : `${startDate} ~ ${endDate}`}</strong>
                  </span>
                </div>
              </div>

              <div className="row align-items-end g-2">
                {dateMode === 'single' ? (
                  <div className="col-12 col-md-4 mb-2">
                    <label className="font-weight-bold font-size-sm text-muted">Chọn ngày xem:</label>
                    <input
                      type="date"
                      className="form-control"
                      value={singleDate}
                      onChange={(e) => setSingleDate(e.target.value)}
                    />
                  </div>
                ) : (
                  <>
                    <div className="col-12 col-md-3 mb-2">
                      <label className="font-weight-bold font-size-sm text-muted">Từ ngày:</label>
                      <input
                        type="date"
                        className="form-control"
                        value={startDate}
                        onChange={(e) => setStartDate(e.target.value)}
                      />
                    </div>
                    <div className="col-12 col-md-3 mb-2">
                      <label className="font-weight-bold font-size-sm text-muted">Đến ngày:</label>
                      <input
                        type="date"
                        className="form-control"
                        value={endDate}
                        onChange={(e) => setEndDate(e.target.value)}
                      />
                    </div>
                  </>
                )}

                <div className="col-12 col-md-3 mb-2">
                  <label className="font-weight-bold font-size-sm text-muted">Cơ sở / Cửa hàng:</label>
                  <select
                    className="form-control"
                    value={storeFilter}
                    onChange={(e) => setStoreFilter(e.target.value)}
                  >
                    <option value="">Tất cả cơ sở</option>
                    {SAMPLE_STORE_REPORTS.map((s) => (
                      <option key={s.store_id} value={s.store_name}>{s.store_name}</option>
                    ))}
                  </select>
                </div>

                <div className="col-12 col-md-2 mb-2">
                  <button
                    type="button"
                    className="btn btn-primary w-100 font-weight-bold"
                    onClick={() => alert('Đã cập nhật dữ liệu báo cáo!')}
                  >
                    <i className="fas fa-search mr-1" /> Xem báo cáo
                  </button>
                </div>
              </div>
            </div>

            {/* Bảng báo cáo theo từng cửa hàng */}
            <div className="table-responsive">
              <table className="table table-bordered table-hover align-middle">
                <thead className="thead-light text-center">
                  <tr>
                    <th>Cơ sở / Cửa hàng</th>
                    <th>Tổng thu thực tế</th>
                    <th>Tổng chi thực tế</th>
                    <th>Thu cọc</th>
                    <th>Thu gia hạn</th>
                    <th>Thu phí thuê</th>
                    <th>Hoàn cọc</th>
                    <th>Hoàn trả sớm</th>
                    <th>Phạt muộn</th>
                    <th>Chi tiết</th>
                  </tr>
                </thead>
                <tbody>
                  {/* Hàng tổng cộng: Tất cả cửa hàng */}
                  <tr className="bg-light-primary font-weight-bolder">
                    <td className="text-primary font-size-lg">
                      <i className="fas fa-building mr-1" /> TẤT CẢ CỬA HÀNG
                    </td>
                    <td className="text-right text-success">{formatMoney(totals.real_in)}</td>
                    <td className="text-right text-danger">{formatMoney(totals.real_out)}</td>
                    <td className="text-right">{formatMoney(totals.deposit)}</td>
                    <td className="text-right">{formatMoney(totals.renew)}</td>
                    <td className="text-right">{formatMoney(totals.rental_fees)}</td>
                    <td className="text-right text-danger">{formatMoney(totals.real_refund)}</td>
                    <td className="text-right text-danger">{formatMoney(Math.abs(totals.money_early))}</td>
                    <td className="text-right text-warning">{formatMoney(totals.money_out_date)}</td>
                    <td className="text-center text-muted">--</td>
                  </tr>

                  {/* Từng cửa hàng */}
                  {SAMPLE_STORE_REPORTS.map((store) => (
                    <React.Fragment key={store.store_id}>
                      <tr id={`store-section-${store.store_id}`}>
                        <td className="font-weight-bold">{store.store_name}</td>
                        <td className="text-right font-weight-bold text-success">{formatMoney(store.total_real_in)}</td>
                        <td className="text-right font-weight-bold text-danger">{formatMoney(store.total_real_out)}</td>
                        <td className="text-right">{formatMoney(store.total_deposit)}</td>
                        <td className="text-right">{formatMoney(store.total_renew)}</td>
                        <td className="text-right">{formatMoney(store.total_rental_fees)}</td>
                        <td className="text-right text-danger">{formatMoney(store.total_real_refund)}</td>
                        <td className="text-right text-danger">{formatMoney(Math.abs(store.total_money_early))}</td>
                        <td className="text-right text-warning">{formatMoney(store.total_money_out_date)}</td>
                        <td className="text-center">
                          <button
                            type="button"
                            className="btn btn-xs btn-outline-primary font-weight-bold"
                            disabled={loadingStoreId === store.store_id}
                            onClick={() => handleViewStoreDetail(store.store_id)}
                          >
                            {loadingStoreId === store.store_id ? (
                              <>
                                <i className="fas fa-spinner fa-spin mr-1" /> Đang tải...
                              </>
                            ) : (
                              <>
                                <i className={`fas fa-chevron-${expandedStoreId === store.store_id ? 'up' : 'down'} mr-1`} />
                                {expandedStoreId === store.store_id ? 'Thu gọn' : 'Xem chi tiết'}
                              </>
                            )}
                          </button>
                        </td>
                      </tr>

                      {/* Chi tiết từng ngày khi bấm xem chi tiết */}
                      {expandedStoreId === store.store_id && (
                        <tr>
                          <td colSpan={10} className="p-3 bg-light">
                            <div className="border rounded bg-white p-3 shadow-xs">
                              <h6 className="font-weight-bold text-primary mb-3">
                                <i className="fas fa-calendar-check mr-2" />
                                Chi tiết thu chi từng ngày: {store.store_name}
                              </h6>
                              {store.days_data && store.days_data.length > 0 ? (
                                <table className="table table-sm table-bordered mb-0">
                                  <thead className="thead-light">
                                    <tr className="text-center font-size-xs">
                                      <th>Ngày</th>
                                      <th>Thu thực tế</th>
                                      <th>Chi thực tế</th>
                                      <th>Thu cọc</th>
                                      <th>Thu gia hạn</th>
                                      <th>Thu phí thuê</th>
                                    </tr>
                                  </thead>
                                  <tbody>
                                    {store.days_data.map((d, i) => (
                                      <tr key={i} className="text-center font-size-sm">
                                        <td><strong>{d.date}</strong></td>
                                        <td className="text-right text-success font-weight-bold">{formatMoney(d.real_in)}</td>
                                        <td className="text-right text-danger font-weight-bold">{formatMoney(d.real_out)}</td>
                                        <td className="text-right">{formatMoney(d.deposit)}</td>
                                        <td className="text-right">{formatMoney(d.renew)}</td>
                                        <td className="text-right">{formatMoney(d.rental_fees)}</td>
                                      </tr>
                                    ))}
                                  </tbody>
                                </table>
                              ) : (
                                <p className="text-muted mb-0">Không có phát sinh thu chi trong kỳ chọn.</p>
                              )}
                            </div>
                          </td>
                        </tr>
                      )}
                    </React.Fragment>
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
