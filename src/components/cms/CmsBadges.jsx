import { AlertTriangle, CheckCircle2, CircleDot } from "lucide-react";
import StatusBadge from "../ui/StatusBadge";
import { labelFor } from "./cms-format";

export function CmsStatusBadge({ status }) {
  const completed = ["CLOSED", "ACCEPTED_RISK"].includes(status);
  const cancelled = status === "CANCELLED";
  const Icon = completed ? CheckCircle2 : cancelled ? AlertTriangle : CircleDot;

  return (
    <StatusBadge tone={completed ? "success" : cancelled ? "danger" : "info"}>
      <Icon className="mr-1" size={13} aria-hidden="true" />
      {labelFor(status, "Unknown")}
    </StatusBadge>
  );
}

export function CmsRiskBadge({ risk }) {
  const code = risk?.code?.toUpperCase();
  const tone =
    code === "CRITICAL" || code === "VERY_HIGH"
      ? "danger"
      : code === "HIGH"
        ? "warning"
        : code === "LOW"
          ? "success"
          : "info";

  return <StatusBadge tone={tone}>{risk?.label || labelFor(code)}</StatusBadge>;
}

export function CmsOverdueBadge({ overdue }) {
  if (!overdue) return null;

  return (
    <StatusBadge tone="danger">
      <AlertTriangle className="mr-1" size={13} aria-hidden="true" />
      Overdue
    </StatusBadge>
  );
}
