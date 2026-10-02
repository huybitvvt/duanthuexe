'use client';

import React from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';

export default function Error403Page() {
  const router = useRouter();

  return (
    <div className="d-flex flex-column flex-root min-vh-100 bg-light p-10 p-sm-30 justify-content-center">
      <div className="container text-center max-w-600px">
        <h1
          className="font-weight-boldest text-danger"
          style={{ fontSize: '120px', lineHeight: 1 }}
        >
          403
        </h1>
        <p className="font-size-h2 font-weight-bolder text-dark mt-4">
          Truy cập bị từ chối (Forbidden)
        </p>
        <p className="font-size-h4 font-weight-normal text-muted mb-8">
          Bạn không có quyền truy cập chức năng này hoặc dữ liệu thuộc cơ sở khác ngoài phạm vi được phân công.
        </p>
        <div>
          <Link href="/dashboard" className="btn btn-primary font-weight-bold px-8 py-3 mr-3">
            Quay lại Trang chủ
          </Link>
          <button
            type="button"
            onClick={() => router.back()}
            className="btn btn-secondary font-weight-bold px-8 py-3"
          >
            Quay lại Trang trước
          </button>
        </div>
      </div>
    </div>
  );
}
