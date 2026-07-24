export default function Brand({ collapsed = false }) {
  return (
    <div className="flex min-w-0 items-center gap-3">
      <img
        className="h-14 w-14 shrink-0 object-contain"
        src="/logo.png"
        alt="City Internal Audit Department"
      />
      {!collapsed && (
        <div className="min-w-0 text-white">
          <strong className="block text-2xl leading-none tracking-wide">
            AGIS
          </strong>
          <small className="mt-1 block max-w-32 text-[10px] leading-tight text-blue-100">
            Audit Governance Information System
          </small>
        </div>
      )}
    </div>
  );
}
