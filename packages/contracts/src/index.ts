export interface HealthResponse {
  status: 'ok' | 'error';
  service: string;
  database: 'ok' | 'unavailable';
  commit: string | null;
}

export interface Store {
  id: number;
  name: string;
  address?: string;
  phone?: string;
  status: number;
  created_at?: string;
  updated_at?: string;
}

export interface UserRole {
  id: number;
  name: string;
  title?: string;
}

export interface User {
  id: number;
  name: string;
  email: string;
  phone?: string;
  store_id?: number;
  store?: Store;
  role?: UserRole;
  status: number;
}

export interface Vehicle {
  id: number;
  name: string;
  license_plate: string;
  brand_id?: number;
  category_id?: number;
  store_id: number;
  store?: Store;
  status: number;
  price_daily?: number;
  price_hourly?: number;
  current_odo?: number;
  image?: string;
}

export interface ApiResponse<T = any> {
  status?: boolean | number;
  message?: string;
  data?: T;
  payload?: T;
  error?: string;
}
