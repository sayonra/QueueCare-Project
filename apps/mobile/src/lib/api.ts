const rawApi = process.env.EXPO_PUBLIC_API_BASE_URL ?? 'http://127.0.0.1:8000';
export const API_URL = rawApi.endsWith('/api/v1')
  ? rawApi
  : `${rawApi.replace(/\/$/, '')}/api/v1`;

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
  ) {
    super(message);
  }
}

export async function apiRequest<T>(path: string, token?: string | null, options: RequestInit = {}): Promise<T> {
  const response = await fetch(`${API_URL}${path}`, {
    ...options,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...options.headers,
    },
  });
  const body = response.status === 204 ? null : await response.json();

  if (!response.ok) {
    const details = body?.error?.details as Record<string, string[]> | undefined;
    const detailMessage = details ? Object.values(details).flat().join(' ') : undefined;
    throw new ApiError(detailMessage || body?.error?.message || 'The request could not be completed.', response.status);
  }

  return body as T;
}
