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
  event_type: string;
  from_status: string | null;
  to_status: string;
  reason: string | null;
  occurred_at: string;
  from_priority: string | null;
  to_priority: string | null;
  from_counter_id: number | null;
  to_counter_id: number | null;
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
  can_check_in: boolean;
  visitors_count: number;
  check_in_method: string | null;
  counter: { id: number; label: string } | null;
  appointment: { id: number; scheduled_for: string; status: string; check_in_token: string } | null;
  branch: { id: number; name: string; address: string };
  service: { id: number; name: string; code: string };
  waiting_since: string | null;
  checked_in_at: string | null;
  cancelled_at: string | null;
  timeline: TicketStatusEvent[];
};

export type Appointment = {
  id: number;
  status: string;
  scheduled_for: string;
  visitors_count: number;
  check_in_token: string;
  ticket: Ticket;
};
