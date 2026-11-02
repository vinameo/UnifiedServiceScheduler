# 07: Parallel holds by one customer

**What to build:** Two parallel holds for the same customer both answer 201 and exactly one `HELD` remains (INV-10.2, D-10.4).

**Blocked by:** [06-one-hold-per-customer](06-one-hold-per-customer.md)

**Status:** ready-for-agent

**Branch:** `feature/10-temporary-hold`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-10-07, TC-10-21

> TC-10-07 and TC-10-21 share the same When and both prove INV-10.2; TC-10-21 is the stricter one. Make both pass; if they turn out to be one case, raise it with the user rather than delete either.

- [ ] TC-10-07 (INV-10.2, D-10.4; Concurrency): given A has no hold; when 2 processes hold 10:00 and 11:00 for A at once; then Exactly one `HELD` remains; both responses 201. Test: `ParallelHoldTest::test_INV_10_2_one_hold_per_customer_in_parallel`.
- [ ] TC-10-21 (INV-10.2, D-10.4; Concurrency): given A holds 09:00 (v1); when 2 processes hold 10:00 and 11:00 for A at once; then Both 201 (never 412); exactly one `HELD`; the 09:00 hold and the earlier new hold are `EXPIRED` with inactive allocations. Test: `ParallelHoldTest::test_D_10_4_parallel_holds_with_an_existing_hold`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
