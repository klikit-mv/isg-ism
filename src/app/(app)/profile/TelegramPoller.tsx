'use client';

import { useRouter } from 'next/navigation';
import { useEffect } from 'react';
import { pollTelegram } from './poll';

/** While waiting for the Start message, check every few seconds and refresh when connected. */
export function TelegramPoller() {
  const router = useRouter();
  useEffect(() => {
    let stopped = false;
    const timer = setInterval(async () => {
      if (stopped) return;
      if (await pollTelegram()) {
        stopped = true;
        clearInterval(timer);
        router.refresh();
      }
    }, 4000);
    return () => { stopped = true; clearInterval(timer); };
  }, [router]);
  return <p className="text-xs text-gray-400">Waiting for Telegram…</p>;
}
