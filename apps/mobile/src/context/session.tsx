import { apiRequest } from '@/lib/api';
import { User } from '@/lib/types';
import { createContext, PropsWithChildren, useContext, useMemo, useState } from 'react';

type SessionContextValue = {
  token: string | null;
  user: User | null;
  signIn: (email: string, password: string) => Promise<void>;
  signOut: () => Promise<void>;
};

const SessionContext = createContext<SessionContextValue | null>(null);

export function SessionProvider({ children }: PropsWithChildren) {
  const [token, setToken] = useState<string | null>(null);
  const [user, setUser] = useState<User | null>(null);

  const value = useMemo<SessionContextValue>(() => ({
    token,
    user,
    signIn: async (email, password) => {
      const response = await apiRequest<{ data: { token: string; user: User } }>('/auth/login', null, {
        method: 'POST',
        body: JSON.stringify({ email, password, device_name: 'QueueCare mobile' }),
      });
      setToken(response.data.token);
      setUser(response.data.user);
    },
    signOut: async () => {
      if (token) {
        await apiRequest('/auth/logout', token, { method: 'POST' }).catch(() => undefined);
      }
      setToken(null);
      setUser(null);
    },
  }), [token, user]);

  return <SessionContext.Provider value={value}>{children}</SessionContext.Provider>;
}

export function useSession(): SessionContextValue {
  const session = useContext(SessionContext);
  if (!session) throw new Error('useSession must be used inside SessionProvider.');
  return session;
}
