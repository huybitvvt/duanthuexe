import type { Metadata } from 'next';
import '../styles/globals.scss';

export const metadata: Metadata = {
  title: 'HIMOTO - Quản lý thuê xe chuyên nghiệp',
  description: 'Hệ thống quản lý cho thuê và kinh doanh xe máy, ô tô chuyên nghiệp HIMOTO',
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="vi">
      <head>
        <meta charSet="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700" />
      </head>
      <body>
        {children}
      </body>
    </html>
  );
}
