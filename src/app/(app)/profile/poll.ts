'use server';

import { confirmTelegram } from '@/app/actions/telegram';

export async function pollTelegram(): Promise<boolean> {
  return confirmTelegram();
}
