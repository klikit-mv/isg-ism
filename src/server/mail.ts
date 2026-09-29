import nodemailer, { type Transporter } from 'nodemailer';
import { config } from '@/lib/config';
import type { AlertData } from './notifications';
import type { AuthUser } from './users';

/** SMTP settings use the same names as the old portal's .env, so existing values carry over. */
export const mailConfigured = () => !!process.env.MAIL_HOST;

let transport: Transporter | null = null;
/** Replace the SMTP connection (tests). */
export const useTransport = (t: Transporter | null) => { transport = t; };

function smtp(): Transporter {
  return (transport ??= nodemailer.createTransport({
    host: process.env.MAIL_HOST,
    port: Number(process.env.MAIL_PORT ?? 587),
    secure: (process.env.MAIL_ENCRYPTION ?? '').toLowerCase() === 'ssl' || Number(process.env.MAIL_PORT) === 465,
    auth: process.env.MAIL_USERNAME ? { user: process.env.MAIL_USERNAME, pass: process.env.MAIL_PASSWORD ?? '' } : undefined,
  }));
}

const escapeHtml = (s: string) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

export async function sendMail(to: string, subject: string, text: string, html?: string): Promise<void> {
  await smtp().sendMail({
    from: { name: process.env.MAIL_FROM_NAME || config.shortName, address: process.env.MAIL_FROM_ADDRESS || 'no-reply@localhost' },
    to, subject, text, html,
  });
}

/** Notification channel: email for people who added an address and switched email alerts on. */
export async function emailChannel(user: AuthUser, alert: AlertData): Promise<void> {
  if ((!mailConfigured() && !transport) || !user.email || !user.emailNotificationsEnabled) return;
  const base = process.env.APP_URL?.replace(/\/$/, '');
  const link = alert.url && base ? `${base}${alert.url}` : null;
  await sendMail(
    user.email,
    alert.title,
    `${alert.body}${link ? `\n\n${link}` : ''}\n\n— ${config.shortName}`,
    `<p><strong>${escapeHtml(alert.title)}</strong></p><p>${escapeHtml(alert.body)}</p>${link ? `<p><a href="${escapeHtml(link)}">Open in the portal</a></p>` : ''}<p style="color:#6b7280">${escapeHtml(config.shortName)}</p>`,
  );
}
