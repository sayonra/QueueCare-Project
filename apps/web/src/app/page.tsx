"use client";

import { FormEvent, useCallback, useEffect, useMemo, useState } from "react";

type Service = { id: number; name: string; code: string; average_service_minutes: number; is_active: boolean };
type Counter = { id: number; label: string; is_active: boolean; is_paused: boolean; services: Service[]; branch?: { id: number; name: string } };
type User = { id: number; name: string; email: string; role: string; suspended_at?: string | null };
type Ticket = { id: number; number: string; status: string; priority: string; people_ahead: number; estimated_wait_minutes: number | null; service: { id: number; name: string; code: string }; counter: { id: number; label: string } | null };
type CounterSnapshot = Counter & { branch: { id: number; name: string }; current_ticket: Ticket | null; waiting_tickets: Ticket[]; skipped_tickets: Ticket[]; waiting_count: number; transfer_targets: { id: number; label: string }[]; refreshed_at: string };
type Dashboard = { waiting_now: number; active_counters: number; served_today: number; skipped_today: number; cancelled_today: number; live_activity: { number: string; status: string; service: string; counter: string | null; updated_at: string }[]; waiting_tickets: { id: number; number: string; priority: string; service: string }[]; refreshed_at: string };
type Report = {
  branch: { id: number; name: string; timezone: string }; range: { from: string; to: string };
  summary: { total_tickets: number; served: number; cancelled: number; skipped: number; average_wait_minutes: number; average_service_minutes: number; cancellation_rate: number; skip_rate: number };
  daily: { date: string; tickets: number; served: number }[]; peak_hours: { hour: number; tickets: number }[];
  services: { service: string; code: string; tickets: number; served: number; average_wait_minutes: number; average_service_minutes: number }[];
  staff: { name: string; served: number; average_service_minutes: number }[];
  branch_comparison: { branch: string; tickets: number; served: number; average_wait_minutes: number; average_service_minutes: number; cancellation_rate: number }[];
  generated_at: string;
};
type AdminUser = User & { created_at: string; last_active_at: string | null; assignments: { branch: string | null; counter: string | null }[] };
type Activity = { id: number; action: string; description: string; actor: { id: number; name: string } | null; subject: { type: string; id: number | null }; metadata: Record<string, unknown> | null; ip_address: string | null; occurred_at: string };
type AdminOverview = { branches: number; active_branches: number; active_counters: number; users: number; suspended_users: number; users_by_role: { customers: number; counter_staff: number; branch_managers: number; super_admins: number }; served_today: number; recent_activity: Activity[]; refreshed_at: string };
type Hour = { id: number; day_of_week: number; opens_at: string | null; closes_at: string | null; is_closed: boolean };
type Assignment = { id: number; user: { name: string; email: string; role: string }; counter: Counter | null };
type Branch = {
  id: number; name: string; slug: string; timezone: string; address: string; phone: string | null; is_active: boolean;
  services: Service[]; counters: Counter[]; operating_hours: Hour[]; staff_assignments: Assignment[];
};

const rawApi = process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://127.0.0.1:8000/api/v1";
const API = rawApi.endsWith("/api/v1") ? rawApi : `${rawApi.replace(/\/$/, "")}/api/v1`;
const days = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
const copy = {
  en: { workspace: "Workspace", dashboard: "Live dashboard", setup: "Branch setup", reports: "Reports", platform: "Platform admin", operations: "Live operations", configuration: "Branch configuration", insights: "Reports & insights", governance: "Platform governance", signOut: "Sign out", manager: "Manager workspace" },
  km: { workspace: "កន្លែងធ្វើការ", dashboard: "ផ្ទាំងទិន្នន័យផ្ទាល់", setup: "រៀបចំសាខា", reports: "របាយការណ៍", platform: "គ្រប់គ្រងប្រព័ន្ធ", operations: "ប្រតិបត្តិការផ្ទាល់", configuration: "ការកំណត់សាខា", insights: "របាយការណ៍ និងទិន្នន័យ", governance: "អភិបាលកិច្ចប្រព័ន្ធ", signOut: "ចាកចេញ", manager: "កន្លែងធ្វើការអ្នកគ្រប់គ្រង" },
};

async function apiRequest<T>(path: string, token: string, options: RequestInit = {}): Promise<T> {
  const response = await fetch(`${API}${path}`, {
    ...options,
    headers: { Accept: "application/json", "Content-Type": "application/json", Authorization: `Bearer ${token}`, ...options.headers },
  });
  const body = response.status === 204 ? null : await response.json();
  if (!response.ok) throw new Error(body?.error?.details ? Object.values(body.error.details).flat().join(" ") : body?.error?.message ?? "Request failed.");
  return body as T;
}

function Brand() {
  return <div className="flex items-center gap-3"><span className="grid size-10 place-items-center rounded-2xl bg-[#0B5CFF] font-black text-white shadow-lg shadow-blue-200">Q</span><span className="text-xl font-extrabold tracking-tight text-[#0B1736]">QueueCare</span></div>;
}

export default function Home() {
  const [token, setToken] = useState("");
  const [user, setUser] = useState<User>();
  const [view, setView] = useState<"dashboard" | "setup" | "reports" | "platform">("dashboard");
  const [language, setLanguage] = useState<"en" | "km">("en");
  const [theme, setTheme] = useState<"light" | "dark">("light");
  const [branches, setBranches] = useState<Branch[]>([]);
  const [selectedId, setSelectedId] = useState<number>();
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");

  const loadBranches = useCallback(async (accessToken: string) => {
    setLoading(true); setError("");
    try {
      const result = await apiRequest<{ data: Branch[] }>("/branches", accessToken);
      setBranches(result.data); setSelectedId((current) => current ?? result.data[0]?.id);
    } catch (reason) { setError(reason instanceof Error ? reason.message : "Could not load branches."); }
    finally { setLoading(false); }
  }, []);

  const branch = useMemo(() => branches.find((item) => item.id === selectedId) ?? branches[0], [branches, selectedId]);
  const refresh = () => token && loadBranches(token);
  const notify = (text: string) => { setMessage(text); setTimeout(() => setMessage(""), 2500); };
  const t = copy[language];

  if (!token || !user) return <Login onLogin={(accessToken, account) => { setToken(accessToken); setUser(account); if (account.role !== "counter_staff") void loadBranches(accessToken); }} />;
  if (user.role === "counter_staff") return <StaffWorkspace token={token} user={user} onSignOut={() => { setToken(""); setUser(undefined); }} />;

  return (
    <main data-theme={theme} lang={language} className="app-shell min-h-screen bg-[#F4F8FF] text-[#0B1736]">
      <div className="mx-auto grid min-h-screen max-w-[1600px] lg:grid-cols-[248px_1fr]">
        <aside className="hidden border-r border-[#DDE7F5] bg-white/80 px-5 py-7 backdrop-blur lg:flex lg:flex-col">
          <Brand />
          <p className="mt-10 px-3 text-[11px] font-bold uppercase tracking-[0.18em] text-[#7D8EAA]">{t.workspace}</p>
          <nav className="mt-3 space-y-1 text-sm font-semibold">
            <button onClick={() => setView("dashboard")} aria-current={view === "dashboard" ? "page" : undefined} className={`flex w-full items-center gap-3 rounded-xl px-3 py-3 ${view === "dashboard" ? "bg-[#DCEBFF] text-[#0B5CFF]" : "text-[#526584]"}`}><b>⌁</b> {t.dashboard}</button>
            <button onClick={() => setView("setup")} aria-current={view === "setup" ? "page" : undefined} className={`flex w-full items-center gap-3 rounded-xl px-3 py-3 ${view === "setup" ? "bg-[#DCEBFF] text-[#0B5CFF]" : "text-[#526584]"}`}><b>⌂</b> {t.setup}</button>
            <button onClick={() => setView("reports")} aria-current={view === "reports" ? "page" : undefined} className={`flex w-full items-center gap-3 rounded-xl px-3 py-3 ${view === "reports" ? "bg-[#DCEBFF] text-[#0B5CFF]" : "text-[#526584]"}`}><b>▦</b> {t.reports}</button>
            {user.role === "super_admin" && <button onClick={() => setView("platform")} aria-current={view === "platform" ? "page" : undefined} className={`flex w-full items-center gap-3 rounded-xl px-3 py-3 ${view === "platform" ? "bg-[#DCEBFF] text-[#0B5CFF]" : "text-[#526584]"}`}><b>◎</b> {t.platform}</button>}
          </nav>
          <div className="mt-auto rounded-2xl bg-[#0B1736] p-4 text-white"><p className="text-xs font-semibold text-[#72DDB8]">SPRINT 6</p><p className="mt-2 text-sm font-bold">Platform governance</p><div className="mt-3 h-1.5 rounded-full bg-white/15"><div className="h-full w-full rounded-full bg-[#72DDB8]" /></div><p className="mt-2 text-xs text-white/60">Accounts · access · audit</p></div>
        </aside>

        <section className="min-w-0 px-4 py-5 sm:px-7 lg:px-10 lg:py-7">
          <header className="flex flex-wrap items-center justify-between gap-4">
            <div className="lg:hidden"><Brand /></div>
            <div className="hidden lg:block"><p className="text-sm text-[#526584]">{t.manager} · {user.name}</p><h1 className="text-2xl font-extrabold tracking-tight">{view === "dashboard" ? t.operations : view === "reports" ? t.insights : view === "platform" ? t.governance : t.configuration}</h1></div>
            <div className="flex items-center gap-3">
              {branches.length > 1 && <select aria-label="Select branch" value={branch?.id} onChange={(event) => setSelectedId(Number(event.target.value))} className="field w-auto">{branches.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>}
              <button onClick={() => setLanguage((value) => value === "en" ? "km" : "en")} className="secondary-button" aria-label="Switch language">{language === "en" ? "ខ្មែរ" : "EN"}</button>
              <button onClick={() => setTheme((value) => value === "light" ? "dark" : "light")} className="secondary-button" aria-label={`Use ${theme === "light" ? "dark" : "light"} mode`}>{theme === "light" ? "◐" : "☀"}</button>
              <button onClick={() => { setToken(""); setUser(undefined); setBranches([]); }} className="secondary-button">{t.signOut}</button>
            </div>
          </header>

          {error && <div className="mt-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}
          {message && <div className="fixed right-6 top-6 z-50 rounded-2xl bg-[#0B1736] px-5 py-3 text-sm font-semibold text-white shadow-xl">{message}</div>}
          {view === "platform" && user.role === "super_admin" ? <PlatformWorkspace token={token} currentUserId={user.id} /> : loading && !branch ? <div className="mt-10 grid gap-5 md:grid-cols-3">{[1,2,3].map((item) => <div key={item} className="h-40 animate-pulse rounded-3xl bg-white" />)}</div> : branch && view === "dashboard" ? <DashboardWorkspace branch={branch} token={token} /> : branch && view === "reports" ? <ReportsWorkspace branch={branch} token={token} language={language} /> : branch ? (
            <div className="mt-7 space-y-6">
              <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Metric label="Services" value={branch.services.length} detail={`${branch.services.filter((item) => item.is_active).length} active`} color="blue" />
                <Metric label="Counters" value={branch.counters.length} detail={`${branch.counters.filter((item) => item.is_active).length} open`} color="mint" />
                <Metric label="Staff" value={branch.staff_assignments.length} detail="Assigned to branch" color="violet" />
                <Metric label="Schedule" value={`${branch.operating_hours.filter((item) => !item.is_closed).length}/7`} detail="Operating days" color="amber" />
              </section>

              <div className="grid items-start gap-6 xl:grid-cols-[1.05fr_.95fr]">
                <div className="space-y-6">
                  <BranchDetails branch={branch} token={token} onSaved={() => { refresh(); notify("Branch details saved"); }} onError={setError} />
                  <ServicesPanel branch={branch} token={token} onChanged={() => { refresh(); notify("Services updated"); }} onError={setError} />
                </div>
                <div className="space-y-6">
                  <CountersPanel branch={branch} token={token} onChanged={() => { refresh(); notify("Counters updated"); }} onError={setError} />
                  <HoursPanel branch={branch} token={token} onChanged={() => { refresh(); notify("Operating hours saved"); }} onError={setError} />
                </div>
              </div>
            </div>
          ) : <div className="mt-10 rounded-3xl bg-white p-10 text-center"><h2 className="text-xl font-bold">No assigned branch</h2><p className="mt-2 text-sm text-[#526584]">Ask a super admin to assign this manager to a branch.</p></div>}
        </section>
      </div>
    </main>
  );
}

function Login({ onLogin }: { onLogin: (token: string, user: User) => void }) {
  const [email, setEmail] = useState("manager@queuecare.test"); const [password, setPassword] = useState("password");
  const [busy, setBusy] = useState(false); const [error, setError] = useState("");
  async function submit(event: FormEvent) { event.preventDefault(); setBusy(true); setError(""); try { const result = await fetch(`${API}/auth/login`, { method: "POST", headers: { Accept: "application/json", "Content-Type": "application/json" }, body: JSON.stringify({ email, password, device_name: "QueueCare web" }) }); const body = await result.json(); if (!result.ok) throw new Error(body?.error?.details?.email?.[0] ?? body?.error?.message); onLogin(body.data.token, body.data.user); } catch (reason) { setError(reason instanceof Error ? reason.message : "Could not sign in."); } finally { setBusy(false); } }
  return <main className="grid min-h-screen place-items-center bg-[#F4F8FF] p-5"><div className="grid w-full max-w-5xl overflow-hidden rounded-[32px] border border-white bg-white shadow-[0_30px_90px_rgba(39,82,140,.18)] lg:grid-cols-[1.08fr_.92fr]">
    <section className="relative hidden min-h-[620px] overflow-hidden bg-[#0B5CFF] p-12 text-white lg:block"><div className="absolute -right-28 -top-20 size-80 rounded-full bg-[#72DDB8]/40"/><div className="absolute -bottom-24 -left-20 size-72 rounded-full bg-[#A882F3]/35"/><Brand /><div className="relative mt-32"><span className="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold tracking-widest">MODULAR BENTO</span><h1 className="mt-6 max-w-md text-5xl font-black leading-[1.05] tracking-tight">A smoother day starts with every queue.</h1><p className="mt-5 max-w-md text-lg leading-8 text-blue-100">Configure branches, services, counters, schedules, and staff from one calm workspace.</p></div><div className="absolute bottom-10 left-12 right-12 grid grid-cols-3 gap-3">{["12 min", "2 counters", "Open"].map((item) => <div key={item} className="rounded-2xl bg-white/12 p-3 text-sm font-bold backdrop-blur">{item}</div>)}</div></section>
    <section className="p-7 sm:p-12 lg:p-14"><div className="lg:hidden"><Brand /></div><p className="mt-12 text-sm font-bold uppercase tracking-[.18em] text-[#0B5CFF] lg:mt-8">Staff & admin portal</p><h2 className="mt-3 text-3xl font-black tracking-tight">Welcome back</h2><p className="mt-2 text-[#526584]">Sign in to run counters or manage your branch.</p><form onSubmit={submit} className="mt-9 space-y-5"><label className="block text-sm font-bold">Email<input className="field mt-2" type="email" value={email} onChange={(event) => setEmail(event.target.value)} required /></label><label className="block text-sm font-bold">Password<input className="field mt-2" type="password" value={password} onChange={(event) => setPassword(event.target.value)} required /></label>{error && <p className="rounded-xl bg-red-50 p-3 text-sm text-red-700">{error}</p>}<button disabled={busy} className="primary-button w-full">{busy ? "Signing in…" : "Sign in"}</button></form><div className="mt-8 grid gap-2 text-sm"><button type="button" onClick={() => setEmail("admin@queuecare.test")} className="rounded-2xl bg-[#EFE8FF] p-4 text-left text-[#5B42A8]"><b className="text-[#0B1736]">Demo super admin</b><br/>admin@queuecare.test</button><button type="button" onClick={() => setEmail("manager@queuecare.test")} className="rounded-2xl bg-[#F4F8FF] p-4 text-left text-[#526584]"><b className="text-[#0B1736]">Demo manager</b><br/>manager@queuecare.test</button><button type="button" onClick={() => setEmail("staff@queuecare.test")} className="rounded-2xl bg-[#DDF8EF] p-4 text-left text-[#386655]"><b className="text-[#0B1736]">Demo counter staff</b><br/>staff@queuecare.test</button><p className="px-2 text-xs text-[#7D8EAA]">Password for all: password</p></div></section>
  </div></main>;
}

function StaffWorkspace({ token, user, onSignOut }: { token: string; user: User; onSignOut: () => void }) {
  const [counters, setCounters] = useState<Counter[]>([]);
  const [counterId, setCounterId] = useState<number>();
  const [snapshot, setSnapshot] = useState<CounterSnapshot>();
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  const loadCounters = useCallback(async () => {
    try {
      const result = await apiRequest<{ data: Counter[] }>("/staff/counters", token);
      setCounters(result.data); setCounterId((current) => current ?? result.data[0]?.id);
    } catch (reason) { setError(reason instanceof Error ? reason.message : "Could not load assigned counters."); }
  }, [token]);
  const refresh = useCallback(async () => {
    if (!counterId) return;
    try { const result = await apiRequest<{ data: CounterSnapshot }>(`/staff/counters/${counterId}/queue`, token); setSnapshot(result.data); setError(""); }
    catch (reason) { setError(reason instanceof Error ? reason.message : "Could not refresh the counter."); }
  }, [counterId, token]);

  useEffect(() => { const start = window.setTimeout(() => void loadCounters(), 0); return () => window.clearTimeout(start); }, [loadCounters]);
  useEffect(() => { const start = window.setTimeout(() => void refresh(), 0); const timer = window.setInterval(() => void refresh(), 5000); return () => { window.clearTimeout(start); window.clearInterval(timer); }; }, [refresh]);

  async function command(path: string, body?: object) {
    setBusy(true); setError("");
    try { await apiRequest(path, token, { method: "POST", body: body ? JSON.stringify(body) : undefined }); await refresh(); }
    catch (reason) { setError(reason instanceof Error ? reason.message : "The counter action failed."); }
    finally { setBusy(false); }
  }

  const current = snapshot?.current_ticket;
  return <main className="min-h-screen bg-[#F4F8FF] p-4 text-[#0B1736] sm:p-7">
    <div className="mx-auto max-w-7xl">
      <header className="flex flex-wrap items-center justify-between gap-4"><Brand/><div className="flex items-center gap-3"><div className="text-right"><p className="text-sm font-bold">{user.name}</p><p className="text-xs text-[#7D8EAA]">Counter staff</p></div><button onClick={onSignOut} className="secondary-button">Sign out</button></div></header>
      <div className="mt-7 flex flex-wrap items-end justify-between gap-4"><div><p className="text-sm font-semibold text-[#0B5CFF]">LIVE COUNTER</p><h1 className="mt-1 text-3xl font-black tracking-tight">{snapshot?.branch.name ?? "Counter workspace"}</h1><p className="mt-2 text-sm text-[#526584]">Queue state refreshes every 5 seconds.</p></div>{counters.length > 0 && <select className="field w-auto min-w-48" value={counterId} onChange={(event) => setCounterId(Number(event.target.value))}>{counters.map((counter) => <option key={counter.id} value={counter.id}>{counter.label}</option>)}</select>}</div>
      {error && <div className="mt-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}
      {!snapshot ? <div className="mt-8 h-80 animate-pulse rounded-[32px] bg-white"/> : <div className="mt-7 grid gap-6 lg:grid-cols-[1.15fr_.85fr]">
        <section className="rounded-[32px] bg-[#0B5CFF] p-6 text-white shadow-[0_25px_70px_rgba(11,92,255,.24)] sm:p-8">
          <div className="flex items-start justify-between"><div><p className="text-sm font-bold text-blue-100">{snapshot.label}</p><p className="mt-1 text-xs text-blue-200">{snapshot.services.map((service) => service.name).join(" · ")}</p></div><span className={`rounded-full px-3 py-1.5 text-xs font-bold ${snapshot.is_paused ? "bg-[#FFF0CE] text-[#8A5700]" : "bg-white/15 text-white"}`}>{snapshot.is_paused ? "Paused" : "Active"}</span></div>
          {current ? <div className="mt-14 text-center"><p className="text-xs font-bold uppercase tracking-[.22em] text-blue-100">{current.status === "serving" ? "Now serving" : "Now calling"}</p><p className="mt-4 text-7xl font-black tracking-tight sm:text-8xl">{current.number}</p><p className="mt-4 text-lg text-blue-100">{current.service.name}</p></div> : <div className="mt-14 rounded-3xl bg-white/10 p-10 text-center"><p className="text-xl font-bold">Ready for the next customer</p><p className="mt-2 text-sm text-blue-100">{snapshot.waiting_count} waiting across this counter&apos;s services</p></div>}
          <div className="mt-12 grid gap-3 sm:grid-cols-3">{!current && <button disabled={busy || snapshot.is_paused} onClick={() => command(`/staff/counters/${snapshot.id}/call-next`)} className="h-14 rounded-2xl bg-white font-black text-[#0B5CFF] disabled:opacity-50 sm:col-span-2">Call next</button>}{current?.status === "called" && <><button disabled={busy} onClick={() => command(`/staff/counters/${snapshot.id}/tickets/${current.id}/recall`)} className="h-14 rounded-2xl bg-white/15 font-bold">Recall</button><button disabled={busy} onClick={() => command(`/staff/counters/${snapshot.id}/tickets/${current.id}/serve`)} className="h-14 rounded-2xl bg-[#72DDB8] font-black text-[#0B1736]">Start service</button><button disabled={busy} onClick={() => command(`/staff/counters/${snapshot.id}/tickets/${current.id}/skip`)} className="h-14 rounded-2xl bg-[#FFF0CE] font-bold text-[#8A5700]">Skip</button></>}{current?.status === "serving" && <button disabled={busy} onClick={() => command(`/staff/counters/${snapshot.id}/tickets/${current.id}/complete`)} className="h-14 rounded-2xl bg-[#72DDB8] font-black text-[#0B1736] sm:col-span-2">Complete service</button>}<button disabled={busy || !!current} onClick={() => command(`/staff/counters/${snapshot.id}/pause`, { is_paused: !snapshot.is_paused })} className="h-14 rounded-2xl border border-white/25 font-bold disabled:opacity-40">{snapshot.is_paused ? "Resume" : "Pause"}</button></div>
          {current && snapshot.transfer_targets.length > 0 && <div className="mt-4 flex gap-2 rounded-2xl bg-white/10 p-3"><select id="transfer-target" className="h-11 min-w-0 flex-1 rounded-xl bg-white px-3 text-sm font-bold text-[#0B1736]">{snapshot.transfer_targets.map((target) => <option key={target.id} value={target.id}>{target.label}</option>)}</select><button disabled={busy} onClick={() => { const select = document.getElementById("transfer-target") as HTMLSelectElement; const reason = window.prompt("Reason for transfer (required)"); if (reason) void command(`/tickets/${current.id}/transfer`, { target_counter_id: Number(select.value), reason }); }} className="rounded-xl border border-white/30 px-4 text-sm font-bold">Transfer</button></div>}
        </section>
        <div className="space-y-6"><Panel title={`Waiting list · ${snapshot.waiting_count}`} subtitle="Ordered by priority and waiting time."><div className="space-y-2">{snapshot.waiting_tickets.length ? snapshot.waiting_tickets.map((ticket, index) => <div key={ticket.id} className="flex items-center gap-3 rounded-2xl bg-[#F4F8FF] p-3"><span className="grid size-9 place-items-center rounded-xl bg-white text-xs font-black text-[#7D8EAA]">{index + 1}</span><div className="flex-1"><p className="font-black">{ticket.number}</p><p className="text-xs text-[#7D8EAA]">{ticket.service.name}</p></div><span className="rounded-full bg-[#DCEBFF] px-2.5 py-1 text-[11px] font-bold text-[#0B5CFF]">{ticket.priority}</span></div>) : <p className="py-8 text-center text-sm text-[#7D8EAA]">The queue is clear.</p>}</div></Panel>{snapshot.skipped_tickets.length > 0 && <Panel title="Skipped tickets" subtitle="Restore a ticket once when the customer returns."><div className="space-y-2">{snapshot.skipped_tickets.map((ticket) => <div key={ticket.id} className="flex items-center gap-3 rounded-2xl bg-[#FFF0CE] p-3"><b className="flex-1">{ticket.number}</b><button disabled={busy} onClick={() => command(`/staff/counters/${snapshot.id}/tickets/${ticket.id}/restore`)} className="rounded-xl bg-white px-3 py-2 text-xs font-black text-[#8A5700]">Restore</button></div>)}</div></Panel>}</div>
      </div>}
    </div>
  </main>;
}

function PlatformWorkspace({ token, currentUserId }: { token: string; currentUserId: number }) {
  const [overview, setOverview] = useState<AdminOverview>();
  const [users, setUsers] = useState<AdminUser[]>([]);
  const [search, setSearch] = useState("");
  const [role, setRole] = useState("");
  const [status, setStatus] = useState("");
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");
  const [busy, setBusy] = useState(false);

  const refresh = useCallback(async () => {
    setBusy(true); setError("");
    const query = new URLSearchParams();
    if (search) query.set("search", search); if (role) query.set("role", role); if (status) query.set("status", status);
    try {
      const [overviewResponse, userResponse] = await Promise.all([
        apiRequest<{ data: AdminOverview }>("/admin/overview", token),
        apiRequest<{ data: AdminUser[] }>(`/admin/users?${query}`, token),
      ]);
      setOverview(overviewResponse.data); setUsers(userResponse.data);
    } catch (reason) { setError(reason instanceof Error ? reason.message : "Could not load platform administration."); }
    finally { setBusy(false); }
  }, [role, search, status, token]);
  useEffect(() => { const start = window.setTimeout(() => void refresh(), 0); return () => window.clearTimeout(start); }, [refresh]);

  async function createAccount(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setBusy(true); setError("");
    const form = event.currentTarget; const values = Object.fromEntries(new FormData(form));
    try { await apiRequest("/admin/users", token, { method: "POST", body: JSON.stringify(values) }); form.reset(); setMessage("Account created and audit event recorded."); await refresh(); }
    catch (reason) { setError(reason instanceof Error ? reason.message : "Could not create the account."); }
    finally { setBusy(false); }
  }

  async function updateAccount(account: AdminUser, change: { role?: string; suspended?: boolean }) {
    const reason = window.prompt("Reason for this access change (required)");
    if (!reason) return;
    setBusy(true); setError("");
    try { await apiRequest(`/admin/users/${account.id}`, token, { method: "PATCH", body: JSON.stringify({ ...change, reason }) }); setMessage("Access updated and audit event recorded."); await refresh(); }
    catch (cause) { setError(cause instanceof Error ? cause.message : "Could not update access."); }
    finally { setBusy(false); }
  }

  if (!overview) return <div className="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">{[1,2,3,4].map((item) => <div key={item} className="h-40 animate-pulse rounded-3xl bg-white" />)}</div>;
  return <div className="mt-7 space-y-6">
    <section className="rounded-[32px] bg-[#0B1736] p-6 text-white sm:p-8"><div className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-xs font-black uppercase tracking-[.2em] text-[#A882F3]">SUPER ADMIN</p><h2 className="mt-3 text-3xl font-black">Platform control center</h2><p className="mt-2 text-sm text-[#C7D7F2]">Manage access across {overview.active_branches} active branches with a permanent audit trail.</p></div><span className="rounded-2xl bg-white/10 px-4 py-3 text-sm font-bold">{overview.served_today} served today</span></div></section>
    {error && <div role="alert" className="rounded-2xl bg-red-50 p-4 text-sm text-red-700">{error}</div>}
    {message && <div role="status" className="rounded-2xl bg-[#DDF8EF] p-4 text-sm font-bold text-[#0D7C59]">{message}</div>}
    <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><Metric label="Branches" value={overview.branches} detail={`${overview.active_branches} active`} color="blue"/><Metric label="Accounts" value={overview.users} detail={`${overview.suspended_users} suspended`} color="violet"/><Metric label="Counter staff" value={overview.users_by_role.counter_staff} detail={`${overview.active_counters} active counters`} color="mint"/><Metric label="Customers" value={overview.users_by_role.customers} detail={`${overview.users_by_role.branch_managers} managers`} color="amber"/></section>
    <div className="grid items-start gap-6 xl:grid-cols-[.72fr_1.28fr]"><Panel title="Create account" subtitle="Issue a verified platform account with an initial role."><form onSubmit={createAccount} className="space-y-4"><label className="form-label">Full name<input name="name" className="field mt-2" required/></label><label className="form-label">Email<input name="email" type="email" className="field mt-2" required/></label><label className="form-label">Initial role<select name="role" className="field mt-2" defaultValue="customer"><option value="customer">Customer</option><option value="counter_staff">Counter staff</option><option value="branch_manager">Branch manager</option><option value="super_admin">Super admin</option></select></label><label className="form-label">Temporary password<input name="password" type="password" minLength={8} className="field mt-2" required/></label><button disabled={busy} className="primary-button w-full">Create account</button></form></Panel>
      <Panel title="Account directory" subtitle="Search, filter, change roles, and suspend access."><form onSubmit={(event) => { event.preventDefault(); void refresh(); }} className="grid gap-3 sm:grid-cols-[1fr_160px_140px_auto]"><input aria-label="Search accounts" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search name or email" className="field"/><select aria-label="Filter by role" value={role} onChange={(event) => setRole(event.target.value)} className="field"><option value="">All roles</option><option value="customer">Customers</option><option value="counter_staff">Counter staff</option><option value="branch_manager">Managers</option><option value="super_admin">Super admins</option></select><select aria-label="Filter by status" value={status} onChange={(event) => setStatus(event.target.value)} className="field"><option value="">All status</option><option value="active">Active</option><option value="suspended">Suspended</option></select><button disabled={busy} className="secondary-button">Apply</button></form><div className="mt-5 overflow-x-auto"><table className="w-full min-w-[720px] text-left text-sm"><thead className="text-xs uppercase tracking-wider text-[#7D8EAA]"><tr><th scope="col" className="pb-3">Account</th><th scope="col" className="pb-3">Role</th><th scope="col" className="pb-3">Assignment</th><th scope="col" className="pb-3">Status</th><th scope="col" className="pb-3 text-right">Action</th></tr></thead><tbody>{users.map((account) => <tr key={account.id} className="border-t border-[#E4ECF7]"><th scope="row" className="py-4"><span className="block font-black">{account.name}</span><span className="text-xs font-normal text-[#7D8EAA]">{account.email}</span></th><td><select aria-label={`Role for ${account.name}`} disabled={busy || account.id === currentUserId} value={account.role} onChange={(event) => void updateAccount(account, { role: event.target.value })} className="h-9 rounded-xl border border-[#DDE7F5] bg-white px-2 text-xs font-bold"><option value="customer">Customer</option><option value="counter_staff">Counter staff</option><option value="branch_manager">Manager</option><option value="super_admin">Super admin</option></select></td><td className="text-xs text-[#526584]">{account.assignments?.map((item) => item.counter ? `${item.branch} · ${item.counter}` : item.branch).filter(Boolean).join(", ") || "Platform-wide"}</td><td><span className={`rounded-full px-2.5 py-1 text-xs font-bold ${account.suspended_at ? "bg-[#FFF0F3] text-[#B42345]" : "bg-[#DDF8EF] text-[#0D7C59]"}`}>{account.suspended_at ? "Suspended" : "Active"}</span></td><td className="text-right"><button disabled={busy || account.id === currentUserId} onClick={() => void updateAccount(account, { suspended: !account.suspended_at })} className="rounded-xl border border-[#DDE7F5] px-3 py-2 text-xs font-bold disabled:opacity-30">{account.suspended_at ? "Restore" : "Suspend"}</button></td></tr>)}</tbody></table>{!users.length && <p className="py-8 text-center text-sm text-[#7D8EAA]">No accounts match these filters.</p>}</div></Panel></div>
    <Panel title="Administration activity" subtitle="Who changed access, what changed, and when."><div className="grid gap-3 lg:grid-cols-2">{overview.recent_activity.map((item) => <article key={item.id} className="rounded-2xl border border-[#E4ECF7] p-4"><div className="flex items-center justify-between gap-3"><span className="rounded-full bg-[#EFE8FF] px-2.5 py-1 text-[11px] font-black text-[#7754D8]">{item.action}</span><time className="text-xs text-[#7D8EAA]">{new Date(item.occurred_at).toLocaleString()}</time></div><p className="mt-3 text-sm font-bold">{item.description}</p><p className="mt-1 text-xs text-[#7D8EAA]">{item.actor?.name ?? "System"}{item.ip_address ? ` · ${item.ip_address}` : ""}</p></article>)}{!overview.recent_activity.length && <p className="py-8 text-center text-sm text-[#7D8EAA]">No administration changes recorded yet.</p>}</div></Panel>
  </div>;
}

function DashboardWorkspace({ branch, token }: { branch: Branch; token: string }) {
  const [dashboard, setDashboard] = useState<Dashboard>();
  const [error, setError] = useState("");
  const refresh = useCallback(async () => { try { const result = await apiRequest<{ data: Dashboard }>(`/dashboard/${branch.id}`, token); setDashboard(result.data); setError(""); } catch (reason) { setError(reason instanceof Error ? reason.message : "Could not load dashboard."); } }, [branch.id, token]);
  useEffect(() => { const start = window.setTimeout(() => void refresh(), 0); const timer = window.setInterval(() => void refresh(), 5000); return () => { window.clearTimeout(start); window.clearInterval(timer); }; }, [refresh]);
  async function changePriority(ticketId: number, priority: string) { const reason = window.prompt(`Reason for ${priority} priority (required)`); if (!reason) return; try { await apiRequest(`/tickets/${ticketId}/priority`, token, { method: "POST", body: JSON.stringify({ priority, reason }) }); await refresh(); } catch (cause) { setError(cause instanceof Error ? cause.message : "Could not update priority."); } }
  if (error) return <div className="mt-6 rounded-2xl bg-red-50 p-4 text-sm text-red-700">{error}</div>;
  if (!dashboard) return <div className="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">{[1,2,3,4].map((item) => <div key={item} className="h-40 animate-pulse rounded-3xl bg-white" />)}</div>;
  return <div className="mt-7 space-y-6"><section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><Metric label="Waiting now" value={dashboard.waiting_now} detail="Customers in queue" color="blue"/><Metric label="Active counters" value={dashboard.active_counters} detail="Open and available" color="mint"/><Metric label="Served today" value={dashboard.served_today} detail="Completed tickets" color="violet"/><Metric label="Exceptions" value={dashboard.skipped_today + dashboard.cancelled_today} detail={`${dashboard.skipped_today} skipped · ${dashboard.cancelled_today} cancelled`} color="amber"/></section><div className="grid gap-6 xl:grid-cols-2"><Panel title="Priority controls" subtitle="Every change records the manager, old/new tier, reason, and time."><div className="space-y-2">{dashboard.waiting_tickets.map((ticket) => <div key={ticket.id} className="flex flex-wrap items-center gap-3 rounded-2xl bg-[#F4F8FF] p-3"><div className="flex-1"><b>{ticket.number}</b><p className="text-xs text-[#7D8EAA]">{ticket.service} · {ticket.priority}</p></div><button onClick={() => changePriority(ticket.id, "accessibility")} className="rounded-xl bg-[#DDF8EF] px-3 py-2 text-xs font-black text-[#0D7C59]">Accessibility</button><button onClick={() => changePriority(ticket.id, "emergency")} className="rounded-xl bg-[#FFF0F3] px-3 py-2 text-xs font-black text-[#B42345]">Emergency</button></div>)}{!dashboard.waiting_tickets.length && <p className="py-8 text-center text-sm text-[#7D8EAA]">No waiting tickets.</p>}</div></Panel><Panel title="Live queue activity" subtitle="Latest ticket changes across this branch."><div className="overflow-x-auto"><table className="w-full min-w-[480px] text-left text-sm"><thead className="text-xs uppercase tracking-wider text-[#7D8EAA]"><tr><th className="pb-3">Ticket</th><th className="pb-3">Service</th><th className="pb-3">Counter</th><th className="pb-3">Status</th></tr></thead><tbody>{dashboard.live_activity.map((item) => <tr key={`${item.number}-${item.updated_at}`} className="border-t border-[#E4ECF7]"><td className="py-4 font-black">{item.number}</td><td>{item.service}</td><td>{item.counter ?? "—"}</td><td><span className="rounded-full bg-[#DCEBFF] px-3 py-1 text-xs font-bold capitalize text-[#0B5CFF]">{item.status}</span></td></tr>)}</tbody></table></div></Panel></div><a href={`/display/${branch.id}`} target="_blank" className="inline-flex h-11 items-center rounded-xl bg-[#0B1736] px-5 text-sm font-bold text-white">Open public display ↗</a></div>;
}

function ReportsWorkspace({ branch, token, language }: { branch: Branch; token: string; language: "en" | "km" }) {
  const [report, setReport] = useState<Report>();
  const [from, setFrom] = useState(() => { const date = new Date(); date.setDate(date.getDate() - 29); return date.toISOString().slice(0, 10); });
  const [to, setTo] = useState(() => new Date().toISOString().slice(0, 10));
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const labels = language === "km" ? {
    title: "ទិដ្ឋភាពប្រតិបត្តិការ", subtitle: "ប្រៀបធៀបការរង់ចាំ សេវាកម្ម និងលទ្ធផលសាខា។", apply: "អនុវត្ត",
    total: "សំបុត្រសរុប", served: "បានបម្រើ", wait: "ពេលរង់ចាំមធ្យម", service: "ពេលបម្រើមធ្យម",
    trend: "និន្នាការប្រចាំថ្ងៃ", services: "លទ្ធផលសេវាកម្ម", branches: "ប្រៀបធៀបសាខា", staff: "លទ្ធផលបុគ្គលិក",
  } : {
    title: "Operational overview", subtitle: "Compare wait, service, and branch outcomes for the selected period.", apply: "Apply",
    total: "Total tickets", served: "Served", wait: "Average wait", service: "Average service",
    trend: "Daily demand", services: "Service performance", branches: "Branch comparison", staff: "Staff performance",
  };

  const refresh = useCallback(async () => {
    setBusy(true); setError("");
    try { const result = await apiRequest<{ data: Report }>(`/reports/${branch.id}?from=${from}&to=${to}`, token); setReport(result.data); }
    catch (reason) { setError(reason instanceof Error ? reason.message : "Could not load the report."); }
    finally { setBusy(false); }
  }, [branch.id, from, to, token]);
  useEffect(() => { const start = window.setTimeout(() => void refresh(), 0); return () => window.clearTimeout(start); }, [refresh]);

  async function download(format: "csv" | "pdf") {
    setError("");
    try {
      const response = await fetch(`${API}/reports/${branch.id}/${format}?from=${from}&to=${to}`, { headers: { Accept: format === "pdf" ? "application/pdf" : "text/csv", Authorization: `Bearer ${token}` } });
      if (!response.ok) throw new Error("The export could not be generated.");
      const url = URL.createObjectURL(await response.blob());
      const link = document.createElement("a"); link.href = url; link.download = `queuecare-${branch.slug}-${from}-${to}.${format}`; link.click(); URL.revokeObjectURL(url);
    } catch (reason) { setError(reason instanceof Error ? reason.message : "The export could not be generated."); }
  }

  const maximum = Math.max(1, ...(report?.daily.map((item) => item.tickets) ?? [1]));
  return <div className="mt-7 space-y-6">
    <section className="report-hero rounded-[32px] bg-[#0B1736] p-6 text-white sm:p-8">
      <div className="flex flex-wrap items-end justify-between gap-5"><div><p className="text-xs font-black uppercase tracking-[.2em] text-[#72DDB8]">QueueCare intelligence</p><h2 className="mt-3 text-3xl font-black tracking-tight">{labels.title}</h2><p className="mt-2 max-w-2xl text-sm text-[#C7D7F2]">{labels.subtitle}</p></div><div className="flex flex-wrap gap-2"><button onClick={() => download("csv")} className="h-11 rounded-xl bg-white/10 px-4 text-sm font-bold">Export CSV</button><button onClick={() => download("pdf")} className="h-11 rounded-xl bg-white px-4 text-sm font-bold text-[#0B1736]">Export PDF</button></div></div>
      <form onSubmit={(event) => { event.preventDefault(); void refresh(); }} className="mt-7 flex flex-wrap items-end gap-3"><label className="text-xs font-bold text-blue-100">From<input aria-label="Report start date" type="date" value={from} onChange={(event) => setFrom(event.target.value)} className="field mt-2 text-[#0B1736]" /></label><label className="text-xs font-bold text-blue-100">To<input aria-label="Report end date" type="date" value={to} onChange={(event) => setTo(event.target.value)} className="field mt-2 text-[#0B1736]" /></label><button disabled={busy} className="h-11 rounded-xl bg-[#72DDB8] px-5 text-sm font-black text-[#0B1736]">{busy ? "…" : labels.apply}</button></form>
    </section>
    {error && <div role="alert" className="rounded-2xl bg-red-50 p-4 text-sm text-red-700">{error}</div>}
    {!report ? <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">{[1,2,3,4].map((item) => <div key={item} className="h-40 animate-pulse rounded-3xl bg-white" />)}</div> : <>
      <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><Metric label={labels.total} value={report.summary.total_tickets} detail={`${report.summary.cancelled} cancelled`} color="blue"/><Metric label={labels.served} value={report.summary.served} detail={`${report.summary.skipped} skipped`} color="mint"/><Metric label={labels.wait} value={`${report.summary.average_wait_minutes} min`} detail="Waiting to first call" color="violet"/><Metric label={labels.service} value={`${report.summary.average_service_minutes} min`} detail="Service start to completion" color="amber"/></section>
      <div className="grid gap-6 xl:grid-cols-[1.15fr_.85fr]"><Panel title={labels.trend} subtitle={`${report.range.from} — ${report.range.to}`}><div className="flex h-56 items-end gap-2" aria-label="Daily ticket volume chart">{report.daily.map((item) => <div key={item.date} className="group flex min-w-0 flex-1 flex-col items-center justify-end gap-2"><span className="text-[10px] font-bold text-[#526584] opacity-0 group-hover:opacity-100">{item.tickets}</span><div className="w-full rounded-t-lg bg-[#0B5CFF]" style={{ height: `${Math.max(3, (item.tickets / maximum) * 165)}px` }} /><span className="hidden text-[9px] text-[#7D8EAA] sm:block">{item.date.slice(5)}</span></div>)}</div></Panel><Panel title={labels.staff} subtitle="Completed tickets and average handling time."><div className="space-y-3">{report.staff.map((item, index) => <div key={item.name} className="flex items-center gap-3 rounded-2xl bg-[#F4F8FF] p-3"><span className="grid size-9 place-items-center rounded-xl bg-[#DCEBFF] text-xs font-black text-[#0B5CFF]">{index + 1}</span><div className="flex-1"><p className="text-sm font-black">{item.name}</p><p className="text-xs text-[#7D8EAA]">{item.average_service_minutes} min average</p></div><b>{item.served}</b></div>)}{!report.staff.length && <p className="py-8 text-center text-sm text-[#7D8EAA]">No completed service records.</p>}</div></Panel></div>
      <div className="grid gap-6 xl:grid-cols-2"><ReportTable title={labels.services} headers={["Service", "Tickets", "Served", "Wait", "Service"]} rows={report.services.map((item) => [item.service, item.tickets, item.served, `${item.average_wait_minutes}m`, `${item.average_service_minutes}m`])}/><ReportTable title={labels.branches} headers={["Branch", "Tickets", "Served", "Wait", "Cancel"]} rows={report.branch_comparison.map((item) => [item.branch, item.tickets, item.served, `${item.average_wait_minutes}m`, `${item.cancellation_rate}%`])}/></div>
    </>}
  </div>;
}

function ReportTable({ title, headers, rows }: { title: string; headers: string[]; rows: (string | number)[][] }) {
  return <Panel title={title} subtitle="Performance for the selected reporting period."><div className="overflow-x-auto"><table className="w-full min-w-[520px] text-left text-sm"><thead><tr className="text-xs uppercase tracking-wider text-[#7D8EAA]">{headers.map((header) => <th scope="col" key={header} className="pb-3">{header}</th>)}</tr></thead><tbody>{rows.map((row) => <tr key={String(row[0])} className="border-t border-[#E4ECF7]">{row.map((cell, index) => index === 0 ? <th scope="row" key={index} className="py-4 font-bold">{cell}</th> : <td key={index}>{cell}</td>)}</tr>)}</tbody></table></div></Panel>;
}

function Metric({ label, value, detail, color }: { label: string; value: string | number; detail: string; color: "blue" | "mint" | "violet" | "amber" }) {
  const accents = { blue: "bg-[#DCEBFF] text-[#0B5CFF]", mint: "bg-[#DDF8EF] text-[#0D8B62]", violet: "bg-[#EFE8FF] text-[#7754D8]", amber: "bg-[#FFF0CE] text-[#B26C00]" };
  return <article className="rounded-3xl border border-[#E4ECF7] bg-white p-5 shadow-[0_10px_30px_rgba(45,83,130,.06)]"><div className={`mb-5 grid size-10 place-items-center rounded-2xl text-lg font-black ${accents[color]}`}>↗</div><p className="text-sm font-semibold text-[#526584]">{label}</p><p className="mt-1 text-3xl font-black tracking-tight">{value}</p><p className="mt-2 text-xs text-[#7D8EAA]">{detail}</p></article>;
}

function Panel({ title, subtitle, children }: { title: string; subtitle: string; children: React.ReactNode }) { return <section className="rounded-3xl border border-[#E4ECF7] bg-white p-5 shadow-[0_12px_40px_rgba(45,83,130,.06)] sm:p-6"><h2 className="text-lg font-extrabold">{title}</h2><p className="mt-1 text-sm text-[#7D8EAA]">{subtitle}</p><div className="mt-6">{children}</div></section>; }

function BranchDetails({ branch, token, onSaved, onError }: { branch: Branch; token: string; onSaved: () => void; onError: (value: string) => void }) {
  async function save(event: FormEvent<HTMLFormElement>) { event.preventDefault(); const data = Object.fromEntries(new FormData(event.currentTarget)); try { await apiRequest(`/branches/${branch.id}`, token, { method: "PATCH", body: JSON.stringify(data) }); onSaved(); } catch (reason) { onError(reason instanceof Error ? reason.message : "Save failed."); } }
  return <Panel title="Branch details" subtitle="The public information customers use to find this location."><form onSubmit={save} className="grid gap-4 sm:grid-cols-2"><label className="form-label">Branch name<input name="name" defaultValue={branch.name} className="field mt-2" /></label><label className="form-label">Phone<input name="phone" defaultValue={branch.phone ?? ""} className="field mt-2" /></label><label className="form-label sm:col-span-2">Address<input name="address" defaultValue={branch.address} className="field mt-2" /></label><label className="form-label">Timezone<input name="timezone" defaultValue={branch.timezone} className="field mt-2" /></label><div className="flex items-end"><button className="primary-button w-full">Save details</button></div></form></Panel>;
}

function ServicesPanel({ branch, token, onChanged, onError }: { branch: Branch; token: string; onChanged: () => void; onError: (value: string) => void }) {
  async function add(event: FormEvent<HTMLFormElement>) { event.preventDefault(); const form = event.currentTarget; const data = Object.fromEntries(new FormData(form)); try { await apiRequest(`/branches/${branch.id}/services`, token, { method: "POST", body: JSON.stringify({ ...data, average_service_minutes: Number(data.average_service_minutes) }) }); form.reset(); onChanged(); } catch (reason) { onError(reason instanceof Error ? reason.message : "Could not add service."); } }
  async function remove(id: number) { try { await apiRequest(`/branches/${branch.id}/services/${id}`, token, { method: "DELETE" }); onChanged(); } catch (reason) { onError(reason instanceof Error ? reason.message : "Could not remove service."); } }
  return <Panel title="Services" subtitle="Configure what customers can queue for."><div className="space-y-3">{branch.services.map((service) => <div key={service.id} className="flex items-center gap-3 rounded-2xl border border-[#E4ECF7] p-3"><span className="grid size-10 place-items-center rounded-xl bg-[#DCEBFF] text-xs font-black text-[#0B5CFF]">{service.code}</span><div className="min-w-0 flex-1"><p className="truncate text-sm font-bold">{service.name}</p><p className="text-xs text-[#7D8EAA]">Average {service.average_service_minutes} min</p></div><button aria-label={`Delete ${service.name}`} onClick={() => remove(service.id)} className="rounded-xl px-3 py-2 text-sm font-bold text-[#9B3150] hover:bg-red-50">×</button></div>)}</div><form onSubmit={add} className="mt-4 grid gap-3 rounded-2xl bg-[#F4F8FF] p-4 sm:grid-cols-[1fr_90px_100px_auto]"><input aria-label="Service name" name="name" placeholder="Service name" className="field" required/><input aria-label="Service code" name="code" placeholder="Code" className="field uppercase" required/><input aria-label="Average minutes" name="average_service_minutes" type="number" min="1" defaultValue="15" className="field" required/><button className="secondary-button">Add</button></form></Panel>;
}

function CountersPanel({ branch, token, onChanged, onError }: { branch: Branch; token: string; onChanged: () => void; onError: (value: string) => void }) {
  async function add(event: FormEvent<HTMLFormElement>) { event.preventDefault(); const form = event.currentTarget; const data = new FormData(form); try { await apiRequest(`/branches/${branch.id}/counters`, token, { method: "POST", body: JSON.stringify({ label: data.get("label"), service_ids: data.get("service_id") ? [Number(data.get("service_id"))] : [] }) }); form.reset(); onChanged(); } catch (reason) { onError(reason instanceof Error ? reason.message : "Could not add counter."); } }
  async function remove(id: number) { try { await apiRequest(`/branches/${branch.id}/counters/${id}`, token, { method: "DELETE" }); onChanged(); } catch (reason) { onError(reason instanceof Error ? reason.message : "Could not remove counter."); } }
  return <Panel title="Counters" subtitle="Connect each desk to the services it can handle."><div className="grid gap-3 sm:grid-cols-2">{branch.counters.map((counter) => <div key={counter.id} className="rounded-2xl border border-[#E4ECF7] p-4"><div className="flex items-start justify-between"><span className="grid size-10 place-items-center rounded-xl bg-[#DDF8EF] font-black text-[#0D8B62]">{counter.label.replace(/\D/g, "") || "C"}</span><button onClick={() => remove(counter.id)} className="text-lg text-[#9B3150]">×</button></div><p className="mt-3 text-sm font-bold">{counter.label}</p><p className="mt-1 text-xs text-[#7D8EAA]">{counter.services.map((item) => item.name).join(", ") || "No service assigned"}</p></div>)}</div><form onSubmit={add} className="mt-4 grid gap-3 rounded-2xl bg-[#F4F8FF] p-4 sm:grid-cols-[1fr_1fr_auto]"><input name="label" placeholder="Counter label" className="field" required/><select aria-label="Counter service" name="service_id" className="field"><option value="">No service</option>{branch.services.map((service) => <option key={service.id} value={service.id}>{service.name}</option>)}</select><button className="secondary-button">Add</button></form></Panel>;
}

function HoursPanel({ branch, token, onChanged, onError }: { branch: Branch; token: string; onChanged: () => void; onError: (value: string) => void }) {
  const [day, setDay] = useState(1); const current = branch.operating_hours.find((item) => item.day_of_week === day); const [closedOverrides, setClosedOverrides] = useState<Record<number, boolean>>({}); const closed = closedOverrides[day] ?? current?.is_closed ?? false;
  async function save(event: FormEvent<HTMLFormElement>) { event.preventDefault(); const data = Object.fromEntries(new FormData(event.currentTarget)); try { await apiRequest(`/branches/${branch.id}/operating-hours`, token, { method: "POST", body: JSON.stringify({ day_of_week: day, opens_at: closed ? null : data.opens_at, closes_at: closed ? null : data.closes_at, is_closed: closed }) }); onChanged(); } catch (reason) { onError(reason instanceof Error ? reason.message : "Could not save hours."); } }
  return <Panel title="Operating hours" subtitle="Keep arrival and queue estimates aligned with opening times."><div className="flex gap-1 overflow-x-auto pb-2">{days.map((label, index) => <button key={label} onClick={() => setDay(index)} className={`min-w-10 rounded-xl px-2 py-2 text-xs font-bold ${day === index ? "bg-[#0B5CFF] text-white" : "bg-[#F4F8FF] text-[#526584]"}`}>{label.slice(0, 2)}</button>)}</div><form key={`${day}-${current?.id}`} onSubmit={save} className="mt-4 grid gap-3 sm:grid-cols-2"><label className="form-label">Opens<input name="opens_at" type="time" defaultValue={current?.opens_at?.slice(0,5) ?? "08:00"} disabled={closed} className="field mt-2 disabled:opacity-40"/></label><label className="form-label">Closes<input name="closes_at" type="time" defaultValue={current?.closes_at?.slice(0,5) ?? "17:00"} disabled={closed} className="field mt-2 disabled:opacity-40"/></label><label className="flex items-center gap-3 rounded-xl bg-[#F4F8FF] p-3 text-sm font-bold"><input type="checkbox" checked={closed} onChange={(event) => setClosedOverrides((value) => ({ ...value, [day]: event.target.checked }))} className="size-4 accent-[#0B5CFF]"/>Closed all day</label><button className="primary-button">Save {days[day]}</button></form></Panel>;
}
