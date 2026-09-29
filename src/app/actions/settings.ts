'use server';

import { redirect } from 'next/navigation';
import { echo, handle, type ActionState } from '@/server/action';
import { recordAudit } from '@/server/audit';
import { removeLogo, saveLogo } from '@/server/branding';
import { flash } from '@/server/flash';
import { requireAdmin } from '@/server/session';
import { setSetting } from '@/server/settings';
import { isUpload } from '@/server/uploads';
import { normalize } from '@/lib/money';
import { parseForm, validate } from '@/lib/validate';

export async function saveSettingsAction(_: ActionState | null, formData: FormData): Promise<ActionState | null> {
  const actor = await requireAdmin();
  const input = parseForm(formData);
  return handle(async () => {
    const data = await validate(input, {
      default_class_fee: ['required', 'numeric', 'min:0', 'max:9999999'],
      bank_name: ['nullable', 'string', 'max:255'],
      account_name: ['nullable', 'string', 'max:255'],
      account_number: ['nullable', 'string', 'max:100'],
      payment_instructions: ['nullable', 'string', 'max:2000'],
      shop_enabled: ['sometimes', 'boolean'],
      proof_max_kb: ['required', 'integer', 'min:100', 'max:20480'],
      footer_text: ['nullable', 'string', 'max:255'],
      remove_logo: ['sometimes', 'boolean'],
    });

    const messages: string[] = [];
    if (isUpload(input.logo)) messages.push(await saveLogo(input.logo, actor.id));
    else if (data.remove_logo) {
      const removed = await removeLogo(actor.id);
      if (removed) messages.push(removed);
    }

    for (const key of ['bank_name', 'account_name', 'account_number', 'payment_instructions', 'footer_text']) {
      await setSetting(key, data[key] ?? null, actor.id);
    }
    await setSetting('default_class_fee', normalize(data.default_class_fee), actor.id);
    await setSetting('shop_enabled', data.shop_enabled ? '1' : '0', actor.id);
    await setSetting('proof_max_kb', String(data.proof_max_kb), actor.id);

    await recordAudit('settings.updated', null, { keys: Object.keys(data) }, actor.id);
    await flash('success', `Settings saved. ${messages.join(' ')}`.trim());
    redirect('/settings');
  }, echo(input));
}
