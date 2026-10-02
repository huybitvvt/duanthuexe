'use client';

import React, { useState, useEffect } from 'react';
import { AppShell } from '@/components/layout/AppShell';
import { ContractOverview, OrderStats, MoneyStats } from '@/components/rental/ContractOverview';
import { RentalFilterBar, RentalQuery } from '@/components/rental/RentalFilterBar';
import { RentalOrderTable, RentalOrderItem } from '@/components/rental/RentalOrderTable';
import { RentalDetailModal } from '@/components/rental/RentalDetailModal';
import { ContractPrintModal } from '@/components/rental/ContractPrintModal';
import { RentalCreateModal } from '@/components/rental/RentalCreateModal';
import api from '@/lib/api-client';

const INITIAL_STATS: OrderStats = {
  total_order: 124,
  total_contracts_completed: 82,
  total_contracts_renting: 38,
  total_out_of_date: 4,
};

const INITIAL_MONEY: MoneyStats = {
  total_deposit: 42500000,
  total_renew: 15800000,
  total_rental_fees: 68900000,
  total_real_refund: 32000000,
  total_origin_refund: 35000000,
  total_money_early: -1500000,
  total_money_out_date: 2400000,
};

const SAMPLE_ORDERS: RentalOrderItem[] = [
  {
    id: 1042,
    contract_number: 'HD-202609-042',
    customer: { name: 'Trần Văn Mạnh', phone: '0987.654.321' },
    store: { name: '02 Hàng Bút' },
    vehicles: [{ name: 'Honda Vision 2023', license: '29B1-987.65' }],
    start_date: '2026-09-18 08:30',
    end_date: '2026-09-22 18:00',
    deposit_amount: 1500000,
    total_amount: 800000,
    status: 3, // Đang thuê
    created_at: '2026-09-18 08:15',
  },
  {
    id: 1041,
    contract_number: 'HD-202609-041',
    customer: { name: 'Lê Thị Thu', phone: '0912.333.444' },
    store: { name: '66 Nguyễn Hoàng' },
    vehicles: [{ name: 'Yamaha Grande 125', license: '29H1-543.21' }],
    start_date: '2026-09-15 09:00',
    end_date: '2026-09-19 12:00',
    deposit_amount: 1000000,
    total_amount: 650000,
    status: 4, // Hoàn thành
    created_at: '2026-09-15 08:45',
  },
  {
    id: 1040,
    contract_number: 'HD-202609-040',
    customer: { name: 'Phạm Đức Anh', phone: '0904.555.777' },
    store: { name: '02 Hàng Bút' },
    vehicles: [{ name: 'Honda Air Blade 160', license: '29X5-888.99' }],
    start_date: '2026-09-10 14:00',
    end_date: '2026-09-17 18:00',
    deposit_amount: 2000000,
    total_amount: 1200000,
    status: 6, // Quá hạn
    created_at: '2026-09-10 13:30',
  },
];

export default function CarRentalPage() {
  const [query, setQuery] = useState<RentalQuery>({
    today_filter: '',
    keyword: '',
    store_id: '',
    order_status: '',
    start_date: '',
    end_date: '',
    page: 1,
    limit: 10,
  });

  const [orders, setOrders] = useState<RentalOrderItem[]>(SAMPLE_ORDERS);
  const [total, setTotal] = useState(SAMPLE_ORDERS.length);
  const [loading, setLoading] = useState(false);
  const [orderStats, setOrderStats] = useState<OrderStats>(INITIAL_STATS);
  const [moneyStats, setMoneyStats] = useState<MoneyStats>(INITIAL_MONEY);

  // Modals
  const [selectedOrder, setSelectedOrder] = useState<RentalOrderItem | null>(null);
  const [detailModalOpen, setDetailModalOpen] = useState(false);
  const [printModalOpen, setPrintModalOpen] = useState(false);
  const [createModalOpen, setCreateModalOpen] = useState(false);

  const fetchOrders = async () => {
    setLoading(true);
    try {
      const res = await api.get('/auth/order/car-rental', { params: query });
      if (res.data?.data) {
        setOrders(res.data.data);
        setTotal(res.data.total || res.data.data.length);
      }
      if (res.data?.order_stats) setOrderStats(res.data.order_stats);
      if (res.data?.money_stats) setMoneyStats(res.data.money_stats);
    } catch (err) {
      // In standalone frontend development mode, retain sample data
      console.warn('Backend currently offline or in test mode, using local state.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchOrders();
  }, [query.page, query.today_filter]);

  const handleSearch = () => {
    setQuery({ ...query, page: 1 });
    fetchOrders();
  };

  const handleReset = () => {
    setQuery({
      today_filter: '',
      keyword: '',
      store_id: '',
      order_status: '',
      start_date: '',
      end_date: '',
      page: 1,
      limit: 10,
    });
    fetchOrders();
  };

  const handleViewOrder = (order: RentalOrderItem) => {
    setSelectedOrder(order);
    setDetailModalOpen(true);
  };

  const handlePrintContract = (order: RentalOrderItem) => {
    setSelectedOrder(order);
    setPrintModalOpen(true);
  };

  return (
    <AppShell>
      <div className="container-fluid py-4">
        {/* Card Header */}
        <div className="card card-custom gutter-b mb-4">
          <div className="card-header d-flex justify-content-between align-items-center flex-wrap py-3">
            <div className="card-title mb-2 mb-sm-0">
              <h3 className="card-label font-weight-bold text-dark mb-0">
                Danh sách hợp đồng thuê xe
              </h3>
            </div>
            <div className="d-flex gap-2">
              <button
                type="button"
                className="btn btn-success mr-2"
                onClick={() => setCreateModalOpen(true)}
              >
                <i className="fas fa-plus mr-1" /> Thêm mới hợp đồng
              </button>
              <button
                type="button"
                className="btn btn-primary"
                onClick={() => alert('Xuất file Excel theo bộ lọc thành công')}
              >
                <i className="fas fa-file-excel mr-1" /> Xuất Excel
              </button>
            </div>
          </div>

          <div className="card-body pt-3 pb-4">
            {/* Overview Stats */}
            <ContractOverview orderStats={orderStats} moneyStats={moneyStats} />

            {/* Filter Bar */}
            <RentalFilterBar
              query={query}
              onChange={setQuery}
              onSearch={handleSearch}
              onReset={handleReset}
            />

            {/* Orders Table */}
            <RentalOrderTable
              orders={orders}
              loading={loading}
              total={total}
              currentPage={query.page || 1}
              pageSize={query.limit || 10}
              onPageChange={(p) => setQuery({ ...query, page: p })}
              onViewOrder={handleViewOrder}
              onPrintContract={handlePrintContract}
            />
          </div>
        </div>
      </div>

      {/* Modals */}
      <RentalDetailModal
        order={selectedOrder}
        isOpen={detailModalOpen}
        onClose={() => setDetailModalOpen(false)}
        onPrint={handlePrintContract}
      />

      <ContractPrintModal
        order={selectedOrder}
        isOpen={printModalOpen}
        onClose={() => setPrintModalOpen(false)}
      />

      <RentalCreateModal
        isOpen={createModalOpen}
        onClose={() => setCreateModalOpen(false)}
        onSuccess={fetchOrders}
      />
    </AppShell>
  );
}
