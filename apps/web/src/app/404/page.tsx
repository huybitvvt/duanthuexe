'use client';

import React from 'react';
import Link from 'next/link';

export default function Error404Page() {
  return (
    <div className="d-flex flex-column flex-root min-vh-100 bg-light p-10 p-sm-30 justify-content-center">
      <div className="container text-center max-w-600px">
        <h1
          className="font-weight-boldest text-dark"
          style={{ fontSize: '140px', lineHeight: 1 }}
        >
          404
        </h1>
        <p className="font-size-h2 font-weight-bolder text-dark mt-4">
          OOPS! Something went wrong here
        </p>
        <p className="font-size-h4 font-weight-normal text-muted mb-8">
          Trang bạn yêu cầu không tồn tại hoặc đã được di chuyển.
        </p>
        <div>
          <Link href="/dashboard" className="btn btn-primary font-weight-bold px-8 py-3">
            Quay lại Trang chủ
          </Link>
        </div>
      </div>
    </div>
  );
}
