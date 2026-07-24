import { useEffect, useMemo, useState } from "react";
import { Activity, Search } from "lucide-react";
import DataTable from "../components/ui/DataTable";
import RegistryHeader from "../components/ui/RegistryHeader";
import StatusBadge from "../components/ui/StatusBadge";
import { activityLogApi } from "../services/api";
import { useToast } from "../ui/toast-context";

function displayAction(action) {
  return action.replaceAll(".", " ").replaceAll("_", " ");
}

function displayValue(value) {
  if (value === null || value === undefined || value === "") return "—";
  if (typeof value === "boolean") return value ? "Yes" : "No";
  return String(value);
}

export default function ActivityLogPage() {
  const toast = useToast();
  const [logs, setLogs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");

  useEffect(() => {
    let active = true;
    activityLogApi
      .list()
      .then((records) => active && setLogs(records))
      .catch((error) => active && toast.error(error.message))
      .finally(() => active && setLoading(false));
    return () => {
      active = false;
    };
  }, [toast]);

  const filtered = useMemo(() => {
    const query = search.trim().toLowerCase();
    if (!query) return logs;
    return logs.filter((log) =>
      [log.actor, log.subject, log.action, log.description, log.ipAddress].some(
        (value) => value?.toLowerCase().includes(query),
      ),
    );
  }, [logs, search]);

  const columns = [
    {
      key: "createdAt",
      label: "Date and Time",
      render: (log) =>
        new Intl.DateTimeFormat("en-PH", {
          dateStyle: "medium",
          timeStyle: "short",
        }).format(new Date(log.createdAt)),
    },
    {
      key: "actor",
      label: "User",
      render: (log) => (
        <div className="flex min-w-40 items-center gap-2">
          <span className="grid h-8 w-8 place-items-center rounded-full bg-sky-100 text-[11px] font-bold text-sky-700">
            {log.actorInitials}
          </span>
          <strong className="text-slate-700">{log.actor}</strong>
        </div>
      ),
    },
    {
      key: "action",
      label: "Activity",
      render: (log) => (
        <StatusBadge>{displayAction(log.action)}</StatusBadge>
      ),
    },
    {
      key: "description",
      label: "Description",
      render: (log) => (
        <span className="block min-w-80 text-slate-700">{log.description}</span>
      ),
    },
    {
      key: "changes",
      label: "Recorded Changes",
      render: (log) => {
        const changes = Object.entries(log.newValues ?? {}).filter(
          ([key, value]) =>
            JSON.stringify(log.oldValues?.[key]) !== JSON.stringify(value),
        );

        if (changes.length === 0)
          return <span className="text-slate-400">—</span>;

        return (
          <div className="grid min-w-64 gap-1 text-xs">
            {changes.slice(0, 4).map(([key, value]) => (
              <span key={key}>
                <strong className="capitalize text-slate-600">
                  {key.replaceAll(/([A-Z])/g, " $1")}:
                </strong>{" "}
                <span className="text-red-500">
                  {displayValue(log.oldValues?.[key])}
                </span>{" "}
                → <span className="text-emerald-700">{displayValue(value)}</span>
              </span>
            ))}
            {changes.length > 4 && (
              <span className="text-slate-400">
                +{changes.length - 4} more changes
              </span>
            )}
          </div>
        );
      },
    },
    { key: "ipAddress", label: "IP Address" },
  ];

  return (
    <div className="p-4 sm:p-6">
      <RegistryHeader
        description="Sign-ins, sign-outs, profile changes, password changes, and user administration activity."
        icon={Activity}
        readOnly
        title="Activity Log"
      />
      <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <header className="flex flex-wrap items-center justify-between gap-3 border-b p-4">
          <strong>{filtered.length} recorded activities</strong>
          <label className="flex h-10 w-full max-w-sm items-center gap-2 rounded-lg border px-3 text-slate-500">
            <Search size={17} />
            <input
              className="min-w-0 flex-1 outline-none"
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Search activity..."
              value={search}
            />
          </label>
        </header>
        <DataTable columns={columns} loading={loading} rows={filtered} />
      </section>
    </div>
  );
}
