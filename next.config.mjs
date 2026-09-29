/** @type {import('next').NextConfig} */
const nextConfig = {
  poweredByHeader: false,
  // Smaller runtime for shared hosting: `node .next/standalone/server.js`.
  output: 'standalone',
  serverExternalPackages: ['mysql2', 'exceljs', 'pdfkit', 'bcryptjs'],
  experimental: {
    authInterrupts: true,
    serverActions: { bodySizeLimit: '12mb' },
  },
};

export default nextConfig;
