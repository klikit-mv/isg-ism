/** @type {import('next').NextConfig} */
const nextConfig = {
  poweredByHeader: false,
  serverExternalPackages: ['mysql2', 'exceljs', 'pdfkit', 'bcryptjs', 'nodemailer'],
  experimental: {
    authInterrupts: true,
    serverActions: { bodySizeLimit: '12mb' },
  },
};

export default nextConfig;
