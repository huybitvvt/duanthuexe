'use client';

import React, { useState, useEffect } from 'react';
import { AppShell } from '@/components/layout/AppShell';
import { VehicleSummaryPanel, VehicleReports } from '@/components/vehicle/VehicleSummaryPanel';
import { VehicleTable, VehicleItem } from '@/components/vehicle/VehicleTable';
import { VehicleGrid } from '@/components/vehicle/VehicleGrid';
import { VehicleCreateModal } from '@/components/vehicle/VehicleCreateModal';
import api from '@/lib/api-client';

const INITIAL_REPORTS: VehicleReports = {
  total_vehicle: 58,
  total_price: 1450000000,
  total_vehicle_ready: 18,
  total_vehicle_using: 36,
  total_vehicle_ga: 32,
  total_vehicle_so: 20,
  total_vehicle_con: 6,
};

const SAMPLE_VEHICLES: VehicleItem[] = [
  {
    id: 1,
    name: 'Honda Vision 2023 Trắng',
    license: '29B1-987.65',
    store: { id: 1, name: '02 Hàng Bút' },
    type_name: 'Xe ga',
    current_odo: 14200,
    price_daily: 150000,
    status: 2, // Đang thuê
  },
  {
    id: 2,
    name: 'Yamaha Grande 125 Đỏ',
    license: '29H1-543.21',
    store: { id: 2, name: '66 Nguyễn Hoàng' },
    type_name: 'Xe ga',
    current_odo: 8900,
    price_daily: 180000,
    status: 1, // Sẵn sàng
  },
  {
    id: 3,
    name: 'Honda Air Blade 160 Xanh',
    license: '29X5-888.99',
    store: { id: 1, name: '02 Hàng Bút' },
    type_name: 'Xe ga',
    current_odo: 21500,
    price_daily: 220000,
    status: 1, // Sẵn sàng
  },
  {
    id: 4,
    name: 'Honda Wave Alpha 110 Đen',
    license: '29A1-222.33',
    store: { id: 3, name: '476 Quang Trung' },
    type_name: 'Xe số',
    current_odo: 34000,
    price_daily: 100000,
    status: 3, // Bảo dưỡng
  },
  {
    id: 5,
    name: 'Yamaha Exciter 155 VVA',
    license: '29F1-777.88',
    store: { id: 4, name: '91 Trần Quốc Hoàn' },
    type_name: 'Xe côn',
    current_odo: 12000,
    price_daily: 250000,
    status: 2, // Đang thuê
  },
];

export default function VehiclesPage() {
  const [viewMode, setViewMode] = useState<'table' | 'grid'>('table');
  const [vehicles, setVehicles] = useState<VehicleItem[]>(SAMPLE_VEHICLES);
  const [reports, setReports] = useState<VehicleReports>(INITIAL_REPORTS);
  const [loading, setLoading] = useState(false);

  // Filters
  const [keyword, setKeyword] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [storeFilter, setStoreFilter] = useState('');
  const [typeFilter, setTypeFilter] = useState('');

  // Modal
  const [modalOpen, setModalOpen] = useState(false);
  const [editingVehicle, setEditingVehicle] = useState<VehicleItem | null>(null);

  const fetchVehicles = async () => {
    setLoading(true);
    try {
      const res = await api.get('/auth/vehicle/vehicles', {
        params: {
          name: keyword,
          status: statusFilter,
          store_id: storeFilter,
          type: typeFilter,
        },
      });

      if (res.data?.data) {
        setVehicles(res.data.data);
      }
      if (res.data?.reports) {
        setReports(res.data.reports);
      }
    } catch (err) {
      console.warn('Using local vehicle state in test mode');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchVehicles();
  }, [statusFilter, storeFilter, typeFilter]);

  const handleEdit = (v: VehicleItem) => {
    setEditingVehicle(v);
    setModalOpen(true);
  };

  const handleDelete = (id: number) => {
    if (confirm('Bạn có chắc chắn muốn xóa xe này khỏi hệ thống?')) {
      setVehicles(vehicles.filter((v) => v.id !== id));
    }
  };

  return (
    <AppShell>
      <div className="container-fluid py-4">
        {/* Card Header */}
        <div className="card card-custom gutter-b mb-4">
          <div className="card-header align-items-center d-flex justify-content-between flex-wrap py-3">
            <div className="card-title mb-2 mb-sm-0">
              <h3 className="card-label font-weight-bold text-dark mb-0">
                Danh sách xe máy
              </h3>
            </div>
            <div className="card-toolbar d-flex align-items-center">
              <div className="btn-group btn-group-sm mr-3" role="group" aria-label="Chế độ hiển thị">
                <button
                  type="button"
                  className={`btn font-weight-bold ${viewMode === 'table' ? 'btn-primary' : 'btn-secondary'}`}
                  onClick={() => setViewMode('table')}
                >
                  <i className="fas fa-list mr-1" /> Bảng
                </button>
                <button
                  type="button"
                  className={`btn font-weight-bold ${viewMode === 'grid' ? 'btn-primary' : 'btn-secondary'}`}
                  onClick={() => setViewMode('grid')}
                >
                  <i className="fas fa-th mr-1" /> Lưới thẻ
                </button>
              </div>
              <button
                type="button"
                className="btn btn-success"
                onClick={() => {
                  setEditingVehicle(null);
                  setModalOpen(true);
                }}
              >
                <i className="fas fa-plus mr-1" /> Thêm xe mới
              </button>
            </div>
          </div>

          {/* Vehicle Summary Panel */}
          <VehicleSummaryPanel reports={reports} />

          <div className="card-body pt-0 pb-4">
            {/* Filter Row */}
            <div className="row g-2 mb-4">
              <div className="col-12 col-md-3 mb-2">
                <input
                  type="text"
                  className="form-control"
                  placeholder="Tên xe, biển số..."
                  value={keyword}
                  onChange={(e) => setKeyword(e.target.value)}
                  onKeyDown={(e) => e.key === 'Enter' && fetchVehicles()}
                />
              </div>
              <div className="col-12 col-md-3 mb-2">
                <select
                  className="form-control"
                  value={statusFilter}
                  onChange={(e) => setStatusFilter(e.target.value)}
                >
                  <option value="">Tất cả trạng thái</option>
                  <option value="1">Sẵn sàng</option>
                  <option value="2">Đang thuê</option>
                  <option value="3">Bảo dưỡng</option>
                  <option value="4">Ngừng hoạt động</option>
                </select>
              </div>
              <div className="col-12 col-md-3 mb-2">
                <select
                  className="form-control"
                  value={storeFilter}
                  onChange={(e) => setStoreFilter(e.target.value)}
                >
                  <option value="">Tất cả cơ sở</option>
                  <option value="1">02 Hàng Bút</option>
                  <option value="2">66 Nguyễn Hoàng</option>
                  <option value="3">476 Quang Trung</option>
                  <option value="4">91 Trần Quốc Hoàn</option>
                </select>
              </div>
              <div className="col-12 col-md-3 mb-2">
                <select
                  className="form-control"
                  value={typeFilter}
                  onChange={(e) => setTypeFilter(e.target.value)}
                >
                  <option value="">Tất cả loại xe</option>
                  <option value="ga">Xe ga</option>
                  <option value="so">Xe số</option>
                  <option value="con">Xe côn</option>
                </select>
              </div>
            </div>

            {/* View Mode Component */}
            {viewMode === 'table' ? (
              <VehicleTable
                vehicles={vehicles}
                loading={loading}
                onEdit={handleEdit}
                onDelete={handleDelete}
              />
            ) : (
              <VehicleGrid
                vehicles={vehicles}
                loading={loading}
                onEdit={handleEdit}
                onDelete={handleDelete}
              />
            )}
          </div>
        </div>
      </div>

      <VehicleCreateModal
        isOpen={modalOpen}
        editingVehicle={editingVehicle}
        onClose={() => setModalOpen(false)}
        onSuccess={fetchVehicles}
      />
    </AppShell>
  );
}
