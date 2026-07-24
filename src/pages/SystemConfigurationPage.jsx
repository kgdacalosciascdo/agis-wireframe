import { useEffect, useMemo, useState } from "react";
import { Save, Settings } from "lucide-react";
import { useAuth } from "../auth/auth-context";
import ConfirmDialog from "../components/ui/ConfirmDialog";
import RegistryHeader from "../components/ui/RegistryHeader";
import { hasPermission } from "../config/navigation";
import { configurationApi } from "../services/api";
import { useToast } from "../ui/toast-context";

export default function SystemConfigurationPage() {
  const { user } = useAuth();
  const toast = useToast();
  const [configurations, setConfigurations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [confirmOpen, setConfirmOpen] = useState(false);
  const canManage = hasPermission(user, "system_configuration.manage");

  useEffect(() => {
    let active = true;
    configurationApi
      .list()
      .then((records) => active && setConfigurations(records))
      .catch((error) => active && toast.error(error.message))
      .finally(() => active && setLoading(false));
    return () => {
      active = false;
    };
  }, [toast]);

  const groups = useMemo(
    () =>
      configurations.reduce((result, configuration) => {
        const current = result[configuration.group] ?? [];
        return {
          ...result,
          [configuration.group]: [...current, configuration],
        };
      }, {}),
    [configurations],
  );

  function change(key, value) {
    setConfigurations((current) =>
      current.map((configuration) =>
        configuration.key === key ? { ...configuration, value } : configuration,
      ),
    );
  }

  async function save() {
    setSaving(true);
    try {
      await configurationApi.update(
        configurations.map(({ key, value }) => ({ key, value })),
      );
      setConfirmOpen(false);
      toast.success("System configuration updated successfully.");
    } catch (error) {
      toast.error(error.message);
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="p-4 sm:p-6">
      <RegistryHeader
        actions={
          canManage && (
            <button
              className="flex h-11 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white"
              disabled={saving}
              onClick={() => setConfirmOpen(true)}
              type="button"
            >
              <Save size={17} /> {saving ? "Saving..." : "Save settings"}
            </button>
          )
        }
        description="Manage organization-wide display, regional, security, and platform defaults."
        icon={Settings}
        readOnly={!canManage}
        title="System Configuration"
      />
      <div className="grid gap-4 xl:grid-cols-2">
        {loading && (
          <div className="h-72 animate-pulse rounded-xl bg-white shadow-sm" />
        )}
        {!loading &&
          Object.entries(groups).map(([group, items]) => (
            <section
              className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
              key={group}
            >
              <h3 className="mb-4 text-sm font-bold uppercase tracking-wide text-slate-600">
                {group}
              </h3>
              <div className="grid gap-4">
                {items.map((configuration) => (
                  <label key={configuration.key}>
                    <span className="text-sm font-bold text-slate-700">
                      {configuration.name}
                    </span>
                    <small className="mt-0.5 block text-xs leading-5 text-slate-500">
                      {configuration.description}
                    </small>
                    <input
                      className="mt-2 h-11 w-full rounded-lg border border-slate-300 px-3 text-sm outline-none focus:border-sky-500 disabled:bg-slate-100"
                      disabled={!canManage}
                      onChange={(event) =>
                        change(configuration.key, event.target.value)
                      }
                      type={
                        configuration.type === "integer" ? "number" : "text"
                      }
                      value={configuration.value}
                    />
                  </label>
                ))}
              </div>
            </section>
          ))}
      </div>
      <ConfirmDialog
        busy={saving}
        confirmLabel="Save settings"
        description="These organization-wide settings may affect all AGIS users and modules."
        onCancel={() => setConfirmOpen(false)}
        onConfirm={save}
        open={confirmOpen}
        title="Update system configuration?"
        tone="warning"
      />
    </div>
  );
}
