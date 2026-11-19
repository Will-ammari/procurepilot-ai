import { describe, expect, it } from 'vitest';
import {
  DEMO_SESSION_KEY,
  readDemoSession,
  saveDemoSession,
  clearDemoSession,
} from './demoSession';
import { users } from '../../services/demo';
function makeStore() {
  const values = new Map<string, string>();
  return {
    getItem: (k: string) => values.get(k) ?? null,
    setItem: (k: string, v: string) => {
      values.set(k, v);
    },
    removeItem: (k: string) => {
      values.delete(k);
    },
  };
}
describe('demo-only role session', () => {
  it('restores an approved synthetic role without storing a password or token', () => {
    const store = makeStore();
    saveDemoSession(users[3], store);
    expect(store.getItem(DEMO_SESSION_KEY)).toBe(
      JSON.stringify({ version: 1, id: 4, role: 'manager' }),
    );
    expect(readDemoSession(store)?.role).toBe('manager');
  });
  it('rejects tampered or unknown user identities', () => {
    const store = makeStore();
    store.setItem(DEMO_SESSION_KEY, JSON.stringify({ version: 1, id: 1, role: 'finance' }));
    expect(readDemoSession(store)).toBeNull();
    store.setItem(DEMO_SESSION_KEY, JSON.stringify({ version: 1, id: 999, role: 'admin' }));
    expect(readDemoSession(store)).toBeNull();
  });
  it('handles malformed or unavailable storage and clears on logout', () => {
    const store = makeStore();
    store.setItem(DEMO_SESSION_KEY, '{bad');
    expect(readDemoSession(store)).toBeNull();
    saveDemoSession(users[0], store);
    clearDemoSession(store);
    expect(readDemoSession(store)).toBeNull();
    expect(readDemoSession(undefined)).toBeNull();
  });
});
