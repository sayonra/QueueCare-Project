"use client";

import { useCallback, useEffect, useState } from "react";
import { useParams } from "next/navigation";

type DisplayData = {
  name: string;
  currently_serving: { number: string; counter: string | null; status: string }[];
  upcoming: string[];
  refreshed_at: string;
};

const rawApi = process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://127.0.0.1:8000/api/v1";
const API = rawApi.endsWith("/api/v1") ? rawApi : `${rawApi.replace(/\/$/, "")}/api/v1`;

export default function PublicDisplay() {
  const { branchId } = useParams<{ branchId: string }>();
  const [data, setData] = useState<DisplayData>();
  const [error, setError] = useState("");
  const refresh = useCallback(async () => {
    try {
      const response = await fetch(`${API}/public/branches/${branchId}/display`, { headers: { Accept: "application/json" }, cache: "no-store" });
      if (!response.ok) throw new Error("The queue display is unavailable.");
      const body = await response.json(); setData(body.data); setError("");
    } catch (reason) { setError(reason instanceof Error ? reason.message : "The queue display is unavailable."); }
  }, [branchId]);

  useEffect(() => { void refresh(); const timer = window.setInterval(() => void refresh(), 5000); return () => window.clearInterval(timer); }, [refresh]);

  return <main className="min-h-screen overflow-hidden bg-[#06122F] p-6 text-white sm:p-10">
    <header className="mx-auto flex max-w-[1500px] items-center justify-between"><div className="flex items-center gap-4"><span className="grid size-14 place-items-center rounded-2xl bg-[#0B5CFF] text-2xl font-black">Q</span><div><p className="text-2xl font-black">QueueCare</p><p className="text-sm text-blue-200">{data?.name ?? "Live queue"}</p></div></div><div className="flex items-center gap-2 text-sm text-blue-200"><span className="size-2.5 animate-pulse rounded-full bg-[#72DDB8]"/>Live · refreshes every 5 seconds</div></header>
    {error ? <div className="mx-auto mt-24 max-w-xl rounded-3xl bg-red-500/15 p-8 text-center text-red-100">{error}</div> : <div className="mx-auto mt-12 grid max-w-[1500px] gap-8 xl:grid-cols-[1.25fr_.75fr]">
      <section><p className="text-sm font-bold uppercase tracking-[.24em] text-[#72DDB8]">Now serving</p><div className="mt-5 grid gap-5 md:grid-cols-2">{data?.currently_serving.length ? data.currently_serving.map((ticket) => <article key={`${ticket.number}-${ticket.counter}`} className="rounded-[36px] bg-white p-8 text-[#0B1736] shadow-[0_30px_90px_rgba(11,92,255,.18)]"><div className="flex items-center justify-between"><span className="rounded-full bg-[#DDF8EF] px-3 py-1.5 text-xs font-black uppercase tracking-wider text-[#0D8B62]">{ticket.status}</span><span className="text-lg font-bold text-[#526584]">{ticket.counter ?? "Please wait"}</span></div><p className="mt-10 text-center text-7xl font-black tracking-tight text-[#0B5CFF] sm:text-8xl">{ticket.number}</p><p className="mt-8 text-center text-sm font-semibold text-[#526584]">Please proceed to the counter</p></article>) : <div className="rounded-[36px] border border-white/10 bg-white/5 p-16 text-center md:col-span-2"><p className="text-2xl font-bold">Waiting for the next call</p><p className="mt-2 text-blue-200">Queue updates will appear here.</p></div>}</div></section>
      <aside className="rounded-[36px] bg-[#0B5CFF] p-7"><p className="text-sm font-bold uppercase tracking-[.24em] text-blue-100">Coming up</p><div className="mt-5 space-y-3">{data?.upcoming.length ? data.upcoming.map((number, index) => <div key={number} className="flex items-center rounded-2xl bg-white/10 px-5 py-4"><span className="mr-4 grid size-9 place-items-center rounded-xl bg-white/10 text-xs font-bold">{index + 1}</span><span className="text-2xl font-black">{number}</span></div>) : <p className="rounded-2xl bg-white/10 p-8 text-center text-blue-100">No customers waiting.</p>}</div></aside>
    </div>}
  </main>;
}
