import { createContext, useContext, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import type { Role, User } from '../../domain/types';
import { repo } from '../../services/repository';
import { clearDemoSession, readDemoSession, saveDemoSession } from './demoSession';

type LoginOptions = { role?: Role; email?: string; password?: string };
type AuthValue = {
  user: User | null;
  login: (options: LoginOptions) => Promise<void>;
  logout: () => Promise<void>;
};
const AuthContext = createContext<AuthValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  // Restore synthetic identity only. API bearer tokens remain exclusively in memory.
  const [user, setUser] = useState<User | null>(() =>
    repo.mode === 'demo' ? readDemoSession() : null,
  );
  const queryClient = useQueryClient();
  const value = useMemo<AuthValue>(
    () => ({
      user,
      login: async (options) => {
        const next = await repo.login(options);
        queryClient.clear();
        if (repo.mode === 'demo') saveDemoSession(next);
        setUser(next);
      },
      logout: async () => {
        try {
          await repo.logout();
        } finally {
          if (repo.mode === 'demo') clearDemoSession();
          setUser(null);
          queryClient.clear();
        }
      },
    }),
    [user, queryClient],
  );
  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used inside AuthProvider');
  return { ...ctx, user: ctx.user! };
}
