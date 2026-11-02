# 07: Customer search for staff

**What to build:** Add `pg_trgm` and the customer search indexes; `GET /customers?query=` finds customers by name (accent-insensitive), phone or email for advisors only, escaping `%` (AC-02.15, D-02.10, D-02.15). New booking gets the customer picker.

**Blocked by:** [06-error-cards-on-new-booking](06-error-cards-on-new-booking.md)

**Status:** ready-for-agent

**Branch:** `feature/02-create-booking`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [DB](../3-database-spec.md); [API](../4-api-spec.md); [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-02-19, TC-02-23, TC-02-36

- [ ] TC-02-19 (AC-02.15; Integration): given Seed customers; when Advisor `?query=hu`, `?query=hùng`, `?query=0903`, `?query=hung@`; customer `?query=hùng`; then 400; Hùng; Hùng; Hùng; 403. Test: `Feature/Customers/CustomerSearchTest::test_AC_02_15_search_rules`.
- [ ] TC-02-23 (D-02.10, API §B4; Integration): given Customer named "100% Auto"; when `?query=%%%`; `?query=100%`; then `%%%`: empty; `100%`: "100% Auto". Test: `CustomerSearchTest::test_like_wildcards_are_escaped`.
- [ ] TC-02-36 (D-02.15; Integration): given 25 customers named "Nguyễn …"; when Advisor `?query=nguy`; then 20 rows; `meta.total` 25. Test: `Feature/Customers/CustomerSearchTest::test_D_02_15_total_is_the_real_count`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
