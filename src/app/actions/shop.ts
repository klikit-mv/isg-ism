'use server';

import { eq } from 'drizzle-orm';
import { forbidden, notFound, redirect } from 'next/navigation';
import { revalidatePath } from 'next/cache';
import { db, schema } from '@/db';
import { echo, handle, simple, type ActionState } from '@/server/action';
import { flash } from '@/server/flash';
import { canActOnPurchase, cancelPurchase, createPurchase, deliverPurchase, findPurchaseByUuid, markReady } from '@/server/purchases';
import { canManageShop, createItem, deleteItem, findItemByUuid, updateItem } from '@/server/shop';
import { requireUser } from '@/server/session';
import { isUpload } from '@/server/uploads';
import { ShopItemStatus } from '@/lib/enums';
import { normalize } from '@/lib/money';
import { inEnum, parseForm, validate } from '@/lib/validate';
import { ScoutError } from '@/server/errors';

const itemRules = (editing: boolean) => ({
  name: ['required', 'string', 'max:255'],
  description: ['nullable', 'string', 'max:2000'],
  price: ['required', 'numeric', 'min:0', 'max:9999999'],
  stock_qty: ['required', 'integer', 'min:0', 'max:1000000'],
  status: [editing ? 'required' : 'nullable', inEnum(ShopItemStatus)],
});

async function manager() {
  const user = await requireUser();
  if (!canManageShop(user)) forbidden();
  return user;
}

export async function saveItemAction(_: ActionState | null, formData: FormData): Promise<ActionState | null> {
  const user = await manager();
  const input = parseForm(formData);
  const item = input.uuid ? await findItemByUuid(String(input.uuid)) : null;
  if (input.uuid && !item) notFound();
  return handle(async () => {
    const data = await validate(input, itemRules(!!item) as never);
    const payload = { name: data.name, description: data.description, price: normalize(data.price), stock_qty: data.stock_qty, status: data.status };
    const image = isUpload(input.image) ? input.image : null;
    if (item) {
      await updateItem(item, payload, image, user);
      await flash('success', `${data.name} was saved.`);
    } else {
      await createItem(payload, image, user);
      await flash('success', `${data.name} was added to the shop.`);
    }
    revalidatePath('/shop');
  }, echo(input));
}

export async function deleteItemAction(formData: FormData): Promise<void> {
  const user = await manager();
  const item = await findItemByUuid(String(formData.get('uuid') ?? ''));
  if (!item) notFound();
  await simple(async () => {
    await deleteItem(item, user);
    await flash('success', 'The item was removed from the shop.');
    revalidatePath('/shop');
  });
}

export async function buyAction(_: ActionState | null, formData: FormData): Promise<ActionState | null> {
  const user = await requireUser();
  const input = parseForm(formData);
  return handle(async () => {
    const data = await validate(input, { item: ['required', 'uuid'], student: ['required', 'uuid'], quantity: ['required', 'integer', 'min:1', 'max:1000'] });
    const item = await findItemByUuid(data.item);
    if (!item) notFound();
    const [student] = await db.select().from(schema.students).where(eq(schema.students.uuid, data.student)).limit(1);
    if (!student) throw new ScoutError('Choose a scout.', 'student');
    await createPurchase(item, student, data.quantity, user);
    await flash('success', 'Your order was placed. Pay for it below to confirm it.');
    redirect('/purchases');
  }, echo(input));
}

async function purchaseFor(formData: FormData, needsShopStaff: boolean) {
  const user = await requireUser();
  const purchase = await findPurchaseByUuid(String(formData.get('uuid') ?? ''));
  if (!purchase) notFound();
  if (!needsShopStaff && !(await canActOnPurchase(user, purchase))) forbidden();
  return { user, purchase };
}

export async function markReadyAction(formData: FormData): Promise<void> {
  const { user, purchase } = await purchaseFor(formData, true);
  await simple(async () => { await markReady(purchase.id, user); await flash('success', 'The purchase is ready for collection.'); revalidatePath('/purchases'); });
}

export async function deliverAction(formData: FormData): Promise<void> {
  const { user, purchase } = await purchaseFor(formData, true);
  await simple(async () => {
    const data = await validate({ recipient: formData.get('recipient') }, { recipient: ['nullable', 'string', 'max:255'] });
    await deliverPurchase(purchase.id, user, data.recipient);
    await flash('success', 'The purchase was delivered.');
    revalidatePath('/purchases');
  });
}

export async function cancelPurchaseAction(formData: FormData): Promise<void> {
  const { user, purchase } = await purchaseFor(formData, false);
  await simple(async () => { await cancelPurchase(purchase.id, user); await flash('success', 'The purchase was cancelled.'); revalidatePath('/purchases'); });
}
