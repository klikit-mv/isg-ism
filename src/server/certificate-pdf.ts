import PDFDocument from 'pdfkit';
import { config } from '@/lib/config';
import { get } from './storage';
import { getSetting } from './settings';

export interface CertificateSheet {
  type: 'badge' | 'general' | 'leadership';
  name: string;
  idCardNo: string | null;
  title: string | null;
  badgeName: string | null;
  certNumber: string;
  dateLong: string;
  patrolOrSix?: string | null;
  troopOrGroup?: string | null;
  startDateLong?: string | null;
  verifier?: { name: string; signature: Buffer | null; dateLong: string } | null;
}

const PURPLE = '#6b21a8';
const DEEP = '#3b0764';
const EMERALD = '#10b981';
const DARK_EMERALD = '#065f46';
const GREY = '#6b7280';

const HEADINGS = { badge: 'Certificate of Proficiency', general: 'Certificate', leadership: 'Certificate of Leadership' } as const;

/** The uploaded website logo, when there is one (PNG or JPEG). */
async function logoBytes(): Promise<Buffer | null> {
  const path = await getSetting('site_logo_path');
  return path ? get('public', path) : null;
}

function bodyLines(c: CertificateSheet): { text: string; style: 'lead' | 'name' | 'title' | 'small' }[] {
  switch (c.type) {
    case 'badge':
      return [
        { text: 'This is to certify that', style: 'lead' }, { text: c.name, style: 'name' },
        { text: 'has successfully completed the requirements for the', style: 'lead' }, { text: c.badgeName ?? c.title ?? '', style: 'title' },
        { text: `awarded on ${c.dateLong}`, style: 'lead' },
      ];
    case 'general':
      return [
        { text: 'This certificate is proudly presented to', style: 'lead' }, { text: c.name, style: 'name' },
        ...(c.idCardNo ? [{ text: `ID card no. ${c.idCardNo}`, style: 'small' as const }] : []),
        { text: c.title ?? '', style: 'title' }, { text: `awarded on ${c.dateLong}`, style: 'lead' },
      ];
    case 'leadership':
      return [
        { text: 'This is to certify that', style: 'lead' }, { text: c.name, style: 'name' },
        { text: 'served as leader of', style: 'lead' }, { text: c.patrolOrSix ?? '', style: 'title' },
        { text: `in ${c.troopOrGroup ?? config.organisation} from ${c.startDateLong ?? c.dateLong}`, style: 'lead' },
      ];
  }
}

/** Render a certificate as an A4 landscape PDF. */
export async function renderCertificatePdf(c: CertificateSheet): Promise<Buffer> {
  const logo = await logoBytes();
  const doc = new PDFDocument({ size: 'A4', layout: 'landscape', margin: 0, info: { Title: `${c.certNumber} — ${c.name}`, Author: config.organisation } });
  const chunks: Buffer[] = [];
  doc.on('data', (d: Buffer) => chunks.push(d));
  const done = new Promise<Buffer>((resolve, reject) => {
    doc.on('end', () => resolve(Buffer.concat(chunks)));
    doc.on('error', reject);
  });

  const W = doc.page.width;
  const H = doc.page.height;
  doc.lineWidth(10).strokeColor(PURPLE).rect(18, 18, W - 36, H - 36).stroke();
  doc.lineWidth(2).strokeColor(EMERALD).rect(38, 38, W - 76, H - 76).stroke();

  let y = 58;
  if (logo) {
    try { doc.image(logo, W / 2 - 30, y, { fit: [60, 60], align: 'center' }); } catch { /* unreadable logo: skip it */ }
    y += 66;
  }
  doc.fillColor(PURPLE).font('Helvetica').fontSize(13).text(config.organisation.toUpperCase(), 0, y, { width: W, align: 'center', characterSpacing: 3 });
  y += 26;
  doc.fillColor(DEEP).font('Helvetica-Bold').fontSize(36).text(HEADINGS[c.type], 0, y, { width: W, align: 'center' });
  y += 62;

  for (const line of bodyLines(c)) {
    if (line.style === 'lead') doc.fillColor(DEEP).font('Helvetica').fontSize(15);
    else if (line.style === 'name') doc.fillColor(DARK_EMERALD).font('Helvetica-Bold').fontSize(32);
    else if (line.style === 'title') doc.fillColor(DEEP).font('Helvetica-Bold').fontSize(22);
    else doc.fillColor(GREY).font('Helvetica').fontSize(11);
    doc.text(line.text, 70, y, { width: W - 140, align: 'center' });
    y = doc.y + 8;
  }

  const footerY = H - 120;
  doc.fillColor(GREY).font('Helvetica').fontSize(10).text(`Certificate no. ${c.certNumber}`, 70, footerY + 50, { width: W / 2 - 100, align: 'left' });
  const sigX = W / 2 + 40;
  const sigW = W / 2 - 110;
  if (c.verifier) {
    if (c.verifier.signature) {
      try { doc.image(c.verifier.signature, sigX + sigW / 2 - 70, footerY, { fit: [140, 46], align: 'center' }); } catch { /* unreadable signature */ }
    } else {
      doc.fillColor(DEEP).font('Helvetica-Oblique').fontSize(24).text(c.verifier.name, sigX, footerY + 8, { width: sigW, align: 'center' });
    }
  }
  doc.lineWidth(1).strokeColor(DEEP).moveTo(sigX, footerY + 54).lineTo(sigX + sigW, footerY + 54).stroke();
  doc.fillColor(DEEP).font('Helvetica').fontSize(10)
    .text(c.verifier ? `${c.verifier.name} — verified ${c.verifier.dateLong}` : 'Authorised signature', sigX, footerY + 60, { width: sigW, align: 'center' });

  doc.end();
  return done;
}
