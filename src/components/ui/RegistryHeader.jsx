export default function RegistryHeader({
  icon: Icon,
  title,
  description,
  readOnly = false,
  actions,
}) {
  return (
    <header className="mb-5 flex flex-wrap items-start justify-between gap-4">
      <div>
        <div className="flex items-center gap-3">
          <span className="grid h-11 w-11 place-items-center rounded-xl bg-sky-100 text-sky-700">
            <Icon size={23} />
          </span>
          <div>
            <h2 className="text-xl font-bold text-slate-800 sm:text-2xl">
              {title}
            </h2>
            <p className="mt-0.5 max-w-3xl text-sm leading-5 text-slate-500">
              {description}
            </p>
          </div>
        </div>
        {readOnly && (
          <span className="mt-3 inline-flex rounded-lg bg-amber-50 px-3 py-2 text-xs font-bold text-amber-800 ring-1 ring-amber-200">
            Your role has view-only access.
          </span>
        )}
      </div>
      {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
    </header>
  );
}
