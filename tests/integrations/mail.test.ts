import nodemailer from 'nodemailer';
import { afterEach, describe, expect, it } from 'vitest';
import { emailChannel, useTransport } from '@/server/mail';
import { makeUser } from '../factories';

afterEach(() => useTransport(null));

function capture() {
  const sent: any[] = [];
  const t = nodemailer.createTransport({ jsonTransport: true });
  const original = t.sendMail.bind(t);
  t.sendMail = (async (message: any) => { sent.push(message); return original(message); }) as never;
  useTransport(t);
  return sent;
}

describe('email channel', () => {
  it('emails people who have an address and switched email alerts on', async () => {
    const sent = capture();
    const user = await makeUser({ email: 'a@example.com' });
    await emailChannel({ ...user, emailNotificationsEnabled: true }, { title: 'Hello <b>', body: 'Body', url: '/payments' });
    expect(sent).toHaveLength(1);
    expect(sent[0].to).toBe('a@example.com');
    expect(sent[0].subject).toBe('Hello <b>');
    expect(sent[0].html).toContain('Hello &lt;b&gt;');
  });

  it('stays quiet when the person has no address or turned email off', async () => {
    const sent = capture();
    const user = await makeUser({ email: 'a@example.com' });
    await emailChannel({ ...user, emailNotificationsEnabled: false }, { title: 't', body: 'b', url: null });
    await emailChannel({ ...user, email: null, emailNotificationsEnabled: true }, { title: 't', body: 'b', url: null });
    expect(sent).toHaveLength(0);
  });
});
