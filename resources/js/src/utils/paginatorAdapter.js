/**
 * HIMOTO Paginator & Model Adapters
 */

export const VEHICLE_STATUS_MAP = {
  ready: { label: "Sẵn sàng", css: "ready" },
  using: { label: "Đang sử dụng", css: "using" },
  renting: { label: "Đang thuê", css: "renting" },
  repairing: { label: "Đang sửa", css: "repairing" },
  pending: { label: "Chờ xử lý", css: "pending" },
  sold: { label: "Đã bán", css: "sold" },
  broken: { label: "Hỏng hóc", css: "broken" },
  bad_debt: { label: "Nợ xấu", css: "bad_debt" }
};

export function adaptVehicle(v) {
  if (!v) return null;

  const rawStatus = String(v.status || "ready").toLowerCase();
  const statusMeta = VEHICLE_STATUS_MAP[rawStatus] || {
    label: v.status_name || rawStatus,
    css: rawStatus
  };

  const license = v.license || v.license_plate || "";
  const odo = v.odometer != null ? Number(v.odometer) : (v.total_km != null ? Number(v.total_km) : null);
  const storeName = v.store?.store_name || v.store_name || "";

  return {
    ...v,
    id: v.id,
    name: v.name || "HIMOTO Fleet",
    license: license,
    license_plate: license,
    odometer: odo,
    total_km: odo,
    status: rawStatus,
    status_label: statusMeta.label,
    status_css: statusMeta.css,
    store_name: storeName,
    images: Array.isArray(v.images) ? v.images : [],
    cost_price: v.cost_price || 0,
    sale_price: v.sale_price || 0,
    daily_price: v.daily_price || 0
  };
}

export function normalizePaginator(res) {
  if (!res) {
    return { items: [], currentPage: 1, lastPage: 1, total: 0, perPage: 15 };
  }

  // Case 1: res.data is array directly
  if (Array.isArray(res.data)) {
    const p = res.pagination || res.meta || {};
    return {
      items: res.data,
      currentPage: p.current_page || 1,
      lastPage: p.last_page || 1,
      total: p.total != null ? p.total : res.data.length,
      perPage: p.per_page || 15
    };
  }

  // Case 2: res.data.data is array (Laravel Paginator standard)
  if (res.data && Array.isArray(res.data.data)) {
    return {
      items: res.data.data,
      currentPage: res.data.current_page || 1,
      lastPage: res.data.last_page || 1,
      total: res.data.total != null ? res.data.total : res.data.data.length,
      perPage: res.data.per_page || 15
    };
  }

  // Case 3: res itself is array
  if (Array.isArray(res)) {
    return {
      items: res,
      currentPage: 1,
      lastPage: 1,
      total: res.length,
      perPage: res.length || 15
    };
  }

  // Fallback
  return { items: [], currentPage: 1, lastPage: 1, total: 0, perPage: 15 };
}

export default {
  adaptVehicle,
  normalizePaginator,
  VEHICLE_STATUS_MAP
};
