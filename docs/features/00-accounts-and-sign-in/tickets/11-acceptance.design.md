# Design notes · 11: Acceptance

| Sketch or rule (spec §) | Invariant (INV/AC) | Checked against (code/DB) | Changes between read and write | Deleted or inactive rows | Same rule elsewhere | Verdict |
| --- | --- | --- | --- | --- | --- | --- |
| Manual acceptance M1–M8 and BA metrics M-00.1–M-00.2 (TC §A2, BA §B2) | TC-00-28, INV-00.2, AC-00.1-AC-00.12 | `docs/features/00-accounts-and-sign-in/6-test-case.md:14` table A2 | Verified across real browser/curl requests and Postgres database rows | All test user entries inspected; no orphaned or invalid state created | TC §A2; BA §B2; RQ §B3 | follow |
