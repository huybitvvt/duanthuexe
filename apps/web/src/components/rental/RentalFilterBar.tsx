'use client';

import React from 'react';
import { ORDER_STATUS } from '@/lib/formatters';

export interface RentalQuery {
  today_filter?: string;
  keyword?: string;
  store_id?: string;
  order_status?: string;
  start_date?: string;
  end_date?: string;
  page?: number;
  limit?: number;
}

interface RentalFilterBarProps {
  query: RentalQuery;
  onChange: (newQuery: RentalQuery) => void;
  onSearch: () => void;
  onReset: () => void;
  stores?: { id: number | string; name: string }[];
}

export const RentalFilterBar: React.FC<RentalFilterBarProps> = ({
  query,
  onChange,
  onSearch,
  onReset,
  stores = [
    { id: '1', name: '02 Hàng Bút' },
    { id: '2', name: '66 Nguyễn Hoàng' },
    { id: '3', name: '476 Quang Trung' },
    { id: '4', name: '91 Trần Quốc Hoàn' },
  ],
}) => {
  const setTodayFilter = (filter: string) => {
    onChange({ ...query, today_filter: filter, page: 1 });
    onSearch();
  };

  return (
    <div className="filter-box mb-4">
      {/* Tabs Lọc Đơn Trong Ngày */}
      <div className="today-filter-tabs d-flex flex-wrap align-items-center mb-3">
        <span className="font-weight-bold text-muted mr-3 font-size-sm">ĐƠN TRONG NGÀY:</span>
        <button
          type="button"
          className={`btn btn-sm mr-2 mb-1 ${!query.today_filter ? 'btn-primary' : 'btn-light'}`}
          onClick={() => setTodayFilter('')}
        >
          Tất cả
        </button>
        <button
          type="button"
          className={`btn btn-sm mr-2 mb-1 font-weight-bold ${query.today_filter === 'created_today' ? 'btn-primary' : 'btn-light-primary'}`}
          onClick={() => setTodayFilter('created_today')}
        >
          Tạo hôm nay
        </button>
        <button
          type="button"
          className={`btn btn-sm mr-2 mb-1 font-weight-bold ${query.today_filter === 'pickup_today' ? 'btn-success' : 'btn-light-success'}`}
          onClick={() => setTodayFilter('pickup_today')}
        >
          Nhận xe hôm nay
        </button>
        <button
          type="button"
          className={`btn btn-sm mr-2 mb-1 font-weight-bold ${query.today_filter === 'return_today' ? 'btn-warning' : 'btn-light-warning'}`}
          onClick={() => setTodayFilter('return_today')}
        >
          Hẹn trả hôm nay
        </button>
        <button
          type="button"
          className={`btn btn-sm mr-2 mb-1 font-weight-bold ${query.today_filter === 'transaction_today' ? 'btn-info' : 'btn-light-info'}`}
          onClick={() => setTodayFilter('transaction_today')}
        >
          Giao dịch hôm nay
        </button>
      </div>

      {/* Grid Inputs */}
      <div className="row g-2">
        <div className="col-12 col-md-3 mb-2">
          <input
            type="text"
            className="form-control"
            placeholder="#ID, Số HĐ, tên, SĐT, xe, biển số"
            value={query.keyword || ''}
            onChange={(e) => onChange({ ...query, keyword: e.target.value })}
            onKeyDown={(e) => e.key === 'Enter' && onSearch()}
          />
        </div>

        <div className="col-12 col-md-2 mb-2">
          <select
            className="form-control"
            value={query.store_id || ''}
            onChange={(e) => onChange({ ...query, store_id: e.target.value })}
          >
            <option value="">Tất cả chi nhánh</option>
            {stores.map((st) => (
              <option key={st.id} value={st.id}>
                {st.name}
              </option>
            ))}
          </select>
        </div>

        <div className="col-12 col-md-2 mb-2">
          <select
            className="form-control"
            value={query.order_status || ''}
            onChange={(e) => onChange({ ...query, order_status: e.target.value })}
          >
            <option value="">Tất cả trạng thái</option>
            {ORDER_STATUS.map((s) => (
              <option key={s.value} value={s.value}>
                {s.label}
              </option>
            ))}
          </select>
        </div>

        <div className="col-12 col-md-2 mb-2">
          <input
            type="date"
            className="form-control"
            placeholder="Từ ngày"
            value={query.start_date || ''}
            onChange={(e) => onChange({ ...query, start_date: e.target.value })}
          />
        </div>

        <div className="col-12 col-md-2 mb-2">
          <input
            type="date"
            className="form-control"
            placeholder="Đến ngày"
            value={query.end_date || ''}
            onChange={(e) => onChange({ ...query, end_date: e.target.value })}
          />
        </div>

        <div className="col-12 col-md-1 mb-2 d-flex gap-1">
          <button type="button" className="btn btn-primary w-100 mr-1" onClick={onSearch}>
            <i className="fas fa-search" />
          </button>
          <button type="button" className="btn btn-secondary" onClick={onReset} title="Làm mới bộ lọc">
            <i className="fas fa-undo" />
          </button>
        </div>
      </div>
    </div>
  );
};
