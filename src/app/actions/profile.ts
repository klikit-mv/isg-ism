'use server';

import { eq } from 'drizzle-orm';
import { forbidden, redirect } from 'next/navigation';
import { db, schema } from '@/db';
import { simple } from '@/server/action';
import { recordAudit } from '@/server/audit';
import { ScoutError } from '@/server/errors';
import { flash } from '@/server/flash';
import { requireUser } from '@/server/session';
import { storeSignature } from '@/server/signatures';
import { remove } from '@/server/storage';
import { isUpload, storeImage } from '@/server/uploads';
import { isStaff } from '@/server/users';

/** Upload or remove the signed-in user's profile picture. */
export async function avatarAction(formData: FormData): Promise<void> {
  const user = await requireUser();
  await simple(async () => {
    const old = user.avatarPath;
    if (formData.get('remove') === '1') {
      await db.update(schema.users).set({ avatarPath: null, updatedAt: new Date() }).where(eq(schema.users.id, user.id));
      if (old?.startsWith('avatars/')) await remove('public', old);
      await recordAudit('profile.avatar_removed', { type: 'user', id: user.id }, {}, user.id);
      await flash('success', 'Your profile picture was removed.');
      redirect('/profile');
    }
    const file = formData.get('avatar');
    if (!isUpload(file)) throw new ScoutError('Choose a picture to upload.');
    const path = await storeImage(file, { directory: 'avatars', name: `${user.uuid}-${Date.now().toString(36)}`, field: 'avatar', maxKb: 2048 });
    await db.update(schema.users).set({ avatarPath: path, updatedAt: new Date() }).where(eq(schema.users.id, user.id));
    if (old?.startsWith('avatars/')) await remove('public', old);
    await recordAudit('profile.avatar_updated', { type: 'user', id: user.id }, {}, user.id);
    await flash('success', 'Your profile picture was updated.');
    redirect('/profile');
  });
}

export async function ownSignatureAction(formData: FormData): Promise<void> {
  const user = await requireUser();
  if (!isStaff(user)) forbidden();
  await simple(async () => {
    const file = formData.get('signature');
    if (!isUpload(file)) throw new ScoutError('Choose a signature image to upload.');
    await storeSignature(user, file);
    await recordAudit('user.signature_uploaded', { type: 'user', id: user.id }, {}, user.id);
    await flash('success', 'Your signature was uploaded.');
    redirect('/profile');
  });
}
