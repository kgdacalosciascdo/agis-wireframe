const styles = {
  sky: "border-blue-200 bg-blue-50 text-blue-700",
  emerald: "border-emerald-200 bg-emerald-50 text-emerald-700",
  amber: "border-amber-200 bg-amber-50 text-amber-700",
  slate: "border-slate-200 bg-slate-50 text-slate-600",
  red: "border-red-200 bg-red-50 text-red-600",
};
export default function SummaryCard({ icon: Icon, label, value, tone }) {
  return (
    <div
      className={`flex min-w-0 items-center gap-3 rounded-xl border px-4 py-3 shadow-sm ${styles[tone]} hover:-translate-y-1.5 hover:shadow-xl ${tone}`}
    >
      <span className="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-white/80 shadow-sm ring-1 ring-black/5">
        <Icon size={20} strokeWidth={2} />
      </span>
      <span className="min-w-0">
        <strong className="block text-xl leading-none text-slate-900">
          {value}
        </strong>
        <span className="mt-1 block truncate text-xs font-semibold uppercase tracking-wide opacity-80">
          {label}
        </span>
      </span>
    </div>
  );
}
