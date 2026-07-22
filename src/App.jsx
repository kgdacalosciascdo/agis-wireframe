import { useEffect, useMemo, useState } from "react";
import users from "./data/users.json";
import "./App.css";
import "./Login.css";
import "./Dashboard.css";

const referenceModules = [
  {
    key: "iap",
    icon: "calendar",
    label: "Internal Audit Planning",
    value: 3,
    note: "Plans in Progress",
    tone: "blue",
  },
  {
    key: "aems",
    icon: "shield",
    label: "Audit Engagement Management",
    value: 18,
    note: "Active Engagements",
    tone: "green",
  },
  {
    key: "afr",
    icon: "finding",
    label: "Audit Finding & Recommendation",
    value: 43,
    note: "Findings Issued",
    tone: "orange",
  },
  {
    key: "cms",
    icon: "check",
    label: "Compliance Management",
    value: 21,
    note: "Open Recommendations",
    tone: "purple",
  },
  {
    key: "armis",
    icon: "users",
    label: "Audit Resource Management",
    value: 12,
    note: "Active Auditors",
    tone: "teal",
  },
  {
    key: "ais",
    icon: "chart",
    label: "Audit Intelligence System",
    value: 8,
    note: "Key Insights",
    tone: "yellow",
  },
];

const dashboardContent = {
  system_admin: {
    eyebrow: "System overview",
    greeting: "Keep AGIS secure, available, and ready for every audit team.",
    modules: referenceModules,
    tasks: [
      ["Prepare Annual Internal Audit Plan", "IAP", "May 20", "IAP-2025"],
      ["Review Working Papers", "AEMS", "May 21", "AEMS-2025-007"],
      ["Validate Recommendation Actions", "REC", "May 22", "REC-2025-041"],
      ["Respond to Management Comment", "FND", "May 23", "FND-2025-038"],
    ],
    quickActions: ["Create user", "Manage roles", "Review audit trail"],
    activity: [
      [
        "User role updated",
        "Marrisa Barcelona was assigned as Lead Auditor",
        "12 min ago",
      ],
      ["Workflow published", "AEMS report approval v2.1", "1 hr ago"],
      [
        "Data refresh complete",
        "AIS refreshed 2,840 governed records",
        "3 hrs ago",
      ],
    ],
  },
  audit_manager: {
    eyebrow: "Executive audit overview",
    greeting: "Here’s what’s happening with your audit activities today.",
    modules: referenceModules,
    tasks: [
      ["Prepare Annual Internal Audit Plan", "IAP", "May 20", "IAP-2025"],
      ["Review Working Papers", "AEMS", "May 21", "AEMS-2025-007"],
      ["Validate Recommendation Actions", "REC", "May 22", "REC-2025-041"],
      ["Respond to Management Comment", "FND", "May 23", "FND-2025-038"],
    ],
    quickActions: ["Create plan", "Start engagement", "Generate report"],
    activity: [
      [
        "Engagement moved to fieldwork",
        "Business Tax Collection Process",
        "24 min ago",
      ],
      [
        "Finding submitted for review",
        "F-REV-01 · Revenue controls",
        "2 hrs ago",
      ],
      [
        "Recommendation validated",
        "R-REV-04 · Supporting documents",
        "Yesterday",
      ],
    ],
  },
  internal_auditor: {
    eyebrow: "My audit workspace",
    greeting: "Your assignments, working papers, and deadlines are ready.",
    modules: referenceModules,
    tasks: [
      ["Complete cash receipt testing", "AEMS", "Today"],
      ["Revise WP-REV-04", "AEMS", "Jul 24"],
      ["Upload supporting evidence", "AEMS", "Jul 25"],
      ["Submit weekly time record", "ARMIS", "Jul 26"],
    ],
    quickActions: ["New working paper", "Upload evidence", "Log hours"],
    activity: [
      [
        "Working paper returned",
        "WP-REV-04 requires one revision",
        "16 min ago",
      ],
      ["Evidence accepted", "EV-REV-019 · Deposit slips", "1 hr ago"],
      [
        "Assignment updated",
        "Revenue audit deadline moved to Jul 31",
        "Yesterday",
      ],
    ],
  },
};

const primaryNavigation = [
  { label: "Dashboard", icon: "grid" },
  { label: "Internal Audit Planning", icon: "calendar" },
  { label: "Audit Engagement Management", icon: "shield" },
  { label: "Compliance Management", icon: "check" },
  { label: "Audit Resource Management", icon: "users" },
  { label: "Audit Intelligence System", icon: "chart" },
];

const registryNavigation = [
  { label: "Office Registry", icon: "office" },
  { label: "Audit Area Registry", icon: "area" },
  { label: "Audit Focus Registry", icon: "target" },
  { label: "User Registry", icon: "user" },
  { label: "Access Role Registry", icon: "key" },
  { label: "Permission Registry", icon: "checklist" },
  { label: "Master Lists", icon: "database" },
];

const administrationNavigation = [
  { label: "Document Management", icon: "file" },
  { label: "Notifications", icon: "bell" },
  { label: "Activity Log", icon: "activity" },
  { label: "Audit Trail", icon: "trail" },
  { label: "System Configuration", icon: "settings" },
  { label: "Administrative Reports", icon: "report" },
];

function Icon({ name, size = 20, strokeWidth = 1.8 }) {
  const paths = {
    grid: (
      <>
        <rect x="3" y="3" width="7" height="7" rx="1" />
        <rect x="14" y="3" width="7" height="7" rx="1" />
        <rect x="3" y="14" width="7" height="7" rx="1" />
        <rect x="14" y="14" width="7" height="7" rx="1" />
      </>
    ),
    calendar: (
      <>
        <rect x="3" y="5" width="18" height="16" rx="2" />
        <path d="M16 3v4M8 3v4M3 10h18" />
      </>
    ),
    shield: (
      <>
        <path d="M12 22s8-3.8 8-10V5l-8-3-8 3v7c0 6.2 8 10 8 10Z" />
        <path d="m8.5 12 2.2 2.2 4.8-5" />
      </>
    ),
    finding: (
      <>
        <path d="M11 21s-7-3.4-7-9V5l7-3 7 3v5" />
        <circle cx="15" cy="14" r="4" />
        <path d="m18 17 3 3" />
      </>
    ),
    search: (
      <>
        <circle cx="11" cy="11" r="7" />
        <path d="m20 20-4-4M11 8v6M8 11h6" />
      </>
    ),
    check: (
      <>
        <rect x="3" y="3" width="18" height="18" rx="3" />
        <path d="m7 12 3 3 7-7" />
      </>
    ),
    users: (
      <>
        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
        <circle cx="9" cy="7" r="4" />
        <path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
      </>
    ),
    bell: (
      <>
        <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" />
        <path d="M10 21h4" />
      </>
    ),
    logout: (
      <>
        <path d="M10 17l5-5-5-5M15 12H3" />
        <path d="M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5" />
      </>
    ),
    eye: (
      <>
        <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" />
        <circle cx="12" cy="12" r="2.5" />
      </>
    ),
    eyeOff: (
      <>
        <path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 4.2A10.8 10.8 0 0 1 12 4c6.5 0 10 8 10 8a17.5 17.5 0 0 1-2.1 3.2M6.6 6.6C3.6 8.6 2 12 2 12s3.5 8 10 8a9.7 9.7 0 0 0 4-.8" />
      </>
    ),
    user: (
      <>
        <circle cx="12" cy="8" r="4" />
        <path d="M4 21a8 8 0 0 1 16 0" />
      </>
    ),
    lock: (
      <>
        <rect x="4" y="10" width="16" height="11" rx="2" />
        <path d="M8 10V7a4 4 0 0 1 8 0v3" />
      </>
    ),
    key: (
      <>
        <circle cx="8" cy="15" r="4" />
        <path d="m11 12 8-8M16 7l2 2M14 9l2 2" />
      </>
    ),
    chevron: <path d="m9 18 6-6-6-6" />,
    chevronUp: <path d="m6 15 6-6 6 6" />,
    chevronDown: <path d="m6 9 6 6 6-6" />,
    arrow: <path d="M4 12h16m-5-5 5 5-5 5" />,
    briefcase: (
      <>
        <rect x="3" y="7" width="18" height="13" rx="2" />
        <path d="M8 7V4h8v3M3 12h18M10 12v2h4v-2" />
      </>
    ),
    file: (
      <>
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" />
        <path d="M14 2v6h6M8 13h8M8 17h6" />
      </>
    ),
    paperclip: (
      <path d="m21.4 11.6-8.9 8.9a6 6 0 0 1-8.5-8.5l9.6-9.6a4 4 0 0 1 5.7 5.7l-9.6 9.6a2 2 0 0 1-2.8-2.8l8.9-8.9" />
    ),
    clock: (
      <>
        <circle cx="12" cy="12" r="9" />
        <path d="M12 7v5l3 2" />
      </>
    ),
    menu: <path d="M4 7h16M4 12h16M4 17h16" />,
    plus: <path d="M12 5v14M5 12h14" />,
    chart: (
      <>
        <path d="M4 20V10M10 20V4M16 20v-7M22 20V7" />
        <path d="m3 8 6-5 6 7 7-6" />
      </>
    ),
    office: (
      <>
        <rect x="4" y="3" width="16" height="18" rx="1" />
        <path d="M8 7h2M14 7h2M8 11h2M14 11h2M8 15h2M14 15h2M10 21v-3h4v3" />
      </>
    ),
    area: (
      <>
        <circle cx="6" cy="6" r="2" />
        <circle cx="18" cy="5" r="2" />
        <circle cx="7" cy="18" r="2" />
        <circle cx="18" cy="17" r="2" />
        <path d="m8 7 8-1M7 8v8M9 17h7M16.5 7l1 8" />
      </>
    ),
    target: (
      <>
        <circle cx="12" cy="12" r="9" />
        <circle cx="12" cy="12" r="5" />
        <circle cx="12" cy="12" r="1" />
      </>
    ),
    checklist: (
      <>
        <path d="m3 6 2 2 3-4M3 13l2 2 3-4M3 20l2 2 3-4M11 6h10M11 13h10M11 20h10" />
      </>
    ),
    database: (
      <>
        <ellipse cx="12" cy="5" rx="8" ry="3" />
        <path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6" />
      </>
    ),
    master: (
      <>
        <circle cx="12" cy="5" r="2" />
        <circle cx="5" cy="18" r="2" />
        <circle cx="19" cy="18" r="2" />
        <path d="M11 7 6 16M13 7l5 9M7 18h10" />
      </>
    ),
    document: (
      <>
        <rect x="4" y="3" width="16" height="18" rx="2" />
        <path d="M8 3v18M11 8h5M11 12h5M11 16h4" />
      </>
    ),
    log: (
      <>
        <path d="M9 6h11M9 12h11M9 18h11" />
        <circle cx="4" cy="6" r="1" />
        <circle cx="4" cy="12" r="1" />
        <circle cx="4" cy="18" r="1" />
      </>
    ),
    footprints: (
      <>
        <ellipse cx="8" cy="7" rx="2.5" ry="4" transform="rotate(-25 8 7)" />
        <ellipse
          cx="16"
          cy="17"
          rx="2.5"
          ry="4"
          transform="rotate(-25 16 17)"
        />
        <circle cx="5" cy="2.5" r=".7" />
        <circle cx="12.5" cy="12" r=".7" />
        <circle cx="15" cy="11" r=".7" />
      </>
    ),
    activity: <path d="M3 12h4l2-7 4 14 2-7h6" />,
    trail: (
      <>
        <circle cx="6" cy="6" r="2" />
        <circle cx="18" cy="18" r="2" />
        <path d="M8 7c7 0 1 10 8 10" />
      </>
    ),
    settings: (
      <>
        <circle cx="12" cy="12" r="3" />
        <path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H3v-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3A1.7 1.7 0 0 0 10 3V3h4v.1a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z" />
      </>
    ),
    report: (
      <>
        <rect x="3" y="4" width="18" height="16" rx="2" />
        <path d="M7 16v-4M12 16V8M17 16v-6" />
      </>
    ),
    help: (
      <>
        <circle cx="12" cy="12" r="9" />
        <path d="M9.8 9a2.5 2.5 0 1 1 3.7 2.2c-1 .5-1.5 1-1.5 2M12 17h.01" />
      </>
    ),
  };

  return (
    <svg
      viewBox="0 0 24 24"
      width={size}
      height={size}
      fill="none"
      stroke="currentColor"
      strokeWidth={strokeWidth}
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      {paths[name] || paths.grid}
    </svg>
  );
}

function Brand({ compact = false }) {
  return (
    <div className={`brand ${compact ? "brand--compact" : ""}`}>
      <div className="brand__seal">
        <img src="/logo.png" alt="" />
      </div>
      <div>
        <strong>AGIS</strong>
        <small>Audit Governance Information System</small>
      </div>
    </div>
  );
}

function LoginPage({ onLogin }) {
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [remember, setRemember] = useState(true);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  const submit = (event) => {
    event.preventDefault();
    setError("");
    const match = users.find(
      (candidate) =>
        candidate.username.toLowerCase() === username.trim().toLowerCase() &&
        candidate.password === password,
    );
    if (!match) {
      setError(
        "The username or password is incorrect. Try a demo account below.",
      );
      return;
    }
    setBusy(true);
    window.setTimeout(() => onLogin(match, remember), 450);
  };

  const chooseDemo = (demoUser) => {
    setUsername(demoUser.username);
    setPassword(demoUser.password);
    setError("");
  };

  return (
    <main className="login-page reference-login">
      <section className="login-visual" aria-label="AGIS introduction">
        <div className="login-visual__logos">
          <img
            className="city-logo"
            src="/cdologo.png"
            alt="City of Cagayan de Oro"
          />
          <img
            className="rise-logo"
            src="/rise.png"
            alt="CdeO RISE governance platform"
          />
        </div>
      </section>

      <section className="login-panel">
        <div className="login-panel__inner">
          <div className="login-card-brand">
            <img src="/logo.png" alt="City Internal Audit Department" />
            <h1>Audit Governance Information System</h1>
          </div>
          <div className="login-card-divider" />
          <div className="login-heading">
            <h2>Sign in to your account</h2>
            <p>Enter your credentials to access AGIS.</p>
          </div>
          <form onSubmit={submit} className="login-form">
            <label htmlFor="username">Username</label>
            <div className="input-wrap">
              <Icon name="user" size={21} />
              <input
                id="username"
                autoComplete="username"
                value={username}
                onChange={(event) => setUsername(event.target.value)}
                placeholder="Enter your username"
                required
              />
            </div>
            <label htmlFor="password">Password</label>
            <div className="input-wrap">
              <Icon name="key" size={21} />
              <input
                id="password"
                type={showPassword ? "text" : "password"}
                autoComplete="current-password"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                placeholder="Enter your password"
                required
              />
              <button
                className="password-toggle"
                type="button"
                onClick={() => setShowPassword((visible) => !visible)}
                aria-label={showPassword ? "Hide password" : "Show password"}
              >
                <Icon name={showPassword ? "eyeOff" : "eye"} size={20} />
              </button>
            </div>
            <div className="form-options">
              <label className="checkbox-row">
                <input
                  type="checkbox"
                  checked={remember}
                  onChange={(event) => setRemember(event.target.checked)}
                />
                <span>Remember me</span>
              </label>
              <button
                type="button"
                className="text-button"
                onClick={() =>
                  setError(
                    "For this prototype, please contact your AGIS administrator to reset a password.",
                  )
                }
              >
                Forgot password?
              </button>
            </div>
            {error && (
              <div className="login-error" role="alert">
                {error}
              </div>
            )}
            <button className="sign-in-button" type="submit" disabled={busy}>
              {busy ? <span className="spinner" /> : "Sign in"}
            </button>
          </form>
          <div className="demo-accounts">
            <div className="demo-accounts__title">
              <span>Demo accounts</span>
            </div>
            <div className="demo-account-list">
              {users.map((demoUser) => (
                <button
                  type="button"
                  key={demoUser.id}
                  onClick={() => chooseDemo(demoUser)}
                >
                  <span className="demo-avatar">{demoUser.initials}</span>
                  <span>
                    <strong>{demoUser.role}</strong>
                    <em title={demoUser.name}>{demoUser.name}</em>
                    <small>
                      {demoUser.username} / {demoUser.password}
                    </small>
                  </span>
                  <Icon name="chevron" size={16} />
                </button>
              ))}
            </div>
          </div>
          <p className="prototype-note">
            Prototype only — credentials are stored locally in JSON.
          </p>
        </div>
      </section>
      <footer className="login-page-footer">
        <div>
          <span>AGIS v1.0.0</span>
          <i />
          <span>City Internal Audit Service (CIAS)</span>
          <i />
          <a href="#privacy">Privacy Policy</a>
          <i />
          <a href="#terms">Terms of Use</a>
        </div>
        <span>
          © 2026 City Government of Cagayan de Oro. All rights reserved.
        </span>
      </footer>
    </main>
  );
}

const moduleMeta = {
  core: ["CORE", "Master Registries"],
  iap: ["IAP", "Internal Audit Planning"],
  aems: ["AEM", "Audit Engagement Management"],
  afr: ["AFR", "Audit Findings & Recommendations"],
  cms: ["CMS", "Compliance Management"],
  armis: ["ARMIS", "Audit Resource Management"],
  ais: ["AIS", "Audit Intelligence System"],
  assignments: ["AEM", "Audit Engagement Management"],
  papers: ["AEM", "Audit Engagement Management"],
  findings: ["AFR", "Audit Findings & Recommendations"],
  evidence: ["AEM", "Audit Engagement Management"],
  hours: ["ARMIS", "Audit Resource Management"],
  insights: ["AIS", "Audit Intelligence System"],
};

const upcomingActivities = [
  ["Entrance Conference", "AEMS-2025-009 • CHUDD", "May 21", "10:00 AM"],
  [
    "Exit Conference",
    "AEMS-2025-005 • City Treasurer’s Office",
    "May 22",
    "2:00 PM",
  ],
  ["Draft Audit Report Due", "AEMS-2025-006 • CIO", "May 23", "5:00 PM"],
  ["Team Meeting", "CIAS Conference Room", "May 26", "9:00 AM"],
];

const recentEngagements = [
  ["AEMS-2025-009", "CHUDD", "Planning", "15%", "Medium"],
  ["AEMS-2025-008", "City Engineer’s Office", "Execution", "45%", "High"],
  ["AEMS-2025-007", "BPLD", "Execution", "68%", "Medium"],
  ["AEMS-2025-006", "CIO", "Reporting", "85%", "High"],
  ["AEMS-2025-005", "City Treasurer’s Office", "Reporting", "90%", "Medium"],
];

const overdueItems = [
  ["REC-2025-021", "BPLD", "15 days"],
  ["REC-2025-015", "CHUDD", "10 days"],
  ["REC-2025-011", "CTO", "7 days"],
  ["REC-2025-008", "CEO", "3 days"],
];

function ModuleCard({ module, index, onOpen }) {
  const [code, page] = moduleMeta[module.key] || ["OPEN", "Dashboard"];
  return (
    <article
      className={`module-card module-card--${module.tone}`}
      style={{ "--delay": `${index * 55}ms` }}
    >
      <span className="module-icon">
        <Icon name={module.icon} size={47} strokeWidth={1.6} />
      </span>
      <div className="module-card__label">
        <strong>
          {module.label} <small>({code})</small>
        </strong>
      </div>
      <div className="module-card__value">{module.value}</div>
      <span className="module-card__status">{module.note}</span>
      <button type="button" onClick={() => onOpen(page)}>
        Open {code}
        <Icon name="arrow" size={15} />
      </button>
    </article>
  );
}

function StatusDonut({ title, total, segments, gradient, onView }) {
  return (
    <article className="panel status-panel">
      <div className="panel__header">
        <h2>{title}</h2>
        <button onClick={onView}>View Report</button>
      </div>
      <div className="status-panel__body">
        <div className="status-donut" style={{ background: gradient }}>
          <div>
            <strong>{total}</strong>
            <span>Total</span>
          </div>
        </div>
        <div className="status-legend">
          {segments.map((segment) => (
            <span key={segment.label}>
              <i style={{ background: segment.color }} />
              <em>{segment.label}</em>
              <strong>
                {segment.value} <small>({segment.percent}%)</small>
              </strong>
            </span>
          ))}
        </div>
      </div>
    </article>
  );
}

function ComingSoonPage({ page, onBack }) {
  const lowerPage = page.toLowerCase();
  const icon = lowerPage.includes("planning")
    ? "calendar"
    : lowerPage.includes("engagement")
      ? "shield"
      : lowerPage.includes("compliance")
        ? "check"
        : lowerPage.includes("resource")
          ? "users"
          : lowerPage.includes("intelligence")
            ? "chart"
            : "settings";
  return (
    <section className="coming-soon-page">
      <div className="coming-soon-card">
        <div className="coming-soon-orbit">
          <span>
            <Icon name={icon} size={34} />
          </span>
        </div>
        <span className="coming-soon-badge">Module in development</span>
        <h1>{page} is coming soon</h1>
        <p>
          This AGIS workspace is currently being prepared and will be available
          in a future update.
        </p>
        <div className="coming-soon-progress">
          <i />
        </div>
        <small>The AGIS team is building this workspace.</small>
        <button type="button" onClick={onBack}>
          <Icon name="grid" size={17} />
          Back to dashboard
        </button>
      </div>
    </section>
  );
}

function NavigationGroup({
  title,
  items,
  activePage,
  onOpen,
  expanded = true,
  onToggle,
}) {
  return (
    <div className={`sidebar-group ${expanded ? "" : "sidebar-group--closed"}`}>
      {title && (
        <button
          className="sidebar-group__title"
          type="button"
          onClick={onToggle}
          aria-expanded={expanded}
        >
          <span>{title}</span>
          <Icon name={expanded ? "chevronUp" : "chevronDown"} size={16} />
        </button>
      )}
      <div className="sidebar-group__items">
        {items.map((item) => (
          <button
            key={item.label}
            title={item.label}
            className={activePage === item.label ? "active" : ""}
            onClick={() => onOpen(item.label)}
          >
            <Icon name={item.icon} size={20} />
            <span>{item.label}</span>
          </button>
        ))}
      </div>
    </div>
  );
}

function Dashboard({ user, onLogout }) {
  const content =
    dashboardContent[user.roleCode] || dashboardContent.internal_auditor;
  const [profileOpen, setProfileOpen] = useState(false);
  const [notice, setNotice] = useState("");
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [sidebarMode, setSidebarMode] = useState("expanded");
  const [registriesExpanded, setRegistriesExpanded] = useState(true);
  const [administrationExpanded, setAdministrationExpanded] = useState(true);
  const [completedTasks, setCompletedTasks] = useState([]);
  const [activePage, setActivePage] = useState("Dashboard");
  const today = useMemo(
    () =>
      new Intl.DateTimeFormat("en-PH", {
        weekday: "long",
        month: "long",
        day: "numeric",
        year: "numeric",
      }).format(new Date()),
    [],
  );

  useEffect(() => {
    if (!notice) return undefined;
    const timeout = window.setTimeout(() => setNotice(""), 2400);
    return () => window.clearTimeout(timeout);
  }, [notice]);

  const markTask = (index) =>
    setCompletedTasks((current) =>
      current.includes(index)
        ? current.filter((item) => item !== index)
        : [...current, index],
    );
  const openPage = (page) => {
    setActivePage(page);
    setSidebarOpen(false);
  };
  const showNotice = (message) => setNotice(message);
  const toggleSidebarCollapse = () =>
    setSidebarMode((current) =>
      current === "expanded" ? "collapsed" : "expanded",
    );

  const engagementSegments = [
    { label: "Planning", value: 4, percent: 22, color: "#4775b8" },
    { label: "Execution", value: 7, percent: 39, color: "#16ad62" },
    { label: "Reporting", value: 4, percent: 22, color: "#ffa01c" },
    { label: "Completed", value: 2, percent: 11, color: "#6840a2" },
    { label: "Closed", value: 1, percent: 6, color: "#a9b5c5" },
  ];
  const recommendationSegments = [
    { label: "Completed", value: 7, percent: 33, color: "#16ad62" },
    { label: "In Progress", value: 8, percent: 38, color: "#4775b8" },
    { label: "Overdue", value: 4, percent: 19, color: "#ffa01c" },
    { label: "Closed", value: 2, percent: 10, color: "#a9b5c5" },
  ];

  return (
    <main
      className={`dashboard-shell reference-dashboard sidebar-shell--${sidebarMode}`}
    >
      <aside className={`sidebar ${sidebarOpen ? "sidebar--open" : ""}`}>
        <div className="sidebar__brand">
          <Brand />
        </div>
        <div className="sidebar__nav">
          <NavigationGroup
            items={primaryNavigation}
            activePage={activePage}
            onOpen={openPage}
          />
          <NavigationGroup
            title="Master Registries"
            items={registryNavigation}
            activePage={activePage}
            onOpen={openPage}
            expanded={registriesExpanded}
            onToggle={() => setRegistriesExpanded((current) => !current)}
          />
          <NavigationGroup
            title="Administration"
            items={administrationNavigation}
            activePage={activePage}
            onOpen={openPage}
            expanded={administrationExpanded}
            onToggle={() => setAdministrationExpanded((current) => !current)}
          />
        </div>
        <div className="sidebar__account">
          <span className="avatar">{user.initials}</span>
          <span>
            <strong>{user.name}</strong>
            <small>{user.role}</small>
          </span>
        </div>
        <button className="sidebar__logout" onClick={onLogout}>
          <Icon name="logout" size={17} />
          Logout
        </button>
      </aside>
      {sidebarOpen && (
        <button
          className="sidebar-backdrop"
          aria-label="Close menu"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      <section className="dashboard-main">
        <header className="topbar">
          <button
            className="menu-button"
            onClick={() => setSidebarOpen(true)}
            aria-label="Open navigation"
          >
            <Icon name="menu" />
          </button>
          <div className="topbar__title">
            <button
              className="topbar__app-icon sidebar-collapse-button"
              onClick={toggleSidebarCollapse}
              aria-label={
                sidebarMode === "collapsed"
                  ? "Expand sidebar"
                  : "Collapse sidebar"
              }
              title={
                sidebarMode === "collapsed"
                  ? "Expand sidebar"
                  : "Collapse sidebar"
              }
            >
              <Icon name="grid" size={29} />
            </button>
            <strong>{activePage}</strong>
          </div>
          <div className="topbar__actions">
            <label className="search-box">
              <Icon name="search" size={17} />
              <input placeholder="Search anything..." aria-label="Search" />
              <kbd>Ctrl + K</kbd>
            </label>
            <button
              className="notification-button"
              aria-label="Notifications"
              onClick={() => showNotice("You have 6 unread notifications.")}
            >
              <Icon name="bell" size={20} />
              <i>6</i>
            </button>
            <button
              className="help-button"
              aria-label="Help"
              onClick={() => showNotice("Help center is coming soon.")}
            >
              <Icon name="help" size={20} />
            </button>
            <div className="profile-menu">
              <button
                className="profile-button"
                onClick={() => setProfileOpen((open) => !open)}
                aria-expanded={profileOpen}
              >
                <span className="avatar">{user.initials}</span>
                <span>
                  <strong>{user.name}</strong>
                  <small>{user.role}</small>
                </span>
                <span className="profile-caret">⌄</span>
              </button>
              {profileOpen && (
                <div className="profile-popover">
                  <div>
                    <strong>{user.name}</strong>
                    <span>{user.office}</span>
                  </div>
                  <button onClick={onLogout}>
                    <Icon name="logout" size={17} />
                    Sign out
                  </button>
                </div>
              )}
            </div>
          </div>
        </header>

        <div className="dashboard-content">
          {activePage === "Dashboard" ? (
            <div className="reference-content">
              <section className="welcome-row">
                <div>
                  <h1>Welcome back, {user.name}!</h1>
                  <p>{content.greeting}</p>
                </div>
                <time>
                  <Icon name="calendar" size={14} />
                  {today}
                </time>
              </section>
              <section className="module-grid" aria-label="Module summaries">
                {content.modules.map((module, index) => (
                  <ModuleCard
                    key={module.key}
                    module={module}
                    index={index}
                    onOpen={openPage}
                  />
                ))}
              </section>

              <section className="operations-grid">
                <article className="panel tasks-panel">
                  <div className="panel__header">
                    <h2>My Tasks</h2>
                    <button
                      onClick={() =>
                        showNotice("All assigned tasks are displayed.")
                      }
                    >
                      View All
                    </button>
                  </div>
                  <div className="task-list">
                    {content.tasks.map(
                      ([task, module, due, reference], index) => (
                        <button
                          key={task}
                          className={
                            completedTasks.includes(index)
                              ? "task-row task-row--done"
                              : "task-row"
                          }
                          onClick={() => markTask(index)}
                        >
                          <span className="task-clipboard">
                            <Icon name="briefcase" size={20} />
                          </span>
                          <span className="task-name">
                            <strong>{task}</strong>
                            <small>
                              {reference ||
                                `${module}-2025-${String(index + 4).padStart(3, "0")}`}
                            </small>
                          </span>
                          <time>{due}</time>
                        </button>
                      ),
                    )}
                  </div>
                </article>
                <article className="panel activities-panel">
                  <div className="panel__header">
                    <h2>Upcoming Activities</h2>
                    <button
                      onClick={() =>
                        showNotice("Calendar view is coming soon.")
                      }
                    >
                      View Calendar
                    </button>
                  </div>
                  <div className="activity-schedule">
                    {upcomingActivities.map(([title, detail, date, time]) => (
                      <div key={title}>
                        <span>
                          <Icon name="calendar" size={18} />
                        </span>
                        <p>
                          <strong>{title}</strong>
                          <small>{detail}</small>
                        </p>
                        <time>
                          <b>{date}</b>
                          <small>{time}</small>
                        </time>
                      </div>
                    ))}
                  </div>
                </article>
                <article className="panel engagements-panel">
                  <div className="panel__header">
                    <h2>Recent Audit Engagements</h2>
                    <button
                      onClick={() =>
                        showNotice("Engagement listing is coming soon.")
                      }
                    >
                      View All
                    </button>
                  </div>
                  <div className="engagement-table">
                    <div className="engagement-table__head">
                      <span>Engagement No.</span>
                      <span>Office</span>
                      <span>Status</span>
                      <span>Progress</span>
                      <span>Risk Level</span>
                    </div>
                    {recentEngagements.map((row) => (
                      <div className="engagement-table__row" key={row[0]}>
                        {row.map((cell, index) => (
                          <span
                            key={cell}
                            className={
                              index === 4 && cell === "High" ? "risk-high" : ""
                            }
                          >
                            {cell}
                          </span>
                        ))}
                      </div>
                    ))}
                  </div>
                </article>
              </section>

              <section className="insights-grid">
                <StatusDonut
                  title="Audit Engagement Status"
                  total={18}
                  segments={engagementSegments}
                  gradient="conic-gradient(#4775b8 0 22%, #16ad62 22% 61%, #ffa01c 61% 83%, #6840a2 83% 94%, #a9b5c5 94%)"
                  onView={() =>
                    showNotice("Audit status report is coming soon.")
                  }
                />
                <StatusDonut
                  title="Recommendation Status (CMS)"
                  total={21}
                  segments={recommendationSegments}
                  gradient="conic-gradient(#16ad62 0 33%, #4775b8 33% 71%, #ffa01c 71% 90%, #a9b5c5 90%)"
                  onView={() =>
                    showNotice("Recommendation report is coming soon.")
                  }
                />
                <article className="panel overdue-panel">
                  <div className="panel__header">
                    <h2>Overdue Recommendations</h2>
                  </div>
                  <div className="overdue-count">
                    <strong>4</strong>
                    <span>Overdue Items</span>
                  </div>
                  <div className="overdue-list">
                    {overdueItems.map(([code, office, days]) => (
                      <div key={code}>
                        <i />
                        <span>{code}</span>
                        <b>{office}</b>
                        <em>{days}</em>
                      </div>
                    ))}
                  </div>
                </article>
                <article className="panel quick-panel">
                  <div className="panel__header">
                    <h2>Quick Actions</h2>
                  </div>
                  <div className="quick-actions">
                    <button
                      onClick={() => showNotice("New engagement selected.")}
                    >
                      <Icon name="plus" />
                      New Audit Engagement
                    </button>
                    <button onClick={() => showNotice("New finding selected.")}>
                      <Icon name="plus" />
                      New Finding
                    </button>
                    <button
                      onClick={() => showNotice("Document search selected.")}
                    >
                      <Icon name="search" />
                      Search Documents
                    </button>
                    <button
                      onClick={() => showNotice("Report generation selected.")}
                    >
                      <Icon name="file" />
                      Generate Report
                    </button>
                  </div>
                </article>
              </section>
            </div>
          ) : (
            <ComingSoonPage
              page={activePage}
              onBack={() => openPage("Dashboard")}
            />
          )}
        </div>
        <footer className="dashboard-footer">
          <span>AGIS v1.0.0</span>
          <span>City Internal Audit Service (CIAS)</span>
          <span>
            © 2026 City Government of Cagayan de Oro. All rights reserved.
          </span>
        </footer>
      </section>
      {notice && (
        <div className="toast" role="status">
          <span>✓</span>
          {notice}
        </div>
      )}
    </main>
  );
}

function App() {
  const [user, setUser] = useState(() => {
    const stored =
      localStorage.getItem("agis-user") || sessionStorage.getItem("agis-user");
    if (!stored) return null;
    try {
      const parsed = JSON.parse(stored);
      return users.find((item) => item.id === parsed.id) || null;
    } catch {
      return null;
    }
  });

  const login = (matchedUser, remember) => {
    const safeSession = JSON.stringify({ id: matchedUser.id });
    if (remember) localStorage.setItem("agis-user", safeSession);
    else sessionStorage.setItem("agis-user", safeSession);
    setUser(matchedUser);
  };

  const logout = () => {
    localStorage.removeItem("agis-user");
    sessionStorage.removeItem("agis-user");
    setUser(null);
  };

  return user ? (
    <Dashboard user={user} onLogout={logout} />
  ) : (
    <LoginPage onLogin={login} />
  );
}

export default App;
