export default function RegistryHeader({
  icon: Icon,
  title,
  description,
  readOnly = false,
  actions,
}) {
  return (
    <header className="mb-5 flex min-w-0 flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
      <div className="min-w-0 flex-1">
        <div className="flex min-w-0 items-start gap-3">
          <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-sky-100 text-sky-700 sm:h-11 sm:w-11">
            <Icon size={22} />
          </span>
          <div className="min-w-0 flex-1">
            <h2 className="break-words text-xl font-bold leading-tight text-slate-800 sm:text-2xl">
              {title}
            </h2>
            <p className="mt-1 max-w-3xl text-xs leading-5 text-slate-500 sm:text-sm">
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
      {actions && (
        <div className="flex w-full flex-wrap gap-2 [&>button]:w-full sm:w-auto sm:[&>button]:w-auto">
          {actions}
        </div>
      )}
    </header>
  );
}
