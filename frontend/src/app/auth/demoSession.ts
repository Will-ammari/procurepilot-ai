import type { User } from '../../domain/types';
import { users } from '../../services/demo';

/** Only the synthetic user's id and role are stored. No API credentials ever enter Web Storage. */
export const DEMO_SESSION_KEY = 'procurepilot-demo-session-v1';

type Store = Pick<Storage, 'getItem' | 'setItem' | 'removeItem'>;
const getStore = (): Store | undefined => {
  try {
    return typeof window === 'undefined' ? undefined : window.sessionStorage;
  } catch {
    return undefined;
  }
};

export function readDemoSession(store: Store | undefined = getStore()): User | null {
  if (!store) return null;
  try {
    const raw = store.getItem(DEMO_SESSION_KEY);
    if (!raw) return null;
    const record: unknown = JSON.parse(raw);
    if (!record || typeof record !== 'object') return null;
    const data = record as { version?: unknown; id?: unknown; role?: unknown };
    if (data.version !== 1 || !Number.isSafeInteger(data.id)) return null;
    const account = users.find((user) => user.id === data.id && user.role === data.role);
    return account
      ? {
          ...account,
          organization: { id: 1, name: 'Berlin Mittelstand GmbH', currency: 'EUR', country: 'DE' },
        }
      : null;
  } catch {
    return null;
  }
}

export function saveDemoSession(user: User, store: Store | undefined = getStore()): void {
  try {
    store?.setItem(DEMO_SESSION_KEY, JSON.stringify({ version: 1, id: user.id, role: user.role }));
  } catch {
    /* Browser storage can be disabled. Demo still works in memory. */
  }
}

export function clearDemoSession(store: Store | undefined = getStore()): void {
  try {
    store?.removeItem(DEMO_SESSION_KEY);
  } catch {
    /* Failing storage cannot prevent logout. */
  }
}
