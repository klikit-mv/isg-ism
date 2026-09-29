import { PageHeader } from '@/components/PageHeader';
import { getSetting, logoPath, logoUrl, shopEnabled, proofMaxKb, proofMimes, defaultClassFee, footerText } from '@/server/settings';
import { SettingsForm } from './SettingsForm';

export const metadata = { title: 'Settings' };

export default async function SettingsPage() {
  const [logo, hasLogo, shop, maxKb, mimes, fee, footer, bank, accountName, accountNumber, instructions] = await Promise.all([
    logoUrl(), logoPath(), shopEnabled(), proofMaxKb(), proofMimes(), defaultClassFee(), footerText(),
    getSetting('bank_name'), getSetting('account_name'), getSetting('account_number'), getSetting('payment_instructions'),
  ]);
  return (
    <>
      <PageHeader title="Settings" description="Fees, payments, shop, storage and notifications." />
      <SettingsForm values={{ fee, bank, accountName, accountNumber, instructions, maxKb, mimes: mimes.join(', ').toUpperCase(), shop, footer, hasLogo: !!hasLogo, logo }} />
    </>
  );
}
