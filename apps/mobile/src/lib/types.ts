export type User = { id: number; name: string; email: string; role: string };

export type QueueSnapshot = {
  waiting_count: number;
  active_counters: number;
  estimated_wait_minutes: number | null;
  estimate_explanation: string | null;
  is_open: boolean;
};

export type Service = {
  id: number;
  name: string;
  code: string;
  average_service_minutes: number;
  queue: QueueSnapshot;
};

export type Branch = {
  id: number;
  name: string;
  slug: string;
  address: string;
  phone: string | null;
  timezone: string;
  is_open: boolean;
  services: Service[];
};

export type TicketStatusEvent = {
  id: number;
  from_status: string | null;
  to_status: string;
  reason: string | null;
  occurred_at: string;
};

export type Ticket = {
  id: number;
  number: string;
  status: string;
  priority: string;
  people_ahead: number;
  active_counters: number;
  estimated_wait_minutes: number | null;
  estimate_explanation: string | null;
  can_cancel: boolean;
  branch: { id: number; name: string; address: string };
  service: { id: number; name: string; code: string };
  waiting_since: string;
  cancelled_at: string | null;
  timeline: TicketStatusEvent[];
};
