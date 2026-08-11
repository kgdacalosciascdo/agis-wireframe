import { expect, test } from "@playwright/test";

async function signIn(page) {
  await page.goto("/login");
  await page.getByLabel("Employee ID").fill("CIAS-HEAD-001");
  await page.locator("#password").fill("lala");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
}

const discrepancy = {
  category: "CAPACITY",
  key: "CAPACITY:user:2:year:2026",
  subject: { userId: 2, resourceCode: "ARMIS-RES-001", fiscalYear: 2026 },
  iap: 80,
  armis: 120,
  status: "DISCREPANCY",
};

const match = {
  category: "SKILLS",
  key: "SKILLS:user:2",
  subject: { userId: 2, resourceCode: "ARMIS-RES-001" },
  iap: [{ code: "FIN" }],
  armis: [{ code: "FIN" }],
  status: "MATCH",
};

function run(overrides = {}) {
  return {
    id: 91,
    displayCode: "ARMIS-REC-000091",
    uuid: "provider-run-91",
    sourceQueryVersion: "ARMIS-6B-v1",
    fiscalYear: 2026,
    providerMode: "ARMIS_SHADOW",
    status: "GENERATED",
    resultSnapshot: [match, discrepancy],
    summary: { total: 2, matches: 1, discrepancies: 1, reviewRequired: true },
    scopeSnapshot: { officeIds: [1], globalOfficeScope: true },
    resultChecksumSha256: "abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789",
    generatedAt: "2026-08-11T09:00:00Z",
    generatedBy: { id: 88, name: "Reconciliation Generator" },
    reviews: [],
    authorityDecisions: [],
    ...overrides,
  };
}

async function mockProvider(page) {
  let currentRun = run();
  let mode = "ARMIS_SHADOW";
  await page.route(/\/api\/armis\/provider\/status$/, async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        success: true,
        data: {
          provider: { mode, activeProvider: "App\\Integrations\\Aems\\InterimIapResourcePlanningGateway", authoritative: mode === "ARMIS_AUTHORITATIVE" },
          latestReconciliation: { id: currentRun.id, displayCode: currentRun.displayCode, fiscalYear: 2026, providerMode: currentRun.providerMode, summary: currentRun.summary, generatedAt: currentRun.generatedAt },
          latestReview: currentRun.reviews[0] || null,
          authorityEligible: Boolean(currentRun.reviews[0]?.decision === "ACCEPTED" && mode !== "ARMIS_AUTHORITATIVE"),
          authorityControls: { genericConfigurationCannotSwitchAuthority: true },
        },
      }),
    });
  });
  await page.route(/\/api\/armis\/provider\/reconciliations$/, async (route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true, data: { runs: [currentRun] } }) });
  });
  await page.route(/\/api\/armis\/provider\/reconciliations\/91$/, async (route) => {
    await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ success: true, data: { run: currentRun } }) });
  });
  await page.route(/\/api\/armis\/provider\/reconciliations\/91\/review$/, async (route) => {
    const payload = route.request().postDataJSON();
    const review = { id: 12, reconciliationRunId: 91, decision: payload.decision, comment: payload.comment, discrepancyDecisions: payload.discrepancyDecisions, reviewedAt: "2026-08-11T10:00:00Z", reviewedBy: { id: 77, name: "Independent Reviewer" } };
    currentRun = run({ reviews: [review] });
    await route.fulfill({ status: 201, contentType: "application/json", body: JSON.stringify({ success: true, data: { review } }) });
  });
  await page.route(/\/api\/armis\/provider\/reconciliations\/91\/activate$/, async (route) => {
    mode = "ARMIS_AUTHORITATIVE";
    const decision = { id: 13, reconciliationRunId: 91, decisionCode: "ACTIVATE_ARMIS", fromMode: "ARMIS_SHADOW", toMode: mode, reason: "Authority approval recorded after accepted independent review.", decidedAt: "2026-08-11T11:00:00Z", decidedBy: { id: 78, name: "Authority" } };
    currentRun = run({ reviews: currentRun.reviews, authorityDecisions: [decision] });
    await route.fulfill({ status: 201, contentType: "application/json", body: JSON.stringify({ success: true, data: { decision } }) });
  });
  return { getRun: () => currentRun };
}

test("renders provider authority and records an explicit discrepancy review", async ({ page }) => {
  await signIn(page);
  const provider = await mockProvider(page);
  await page.goto("/audit-resource-management/provider-reconciliation");

  await expect(page.getByRole("heading", { name: "ARMIS Provider Reconciliation", exact: true, level: 2 })).toBeVisible();
  await expect(page.getByText("ARMIS-REC-000091", { exact: true }).first()).toBeVisible();
  await expect(page.getByText("Provider authority status", { exact: true })).toBeVisible();
  await page.getByRole("button", { name: "Record review" }).click();
  await page.getByLabel("Review comment").fill("Independent review confirms the documented capacity difference and its operational basis.");
  await page.getByRole("button", { name: "Record immutable review" }).click();
  await expect(page.getByText("Independent ARMIS reconciliation review recorded.", { exact: true })).toBeVisible();
  expect(provider.getRun().reviews[0].decision).toBe("ACCEPTED");
});

test("keeps the reconciliation workspace usable on mobile", async ({ page }) => {
  await signIn(page);
  await mockProvider(page);
  await page.goto("/audit-resource-management/provider-reconciliation");
  await expect(page.getByRole("heading", { name: "ARMIS Provider Reconciliation", exact: true, level: 2 })).toBeVisible();
  await expect(page.locator("body")).toHaveJSProperty("scrollWidth", await page.evaluate(() => document.documentElement.clientWidth));
});
