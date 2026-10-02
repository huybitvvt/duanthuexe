'use client';

import React, { useState } from 'react';
import { useRouter } from 'next/navigation';
import api from '@/lib/api-client';

export default function LoginPage() {
  const router = useRouter();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [generalError, setGeneralError] = useState('');
  const [heroAvailable, setHeroAvailable] = useState(true);

  const handleSignin = async (e: React.FormEvent) => {
    e.preventDefault();
    setGeneralError('');

    if (!email || !password) {
      setGeneralError('Vui lòng nhập đầy đủ email và mật khẩu.');
      return;
    }

    setLoading(true);
    try {
      const res = await api.post('/auth/login', {
        email,
        password,
      });

      const token = res.data?.token || res.data?.data?.token || res.data?.access_token;
      if (token) {
        localStorage.setItem('token', token);
        if (res.data?.user) {
          localStorage.setItem('user', JSON.stringify(res.data.user));
        }
        router.push('/dashboard');
      } else {
        setGeneralError('Không nhận được mã xác thực hợp lệ từ máy chủ.');
      }
    } catch (err: any) {
      const msg = err.response?.data?.message || err.response?.data?.error || 'Đăng nhập không thành công. Vui lòng kiểm tra lại tài khoản hoặc mật khẩu.';
      setGeneralError(msg);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="himoto-auth">
      <aside className="himoto-auth-brand" aria-label="Thuê xe máy du lịch cùng HiMOTO">
        {heroAvailable ? (
          <picture>
            <source srcSet="/images/branding/himoto-journey.webp" type="image/webp" />
            <img
              className="himoto-hero-image"
              src="/images/branding/himoto-journey.png"
              alt="Hai xe máy trên cung đường ven biển Việt Nam"
              loading="eager"
              width={1122}
              height={1402}
              onError={() => setHeroAvailable(false)}
            />
          </picture>
        ) : (
          <div className="himoto-hero-copy">
            <img src="/images/branding/logo-himoto-pdf.png" alt="HiMOTO" />
            <h1>Khởi đầu hành trình.<br />Khám phá tự do.</h1>
            <p>Thuê xe máy du lịch cùng HiMOTO.</p>
          </div>
        )}
      </aside>

      <div className="himoto-auth-main">
        <div className="himoto-language" aria-label="Ngôn ngữ hiện tại: Tiếng Việt">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" aria-hidden="true" width="18" height="18">
            <circle cx="12" cy="12" r="9" />
            <ellipse cx="12" cy="12" rx="4" ry="9" />
            <path d="M3 12h18M5 7h14M5 17h14" />
          </svg>
          <span>Tiếng Việt</span>
        </div>

        <div className="himoto-mobile-header">
          <img
            src="/images/branding/logo-himoto-pdf.png"
            alt="HIMOTO Logo"
            className="himoto-mobile-logo"
          />
        </div>

        <div className="himoto-auth-card">
          <div className="himoto-form-header">
            <span className="himoto-form-badge">QUẢN LÝ THUÊ XE HIMOTO</span>
            <h2 className="himoto-form-title">Chào mừng trở lại</h2>
            <p className="himoto-form-desc">Đăng nhập để quản lý xe và đơn thuê.</p>
          </div>

          {generalError && (
            <div
              className="himoto-alert"
              role="alert"
              aria-live="assertive"
              id="himoto-general-alert"
            >
              <span className="himoto-alert-icon" aria-hidden="true">Lỗi</span>
              <span>{generalError}</span>
            </div>
          )}

          <form onSubmit={handleSignin} noValidate id="himoto_login_form">
            <div className="himoto-field-group mb-3">
              <label className="himoto-field-label" htmlFor="himoto_email_input">
                Email hoặc Tên đăng nhập
              </label>
              <input
                type="text"
                id="himoto_email_input"
                className="himoto-input"
                placeholder="Nhập email của bạn"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                autoComplete="username"
                required
              />
            </div>

            <div className="himoto-field-group mb-4">
              <label className="himoto-field-label" htmlFor="himoto_password_input">
                Mật khẩu
              </label>
              <input
                type="password"
                id="himoto_password_input"
                className="himoto-input"
                placeholder="Nhập mật khẩu"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                autoComplete="current-password"
                required
              />
            </div>

            <button
              type="submit"
              id="himoto_login_submit_btn"
              className="himoto-submit-btn w-100"
              disabled={loading}
            >
              {loading ? 'Đang xác thực...' : 'Đăng nhập'}
            </button>
          </form>
        </div>
      </div>
    </div>
  );
}
